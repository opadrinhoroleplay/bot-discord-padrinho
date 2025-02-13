const { SlashCommandBuilder } = require('discord.js');
const AFKUtils = require('../utils/afkUtils');

module.exports = {
    data: new SlashCommandBuilder()
        .setName('afk')
        .setDescription('Define o teu estado AFK')
        .addStringOption(option =>
            option.setName('razao')
                .setDescription('A razão pela qual vais ficar AFK')
                .setRequired(false)),

    async execute(interaction) {
        const member = interaction.member;
        const reason = interaction.options.getString('razao');

        if (reason) {
            await AFKUtils.set(member, true, reason);
            await interaction.deferReply();
            await interaction.deleteReply();
        } else {
            await AFKUtils.set(member, !AFKUtils.get(member));
            await interaction.deferReply();
            await interaction.deleteReply();
        }
    },
}; 