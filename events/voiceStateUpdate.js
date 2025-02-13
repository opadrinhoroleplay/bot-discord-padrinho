const { Events } = require('discord.js');
const MemberUtils = require('../utils/memberUtils');

module.exports = {
    name: Events.VoiceStateUpdate,
    async execute(oldState, newState, client) {
        const member = newState.member;

        // Don't let non-admin members move to discussion while in-game
        if (!MemberUtils.isAdmin(member) && 
            MemberUtils.isInGame(member) && 
            newState.channelId === client.config.discord.channels.voice.discussion) {
            
            await member.voice.setChannel(oldState.channelId ?? client.config.discord.channels.voice.lobby, 'Attempted to return to General Discussion');
            
            await member.send('Não podes voltar para Discussão Geral enquanto estiveres a jogar.');
            return;
        }

        // Handle voice channel join/leave/move events
        if (!oldState.channel) {
            // Member joined a voice channel
            await client.logChannels.voice.send(`**${member.user.username}** entrou no canal de voz **${newState.channel.name}**.`);

            if (!MemberUtils.isAdmin(member)) {
                if (newState.channelId === client.config.discord.channels.voice.discussion) {
                    await member.send(
                        'Olá! Este canal de voz é para conversas gerais, enquanto não estas a jogar. ' +
                        'Se quiseres um canal privado, para ti e para os teus amigos/equipa utiliza o comando `/voz`.'
                    );
                } else if (newState.channelId === client.config.discord.channels.voice.lobby) {
                    await member.send(
                        'Olá! Cria um canal de voz privado para ti e para os teus amigos/equipa utilizando o comando `/voz`. ' +
                        'Não é suposto ficar aqui a conversar com os outros membros.'
                    );
                }
            } else if (newState.channelId === client.config.discord.channels.voice.admin) {
                await client.adminChannel.send(`**${member.user.username}** entrou no canal de voz de Administração ${newState.channel}.`);
            }
        } else if (!newState.channel) {
            // Member left a voice channel
            await client.logChannels.voice.send(`**${member.user.username}** saiu do canal de voz **${oldState.channel.name}**`);
        } else if (oldState.channelId !== newState.channelId) {
            // Member moved between voice channels
            await client.logChannels.voice.send(`**${member.user.username}** saiu do canal de voz **${oldState.channel.name}** e entrou no canal de voz **${newState.channel.name}**`);
        }
    },
}; 