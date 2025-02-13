const { SlashCommandBuilder } = require('discord.js');
const FiveMUtils = require('../utils/fivemUtils');

module.exports = {
    data: new SlashCommandBuilder().setName('fivem').setDescription('Verifica o estado do FiveM'),

    async execute(interaction) {
        await interaction.deferReply();
        
        const status = await FiveMUtils.status();
        if (status !== null) 
            await interaction.editReply({ content: `**Estado actual do FiveM**: ${status ? 'Online' : 'Offline'}`, ephemeral: false });
        else 
            await interaction.editReply({ content: 'Não foi possível verificar o estado do FiveM.', ephemeral: true });
    }
}; 