import { Events, ChannelType } from 'discord.js';
import Member from '../utils/Member.js';
import config from '../config.js';

export default {
	name: Events.VoiceStateUpdate,
	async execute(oldState, newState, client) {
		const member = newState.member;

		// Don't let non-admin members move to discussion while in-game
		if (!Member.isAdmin(member) && 
			Member.isInGame(member) && 
			newState.channelId === config.discord.channel.voice.discussion) {
			
			await member.voice.setChannel(oldState.channelId ?? config.discord.channel.voice.lobby, 'Attempted to return to General Discussion');
			
			await member.send('Não podes voltar para Discussão Geral enquanto estiveres a jogar.');
			return;
		}

		// Handle voice channel join/leave/move events
		if (!oldState.channel) {
			// Member joined a voice channel
			await client.logChannels.voice.send(`**${member.user.username}** entrou no canal de voz **${newState.channel.name}**.`);

			if (!Member.isAdmin(member)) {
				if (newState.channelId === config.discord.channel.voice.discussion) {
					await member.send(
						'Olá! Este canal de voz é para conversas gerais, enquanto não estas a jogar. ' +
						'Se quiseres um canal privado, para ti e para os teus amigos/equipa utiliza o comando `/voz`.'
					);
				} else if (newState.channelId === config.discord.channel.voice.lobby) {
					await member.send(
						'Olá! Cria um canal de voz privado para ti e para os teus amigos/equipa utilizando o comando `/voz`. ' +
						'Não é suposto ficar aqui a conversar com os outros membros.'
					);
				}
			} else if (newState.channelId === config.discord.channel.voice.admin) {
				await client.staffChannel.send(`**${member.user.username}** entrou no canal de voz de Administração ${newState.channel}.`);
			}
		} else if (!newState.channel) {
			// Member left a voice channel
			await client.logChannels.voice.send(`**${member.user.username}** saiu do canal de voz **${oldState.channel.name}**`);
		} else if (oldState.channelId !== newState.channelId) {
			// Member moved between voice channels
			await client.logChannels.voice.send(`**${member.user.username}** saiu do canal de voz **${oldState.channel.name}** e entrou no canal de voz **${newState.channel.name}**`);
		}

		// 24-hour private channel deletion logic
		const privateChannels = newState.guild.channels.cache
			.filter(channel => 
				channel.type === ChannelType.GuildVoice && 
				channel.parentId === config.discord.category.voice &&
				channel.members.size === 0
			);

		for (const [channelId, channel] of privateChannels) {
			// Skip the lobby channel
			if (channelId === config.discord.channel.voice.lobby) continue;

			// Check if the channel has been empty for more than 24 hours
			const channelCreatedAt = channel.createdAt;
			const emptyDuration = Date.now() - channelCreatedAt.getTime();
			const twentyFourHours = 24 * 60 * 60 * 1000;

			if (emptyDuration > twentyFourHours) {
				try {
					await channel.delete('Channel empty for 24 hours');
					await client.staffChannel.send(`Eliminei o Canal de Voz Privado **${channel.name}** por estar vazio há mais de 24 horas.`);
				} catch (error) {
					console.error(`Erro ao eliminar o canal ${channel.name}:`, error);
				}
			}
		}
	},
}; 