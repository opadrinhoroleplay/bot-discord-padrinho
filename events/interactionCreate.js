import { MessageFlags } from 'discord.js';

export default {
	name: 'interactionCreate',
	async execute(interaction) {
		if (!interaction.isChatInputCommand()) return;
		const command = interaction.client.commands.get(interaction.commandName);
		if (!command) return;
		try {
			await command.execute(interaction);
		} catch (error) {
			console.error('Error executing command:', error);
			if (interaction.replied || interaction.deferred) {
				await interaction.followUp({ content: 'There was an error executing the command!', flags: MessageFlags.Ephemeral });
			} else {
				await interaction.reply({ content: 'There was an error executing the command!', flags: MessageFlags.Ephemeral });
			}
		}
	},
};
