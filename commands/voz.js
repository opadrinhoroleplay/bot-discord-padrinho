import { SlashCommandBuilder, ChannelType, PermissionFlagsBits, MessageFlags, OverwriteType } from 'discord.js';
import Member from '../utils/Member.js';
import Claude, { Models } from '../utils/Claude.js';
import config from '../config.js';

export default {
    data: new SlashCommandBuilder()
        .setName('voz')
        .setDescription('Cria ou modifica um canal de voz privado')
        .addSubcommand(subcommand => subcommand
            .setName('criar')
            .setDescription('Cria um novo canal de voz privado')
            .addMentionableOption(option =>
                option.setName('membros')
                    .setDescription('Menciona os membros que podem entrar no canal')
                    .setRequired(true))
            .addBooleanOption(option =>
                option.setName('notificar')
                    .setDescription('Notificar membros sobre o novo canal')))
        .addSubcommand(subcommand => subcommand
            .setName('info')
            .setDescription('Mostra as informações do teu canal de voz privado'))
        .addSubcommandGroup(group => group
            .setName('editar')
            .setDescription('Modifica o teu canal de voz privado')
            .addSubcommand(subcommand => subcommand
                    .setName('adicionar')
                    .setDescription('Adiciona membros ao teu canal de voz')
                    .addMentionableOption(option =>
                        option.setName('membros')
                            .setDescription('Menciona os membros para adicionar ao canal')
                            .setRequired(true)))
                .addSubcommand(subcommand => subcommand
                    .setName('remover')
                    .setDescription('Remove membros do teu canal de voz')
                    .addMentionableOption(option =>
                        option.setName('membros')
                            .setDescription('Menciona os membros para remover do canal')
                            .setRequired(true))))
        .addSubcommand(subcommand => subcommand
            .setName('eliminar')
            .setDescription('Elimina o teu canal de voz privado')),

    async execute(interaction) {
        // Defer the reply immediately
        await interaction.deferReply({ flags: MessageFlags.Ephemeral });

        const member = interaction.member;
        const subcommand = interaction.options.getSubcommand();
        
        // Check if member has a channel (required for all edit operations)
        const memberVoiceChannel = await Member.getPrivateVoiceChannel(member);
        if (subcommand !== 'criar' && !memberVoiceChannel) return interaction.editReply({ content: 'Não tens um canal de voz privado. Usa primeiro o comando `/voz criar`.' });

        // Handle create command
        if (subcommand === 'criar') {
            if (memberVoiceChannel) return interaction.editReply({ content: 'Já tens um canal de voz privado. Usa o comando `/voz info` para ver informações sobre ele.' });

            const mentionedMembers = interaction.options.getMentionable('membros');
            const channelMembers = Array.isArray(mentionedMembers) ? mentionedMembers : [mentionedMembers];
            const notify = interaction.options.getBoolean('notificar') ?? true;

            if (channelMembers.length === 0) return interaction.editReply({ content: 'Tens que mencionar pelomenos um membro do Discord para fazer parte do teu canal.' });

            // Create channel name using Claude
            const channelNameResponse = await new Claude(process.env.ANTHROPIC_INTERNAL_API_KEY).sendPrompt(
                "The name must adhere to the What3Words format (word.word.word), include words associated with Grand Theft Auto V, and remain under 19 characters. " +
                "It doesn't have to connect to all of them. Use European Portuguese. Please respond solely with the name in the specified format - nothing more.",
                1, 15, Models.HAIKU3
            );

            let channelName = channelNameResponse.content[0]?.text;
            if (!channelName) return interaction.editReply({ content: 'Não consegui criar um nome para o canal. Tenta novamente mais tarde.' });

            // Create the channel
            const channel = await interaction.guild.channels.create({
                name: channelName,
                type: ChannelType.GuildVoice,
                parent: config.discord.category.voice,
                bitrate: 96000,
                permissionOverwrites: [
                    {
                        id: interaction.guild.id,
                        deny: [PermissionFlagsBits.Connect],
                        allow: [PermissionFlagsBits.ViewChannel]
                    },
                    {
                        id: member.id,
                        allow: [
                            PermissionFlagsBits.Connect,
                            PermissionFlagsBits.UseVAD,
                            PermissionFlagsBits.PrioritySpeaker,
                            PermissionFlagsBits.MuteMembers,
                            PermissionFlagsBits.DeafenMembers,
                        ]
                    }
                ]
            }).catch(error => {
                console.error("Erro ao criar canal:", error);
                return interaction.editReply({ content: 'Não consegui criar o canal. Tenta novamente mais tarde.' });
            });

            interaction.client.staffChannel.send(`${member} criou um novo Canal de Voz Privado: ${channel}`);

            // Move member to the new channel
            if (member.voice.channel) await member.voice.setChannel(channel);

            // Add permissions for mentioned members
            for (const channelMember of channelMembers) {
                await channel.permissionOverwrites.create(channelMember, { Connect: true, UseVAD: true }); // Better to just wait
                if (notify) channelMember.send(`${member} autorizou-te a entrar no Canal de Voz Privado '${channel.name}'.`);
            }

            return interaction.editReply({ content: `Criei o Canal ${channel} para ti e para os teus amigos.` });
        }

        // Get the channel for edit operations
        const channel = memberVoiceChannel;

        // Handle edit and new "membros" subcommand
        switch (subcommand) {
            case 'adicionar': {
                const mentionedMembers = interaction.options.getMentionable('membros');
                const channelMembers = Array.isArray(mentionedMembers) ? mentionedMembers : [mentionedMembers];
                const validMembers = channelMembers.filter(mention => mention.user && mention.id !== member.id).map(mention => interaction.guild.members.cache.get(mention.id)).filter(m => m);

                if (validMembers.length === 0) return interaction.editReply({ content: 'Tens que mencionar pelomenos um membro do Discord para adicionar ao canal.' });

                for (const channelMember of validMembers) {
                    channel.permissionOverwrites.create(channelMember, { Connect: true, UseVAD: true });
                    channelMember.send(`${member} autorizou-te a entrar no Canal de Voz Privado '${channel.name}'.`);
                }

                return interaction.editReply({ content: `Adicionaste ${validMembers.length} ${validMembers.length === 1 ? 'membro' : 'membros'} ao teu Canal de Voz Privado: ${channel}.` });
            }

            case 'remover': {
                const mentionedMembers = interaction.options.getMentionable('membros');
                const channelMembers = Array.isArray(mentionedMembers) ? mentionedMembers : [mentionedMembers];
                const validMembers = channelMembers.filter(mention => mention.user && mention.id !== member.id).map(mention => interaction.guild.members.cache.get(mention.id)).filter(m => m);

                if (validMembers.length === 0) return interaction.editReply({ content: 'Tens que mencionar pelomenos um membro do Discord para remover do canal.' });

                for (const channelMember of validMembers) {
                    channel.permissionOverwrites.delete(channelMember.id);
                    channelMember.send(`${member} removeu o teu acesso ao Canal de Voz Privado '${channel.name}'.`);
                    
                    // Move them to lobby
                    channelMember.voice.setChannel(config.discord.channel.voice.lobby);
                }

                return interaction.editReply({ content: `Removeste ${validMembers.length} ${validMembers.length === 1 ? 'membro' : 'membros'} do teu Canal de Voz Privado: ${channel}.` });
            }

            case 'info': {
                const members = memberVoiceChannel.permissionOverwrites.cache
                    .filter(perm => 
                        perm.type === OverwriteType.Member && // User type permission overwrite
                        perm.id !== member.id && 
                        perm.allow.has(PermissionFlagsBits.Connect)
                    )
                    .map(perm => `<@${perm.id}>`)
                    .join(', ');

                return interaction.editReply({ 
                    content: members 
                        ? `Membros com acesso ao teu Canal de Voz Privado ${memberVoiceChannel}:\n${members}`
                        : `Não há outros membros com acesso ao teu Canal de Voz Privado ${memberVoiceChannel}.`
                });
            }

            case 'eliminar': {
                const channelName = memberVoiceChannel.name;
                await memberVoiceChannel.delete();
                return interaction.editReply({ content: `Eliminei o teu Canal de Voz Privado: \`${channelName}\`.` });
            }
        }
    }
}; 