import { Events } from 'discord.js';

export default {
	name: Events.MessageCreate,
	async execute(message, client) {
		if (message.author.bot) return;

		// Check for Discord invite links
		const inviteRegex = /discord\.(gg|com\/invite)\//i;
		if (inviteRegex.test(message.content)) {
			try {
				await message.delete();
				await message.channel.send(`${message.author}, não é permitido partilhar convites do Discord neste servidor.`);
				console.log(`Deleted invite link from ${message.author.tag}: ${message.content}`);
			} catch (error) {
				console.error('Error deleting invite link message:', error);
			}
			return; // Stop further processing if it was an invite link
		}
	},
}; 