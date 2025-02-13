const { SlashCommandBuilder } = require('discord.js');
const MemberUtils = require('../utils/memberUtils');
const { slugify } = require('../utils/wordUtils');

module.exports = {
    data: new SlashCommandBuilder().setName('convite').setDescription('Gera um link de convite personalizado'),

    async execute(interaction) {
        const member = interaction.member;
        const inviterSlug = slugify(member.user.username);

        const [rows] = await interaction.client.db.query('SELECT code FROM invites WHERE inviter_id = ?', [member.id]);

        if (rows.length > 0) return interaction.reply({ content: `Olá ${member.user.username}, este é o teu link de convite: http://opadrinhoroleplay.pt/convite.php?slug=${inviterSlug}`, ephemeral: true });

        // Create new invite
        try {
            const invite = await interaction.guild.channels.cache
                .get(interaction.client.config.discord.channels.main)
                .createInvite({
                    maxAge: 0,
                    maxUses: 0,
                    temporary: false,
                    unique: true,
                    reason: `Codigo de Convite para '${member.user.username}'`
                });

            if (!await MemberUtils.exists(member)) await MemberUtils.create(member);

            // Save invite to database
            await interaction.client.db.query( 'INSERT INTO invites (code, inviter_id, inviter_slug) VALUES (?, ?, ?)', [invite.code, member.id, inviterSlug]);

            await interaction.reply({ content: `Olá ${member.user.username}, este é o teu link de convite: https://www.opadrinhoroleplay.pt/convidar.php?slug=${inviterSlug}`, ephemeral: true});

            await interaction.client.adminChannel.send(`O utilizador **${member.user.username}** criou um convite. (Slug: '${inviterSlug}')`);
        } catch (error) {
            console.error('Error creating invite:', error);
            return interaction.reply({ content: `Ocorreu um erro ao gerar o teu código de convite! Fala com o <@${interaction.client.config.discord.users.owner}>`, ephemeral: true});
        }
    },
}; 