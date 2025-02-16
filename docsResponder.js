const fs = require('fs');
const path = require('path');
const { createHash } = require('crypto');
const Anthropic = require('@anthropic-ai/sdk');
const { TfIdf, WordTokenizer } = require('natural');

const anthropic = new Anthropic({ apiKey: process.env.ANTHROPIC_API_KEY });
const tfidf = new TfIdf();
const tokenizer = new WordTokenizer();
const docsCache = {};
const DOCS_DIR = './docs';

const getFileHash = content => createHash('sha256').update(content).digest('hex');

const loadDocsFromGithub = async () => {
	console.info('Loading docs from Github...');

	try {
		if (!fs.existsSync(DOCS_DIR)) fs.mkdirSync(DOCS_DIR);

		const response = await fetch('https://api.github.com/repos/opadrinhoroleplay/docs/git/trees/main?recursive=1');
		const { tree: files } = await response.json();

		console.info(`Found ${files.length} files`);

		let updated = 0;
		
		// Phase 1: Check and update files from GitHub
		for (const file of files) {
			if (!file.path.endsWith('.md') || file.path.includes('README.md')) continue;
			const fileName = path.basename(file.path);
			const filePath = path.join(DOCS_DIR, fileName);
			const fileResponse = await fetch(`https://raw.githubusercontent.com/opadrinhoroleplay/docs/main/${file.path}`);
			const text = await fileResponse.text();
			
			if (fs.existsSync(filePath)) {
				const cachedText = fs.readFileSync(filePath, 'utf-8');

				if (getFileHash(cachedText) === getFileHash(text)) continue;

				updated++;
			} else {
				updated++;
			}

			fs.writeFileSync(filePath, text, 'utf-8');
		}
		console.info(`Updated ${updated} files`);
		
		// Phase 2: Load all markdown files into memory for processing
		const localFiles = fs.readdirSync(DOCS_DIR);
		console.info('Found files: ' + localFiles.join(', '));

		for (const fileName of localFiles) {
			if (!fileName.endsWith('.md') || fileName === 'README.md') continue;

			const fullPath = path.join(DOCS_DIR, fileName);
			const text = fs.readFileSync(fullPath, 'utf-8');

			docsCache[fileName] = { text, name: path.basename(fileName, '.md') };
			tfidf.addDocument(text);
		}
	} catch (error) {
		console.error('Error loading docs:', error);
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
  
  // Output the selected document paths
  console.info('Selected documents: ' + selectedDocs.map(item => item.path).join(', '));
  
  // Return only sections with strict whole-word matches along with file identifiers
  return selectedDocs.map(({ doc, path }) => {
    const sections = doc.text.split(/(?=^#+ )/gm);
    const matchedSections = sections.filter(section =>
      tokens.some(token => new RegExp(`\\b${token}\\b`, 'i').test(section))
    ).join('\n\n');
    return `File: ${path}\n${matchedSections}`;
  }).join('\n\n');
};

// Prompter is a Discord author object
const generateResponse = async (prompter, prompt) => {
	const relevantDocs = findRelevantDocs(prompt);

	if (!relevantDocs) return null;

	const prompterRoles = prompter.roles?.cache?.map(role => role.name) || [];

	const content =
		"\nHere are some excerpts from the documentation, for your reference, taken from the website (notice it's written in GitHub flavoured Markdown):\n" +
		relevantDocs +
		"\n\nYour name is Fredo Corleone and you're the discord helper for the FiveM roleplay server of O Padrinho Roleplay (Your name is based on the Godfather movie series). You help the users with their questions and provide them with the documentation." +
		"\n\nProvide any of the following links (prefixing the base url - https://www.opadrinhoroleplay.pt and formatting them as Discord links) and send them to the user if they are relevant to the question and/or if you think the excerpts are not enough to answer the question:\n" +
		'{baseUrl}/guias/regras-do-servidor\n' +
		'{baseUrl}/guias/como-fazer-roleplay\n' +
		'{baseUrl}/guias/criar-personagens\n' +
		'{baseUrl}/guias/sistema-de-progressao\n' +
		'{baseUrl}/guias/economia\n' +
		'{baseUrl}/guias/faccoes-e-organizacoes\n' +
		'{baseUrl}/guias/quiz\n' +
		'{baseUrl}/guias/regras-quiz\n' +
		'{baseUrl}/guias/diretrizes\n' +
		'{baseUrl}/guias/tickets-e-bugs\n' +
		'{baseUrl}/guias/live-service\n' +
		'Or send them to "{baseUrl}/#guias" if they are not relevant to the question.\n' +
		'Portal URL: https://portal.opadrinhoroleplay.pt\n' +
		'If you think the excerpts are not enough information it\'s better to not make up information.' +
		`\n\nThis user, that you're replying to, is '${prompter.username}' (Roles: ${prompterRoles.join(', ')}) and is asking: ${prompt}` +
		"\n\nPlease respond based on the provided documentation, using a friendly passive-aggressive 'mafia-like' tone, without greetings, but being direct and concise, in European Portuguese, like you're answering a question that wasn't directed at you and as if you're just helping out a friend. Format as Discord flavoured Markdown, if you have a lot to say. But don't forget to use accents and special characters.";

	console.info(content);

	const message = await anthropic.messages.create({
		model: 'claude-3-haiku-20240307',
		max_tokens: 2000,
		messages: [{ role: 'user', content }],
	});

	// console.log('Haiku message:', JSON.stringify(message, null, 2));

	// Extract just the text content from the response
	return message.content[0]?.text || null;
};

const startDocsRefreshing = async () => {
	await loadDocsFromGithub();
	const timeToMidnight = new Date().setHours(24, 0, 0, 0) - new Date();
	setTimeout(startDocsRefreshing, timeToMidnight > 0 ? timeToMidnight : 86400000);
};

const setup = client => {
	client.on('messageCreate', async message => {
		if (message.author.bot || !message.content.trim().endsWith('?')) return;

		message.channel.sendTyping();
		
		console.info(`Received message from ${message.user}: ${message.content}`);
		
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

			console.info('\nHaiku response: ' + response);

			if (response) await message.reply(response);
		} catch (error) {
			console.error(`Error generating response for ${message.user} (${message.content}):`, error);
		}
	});
	startDocsRefreshing().catch(err => console.error('Error starting docs refreshing:', err));
};

module.exports = { setup };
