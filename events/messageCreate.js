import { Events } from 'discord.js';

export default {
	name: Events.MessageCreate,
	async execute(message, client) {
		if (message.author.bot) return;
	},
}; 