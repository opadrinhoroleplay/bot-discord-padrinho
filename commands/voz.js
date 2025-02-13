const { SlashCommandBuilder, ChannelType, PermissionFlagsBits } = require('discord.js');
const MemberUtils = require('../utils/memberUtils');
const { generateWhatThreeWords, slugify } = require('../utils/wordUtils');

module.exports = {
    data: new SlashCommandBuilder()
        .setName('voz')
        .setDescription('Cria ou modifica um canal de voz privado')
        .addStringOption(option =>
            option.setName('membros')
                .setDescription('Menciona os membros que podem entrar no canal (@membro1 @membro2...)')
                .setRequired(true))
        .addStringOption(option =>
            option.setName('nome')
                .setDescription('Nome do canal')
                .setRequired(false)),

    async execute(interaction) {
        const member         = interaction.member;
        const memberMentions = interaction.options.getString('membros');
        const channelName    = interaction.options.getString('nome');

        // Parse mentioned members
        const memberIds = memberMentions.match(/<@!?(\d+)>/g);
        if (!memberIds) return interaction.reply({ content: 'Tens que especificar/mencionar (@membro) pelomenos um membro do Discord para fazer parte do teu canal.', ephemeral: true });

        const channelMembers = [];
        for (const mention of memberIds) {
            const id = mention.replace(/<@!?(\d+)>/, '$1');
            if (id === member.id) continue;

            const mentionedMember = await interaction.guild.members.fetch(id).catch(() => null);
            if (mentionedMember) channelMembers.push(mentionedMember);
        }

        if (channelMembers.length === 0) return interaction.reply({ content: 'Não consegui identificar algum Membro. Tens que \'@mencionar\' cada um deles.', ephemeral: true });

        // Check if member already has a channel
        const existingChannelId = MemberUtils.getMemberVoiceChannel(member);
        if (existingChannelId) {
            const channel = interaction.guild.channels.cache.get(existingChannelId);
            
            // Update channel name if provided
            if (channelName) await channel.setName(slugify(channelName));

            // Remove all existing member permissions except owner
            const memberPerms = channel.permissionOverwrites.cache.filter(perm => perm.type === 1 && perm.id !== member.id);
            for (const [id, perm] of memberPerms) await channel.permissionOverwrites.delete(id);

            // Add new member permissions
            for (const channelMember of channelMembers) await channel.permissionOverwrites.create(channelMember, { Connect: true, UseVAD: true });
            for (const channelMember of channelMembers) await channelMember.send(`${member} autorizou-te a entrar no Canal de Voz Privado '${channel.name}'.`);

            if (member.voice.channel) await member.voice.setChannel(channel);

            return interaction.reply({ content: `Alteraste o teu Canal de Voz Privado: ${channel}.`, ephemeral: true });
        } else {
            // Create new voice channel
            const channel = await interaction.guild.channels.create({
                name: channelName ? slugify(channelName) : generateWhatThreeWords(),
                type: ChannelType.GuildVoice,
                parent: '1030787112628400198', // Voice category ID
                bitrate: 96000,
                permissionOverwrites: [
                    {
                        id: interaction.guild.id,
                        deny: [PermissionFlagsBits.Connect]
                    },
                    {
                        id: member.id,
                        allow: [
                            PermissionFlagsBits.Connect,
                            PermissionFlagsBits.UseVAD,
                            PermissionFlagsBits.PrioritySpeaker,
                            PermissionFlagsBits.MuteMembers
                        ]
                    }
                ]
            });

            await interaction.client.adminChannel.send(`${member} criou um novo Canal de Voz Privado: ${channel}`);

            // Add permissions for mentioned members
            for (const channelMember of channelMembers) await channel.permissionOverwrites.create(channelMember, { Connect: true, UseVAD: true });
            for (const channelMember of channelMembers) await channelMember.send(`${member} autorizou-te a entrar no Canal de Voz Privado '${channel.name}'.`);

            if (member.voice.channel) await member.voice.setChannel(channel);

            return interaction.reply({ content: `Criei o Canal ${channel} para ti e para os teus amigos.`, ephemeral: true });
        }
    }
}; 