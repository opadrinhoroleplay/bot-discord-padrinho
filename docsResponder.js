import { createHash } from 'crypto';
import { readFileSync, writeFileSync, existsSync, mkdirSync, readdirSync } from 'fs';
import { join, basename } from 'path';
import Anthropic from '@anthropic-ai/sdk';
import natural from 'natural';
import { Models } from './utils/Claude.js';
import Member from './utils/Member.js';
import { XMLBuilder } from 'fast-xml-parser';

const anthropic = new Anthropic({ apiKey: process.env.ANTHROPIC_API_KEY });
const tfidf = new natural.TfIdf();
const tokenizer = new natural.WordTokenizer();
const docsCache = {};
const DOCS_DIR = './docs';

const getFileHash = content => createHash('sha256').update(content).digest('hex');

const loadDocsFromGithub = async () => {
	console.info('[Docs] Loading docs from Github...');

	try {
		if (!existsSync(DOCS_DIR)) mkdirSync(DOCS_DIR);

		const response = await fetch('https://api.github.com/repos/opadrinhoroleplay/docs/git/trees/main?recursive=1');
		const { tree: files } = await response.json();

		console.info(`[Docs] Found ${files.length} files in the docs repository.`);

		let updated = 0;
		
		// Phase 1: Check and update files from GitHub
		for (const file of files) {
			if (!file.path.endsWith('.md') || file.path.includes('README.md')) continue;
			const fileName = basename(file.path);
			const filePath = join(DOCS_DIR, fileName);
			const fileResponse = await fetch(`https://raw.githubusercontent.com/opadrinhoroleplay/docs/main/${file.path}`);
			const text = await fileResponse.text();
			
			if (existsSync(filePath)) {
				const cachedText = readFileSync(filePath, 'utf-8');

				if (getFileHash(cachedText) === getFileHash(text)) continue;

				updated++;
			} else {
				updated++;
			}

			writeFileSync(filePath, text, 'utf-8');
		}
		console.info(`[Docs] Updated ${updated} files.`);
		
		// Phase 2: Load all markdown files into memory for processing
		const localFiles = readdirSync(DOCS_DIR);

		for (const fileName of localFiles) {
			if (!fileName.endsWith('.md') || fileName === 'README.md') continue;

			const fullPath = join(DOCS_DIR, fileName);
			const text = readFileSync(fullPath, 'utf-8');

			docsCache[fileName] = { text, name: basename(fileName, '.md') };
			tfidf.addDocument(text);
		}
	} catch (error) {
		console.error('[Docs] Error loading docs:', error);
	}
};

// topK is the number of relevant docs to return
const findRelevantDocs = (query, topK = 3) => {
	const tokens = tokenizer.tokenize(query.toLowerCase());
	
	const docsWithScores = Object.keys(docsCache).map(filePath => {
		const doc = docsCache[filePath];
		const docText = doc.text.toLowerCase();
		let nameScore = 0, headerScore = 0, contentScore = 0;
		
		tokens.forEach(token => {
			const regex = new RegExp(`\\b${token}\\b`, 'gi');
			// Score filename strictly: add 2 if token appears at least once
			if (regex.test(doc.name)) nameScore += 2;
			
			// Score header matches: add 3 for every occurrence in each header line
			const headers = doc.text.match(/^#+\s+.+/gm) || [];
			headers.forEach(header => {
				const matches = header.match(regex);
				if (matches) headerScore += 3 * matches.length;
			});
			
			// Score content: count all whole word occurrences in the document content
			const contentMatches = docText.match(regex);
			if (contentMatches) contentScore += contentMatches.length;
		});
		
		// Enforce strictness: document must have at least one match in its filename or headers
		if ((nameScore + headerScore) === 0) return null;
		
		return { path: filePath, score: nameScore + headerScore + contentScore, doc };
	}).filter(item => item !== null);
	
	// Increase strictness by using a higher minimum score
	const MIN_SCORE = 5;
	const selectedDocs = docsWithScores
		.filter(item => item.score >= MIN_SCORE)
		.sort((a, b) => b.score - a.score)
		.slice(0, topK);
	
	if (selectedDocs.length === 0) return null;
	
	// Return results in a cleaner format
	return selectedDocs.map(({ doc, path, score }) => {
		const sections = doc.text.split(/(?=^#+ )/gm);
		const matchedSections = sections.filter(section => tokens.some(token => new RegExp(`\\b${token}\\b`, 'i').test(section)));

		return {
			path: path,
			name: doc.name,
			score: score,
			matched_sections: matchedSections.map(section => section.trim())
		};
	});
};

// Prompter is a Discord author object
const generateResponse = async (author, question) => {
	const relevantDocs = findRelevantDocs(question);
	const isServerOwner = Member.isOwner(author.id);

	console.info('[Docs] ' + author.username + ' (' + author.id + ') has asked: ' + question);

	// Define the system prompt for Claude's role
	let systemPrompt = 
		"You are Fredo Corleone, the discord helper for the 'O Padrinho Roleplay' FiveM server. A connosieur in Roleplay, especially in FiveM. " +
		"As the Underboss of the Corleone family, you have a passive-aggressive psycho mafia-like personality. " +
		"You compensate for your lack of toughness with charisma and a gregarious personality. " +
		"You must ALWAYS respond in European Portuguese (from Portugal)." +
		"You are direct, concise, and skip greetings." + 
		"Keep it friendly and personal when talking about documentation.";

	if (isServerOwner) {
		systemPrompt += "VIRUXE is the owner of the server. Address him like he is Don Vito Corleone. Abide by his instructions and do not question his decisions. " +
		"If he asks you to do something and there is a member mention in his query, you reply by mentioning the member (<@member_id>) and directed to the member mentioned.";
	}

	// Create XML object structure
	const xmlObject = {
		context: {
			available_links: {
				base_url: 'https://www.opadrinhoroleplay.pt',
				guides: {
					'/guias/regras-do-servidor': 'Regras do Servidor',
					'/guias/como-fazer-roleplay': 'Como Fazer Roleplay',
					'/guias/criar-personagens': 'Criar Personagens',
					'/guias/sistema-de-progressao': 'Sistema de Progressão',
					'/guias/economia': 'Economia',
					'/guias/faccoes-e-organizacoes': 'Facções e Organizações',
					'/guias/quiz': 'Quiz',
					'/guias/regras-quiz': 'Regras do Quiz',
					'/guias/diretrizes': 'Diretrizes',
					'/guias/tickets-e-bugs': 'Tickets e Bugs',
					'/guias/live-service': 'Live Service'
				},
				fallback_links: {
					guides_fallback: '/#guias',
					portal: 'https://portal.opadrinhoroleplay.pt',
					server_ip: 'http://cfx.re/join/e63a9p'
				}
			},
			user_query: {
				user_id: author.id,
				username: author.username,
				question: question
			},
			note: 'The server is not yet completed.'
		},
		instruction: [
			'If the question matches a specific guide, provide the relevant link from the guides list',
			'If no specific guide matches, use the guides_fallback link',
			'For portal questions, use the portal link',
			'For server IP questions, use the server_ip link',
			'Always prefix guide links with the base_url',
			'Format the response for Discord Markdown. Allowing links to be clicked.'
		]
	};

	// Modify how relevant documentation is added to xmlObject
	if (relevantDocs) {
		xmlObject.context.relevant_documentation = {
			document: relevantDocs.map(doc => ({
				path: doc.path,
				name: doc.name,
				score: doc.score,
				section: doc.matched_sections
			}))
		};
	}

	// Add server owner flag if applicable
	if (isServerOwner) xmlObject.context.user_query.server_owner = true;

	// Convert XML object to XML string
	const xmlString = new XMLBuilder().build(xmlObject);

	console.log('[Docs] XML String:', xmlString);

	const message = await anthropic.messages.create({
		model: Models.HAIKU,
		max_tokens: 2000,
		system: systemPrompt,
		messages: [{ 
			role: 'user', 
			content: xmlString 
		}]
	});

	const output = message.content[0]?.text;

	if (!output) console.error('[Docs] No response from Claude.', JSON.stringify(message, null, 2));

	return output;
};

const startDocsRefreshing = async () => {
	await loadDocsFromGithub();
	const timeToMidnight = new Date().setHours(24, 0, 0, 0) - new Date();
	setTimeout(startDocsRefreshing, timeToMidnight > 0 ? timeToMidnight : 86400000);
};

const setup = client => {
	client.on('messageCreate', async message => {
		if (message.author.bot || !message.content.trim().endsWith('?') && !message.mentions.has(client.user)) return;

		message.channel.sendTyping();
		
		try {
			// Parse member tags and use their username instead of the tags
			const content = message.content.replace(/<@!?(\d+)>/g, (match, userId) => {
				const member = message.guild.members.cache.get(userId);
				return member ? member.user.username : match;
			}).replace(/<@&(\d+)>/g, (match, roleId) => { // Replace role tags with the role name
				const role = message.guild.roles.cache.get(roleId);
				return role ? role.name : match;
			});

			const response = await generateResponse(message.author, content);

			if (response) await message.reply(response), console.log('[Docs] Claude\'s response:', response); else await message.reply('Não consegui encontrar a resposta para a tua pergunta. Tenta reformular a tua pergunta.');
		} catch (error) {
			console.error(`[Docs] Error generating response for ${message.user} (${message.content}):`, error);
		}
	});
	startDocsRefreshing().catch(err => console.error('[Docs] Error starting refreshing:', err));
};

export { setup };
