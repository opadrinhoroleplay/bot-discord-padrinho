import 'dotenv/config';
import mysql from 'mysql2/promise';
import { readdir } from 'fs/promises';
import { Client, GatewayIntentBits, Partials, Collection } from 'discord.js';

import config from './config.js';
import CFXStatusService from './utils/CFXStatusService.js';

import { setup as setupDocsResponder } from './docsResponder.js';

const client = new Client({
	intents: [
		GatewayIntentBits.Guilds,
		GatewayIntentBits.GuildMembers,
		GatewayIntentBits.GuildMessages,
		GatewayIntentBits.GuildPresences,
		GatewayIntentBits.GuildVoiceStates,
		GatewayIntentBits.MessageContent,
		GatewayIntentBits.GuildInvites
	],
	partials: [
		Partials.Message,
		Partials.Channel,
		Partials.Reaction,
		Partials.User,
		Partials.GuildMember
	]
});

// Global variables
client.commands = new Collection();
client.invitesUses = new Map();
client.memberStatus = new Map();
client.startTime = new Date();

(async () => { // We need to load a bunch of shit asynchronously
	// Load Events
	const eventFiles = (await readdir('events')).filter(file => file.endsWith('.js'));

	for (const file of eventFiles) {
		const event = await import(`./events/${file}`);

		if (event.default.once) {
			client.once(event.default.name, (...args) => event.default.execute(...args, client));
		} else {
			client.on(event.default.name, (...args) => event.default.execute(...args, client));
		}
	}
	console.log(`[Init] Loaded ${eventFiles.length} events`);

	// Load Commands
	const commandFiles = (await readdir('commands')).filter(file => file.endsWith('.js'));

	for (const file of commandFiles) {
		const filePath = `./commands/${file}`;

		try {
			const commandModule = await import(filePath);
			const command = commandModule.default;

			if (!command) {
				console.error(`[ERROR] No default export found in ${file}`);
				continue;
			}

			if ('data' in command && 'execute' in command) {
				try {
					// Validate command data and execute method
					if (!command.data || typeof command.data.setName !== 'function') {
						console.error(`[ERROR] Invalid command data in ${file}`);
						continue;
					}

					if (typeof command.execute !== 'function') {
						console.error(`[ERROR] Invalid execute method in ${file}`);
						continue;
					}

					const commandName = command.data.name;
					client.commands.set(commandName, command);
				} catch (validationError) {
					console.error(`[ERROR] Command validation failed for ${file}:`, validationError);
				}
			} else {
				console.warn(`[WARNING] The command at ${filePath} is missing a required "data" or "execute" property.`);
				console.log('Command details:', command);
			}
		} catch (error) {
			console.error(`[ERROR] Error loading command file ${file}:`, error);
		}
	}
	console.log(`[Init] Loaded ${client.commands.size} commands: ${client.commands.map(command => command.data.name).join(', ')}`);

	// Initialize Database
	try {
		const connection = await mysql.createConnection({
			host: config.database.host,
			user: config.database.user,
			password: config.database.password,
			database: config.database.database,
			waitForConnections: true,
			connectionLimit: 10,
			queueLimit: 0
		});

		console.log('[Database] Connected to MySQL database');
		client.db = connection;
	} catch (error) {
		// Log the actual configuration being used (without password)
		console.error('[Database] Error connecting to MySQL database:', {
			host: config.database.host,
			user: config.database.user,
			database: config.database.database
		}, error);
		process.exit(1);
	}

	// Set up CFX status checking interval
	let previousStatus = await CFXStatusService.getLastSavedStatus();
	console.info(`[CFX Status] Previous CFX status: ${previousStatus ? 'Found' : 'Not found'}`);

	// Calculate milliseconds until next hour
	const scheduleStatusCheck = () => {
		const now = new Date();
		const nextHour = new Date(now.getFullYear(), now.getMonth(), now.getDate(), now.getHours() + 1, 0, 0, 0);
		const timeToNextHour = nextHour.getTime() - now.getTime();
			
		console.info(`[CFX Status] Next status check in ${Math.floor(timeToNextHour / 1000 / 60)} minutes`);

		const statusCheckTimer = setTimeout(async () => {
			try {
				const currentStatus = await CFXStatusService.fetchStatus();

				if (!currentStatus) return;

				// Detect significant changes using the new method
				const hasStatusChanged = currentStatus.hasSignificantChange(previousStatus);

				// Report only if there's a significant change and bot is ready
				if (!hasStatusChanged) return;

				const statusMessage = currentStatus.generateMessage();

				client.mainChannel.send({ content: statusMessage.text, embeds: [statusMessage.embed] });
				previousStatus = currentStatus;
			} catch (error) {
				console.error(`[CFX Status] Error on status check: ${error}`);
			}

			// Schedule next check
			scheduleStatusCheck();
		}, timeToNextHour);

		// Optional: Clear timer on client close to prevent memory leaks
		client.on('close', () => clearTimeout(statusCheckTimer));
	};

	// Start the recursive scheduling
	scheduleStatusCheck();
})();

// Initialize docs responder
setupDocsResponder(client);

// Error handling
process.on('unhandledRejection', error => console.error('Unhandled promise rejection:', error));

client.on('error', error => console.error('Discord client error:', error));

client.login(process.env.DISCORD_TOKEN); 