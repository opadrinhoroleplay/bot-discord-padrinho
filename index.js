require('dotenv').config();
const { Client, GatewayIntentBits, Partials, Collection } = require('discord.js');
const { config } = require('./config');
const path = require('path');

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
client.config = config;
client.commands = new Collection();
client.invitesUses = new Map();
client.memberStatus = new Map();
client.startTime = new Date();

// Load handlers
['events', 'commands', 'database'].forEach(handler => require(`${path.join(__dirname, 'handlers')}/${handler}`)(client));

// Initialize docs responder
const { setup: setupDocsResponder } = require('./docsResponder');
setupDocsResponder(client);

// Error handling
process.on('unhandledRejection', error => console.error('Unhandled promise rejection:', error));

client.on('error', error => console.error('Discord client error:', error));

client.login(client.config.DISCORD_TOKEN); 