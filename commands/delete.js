const { SlashCommandBuilder, PermissionFlagsBits, MessageFlags } = require('discord.js');

module.exports = {
	data: new SlashCommandBuilder()
		.setName('delete')
		.setDescription('Apaga mensagens deste canal')
		.addIntegerOption(option => option.setName('quantidade').setDescription('Número de mensagens a apagar'))
		.addStringOption(option => option.setName('id').setDescription('ID da mensagem até à qual queres apagar as mensagens'))
		.addBooleanOption(option => option.setName('todas').setDescription('Apaga todas as mensagens deste canal (apenas o dono)')),

	async execute(interaction) {
		const amount      = interaction.options.getInteger('quantidade');
		const messageId   = interaction.options.getString('id');
		const allMessages = interaction.options.getBoolean('todas');

		// Verifies if the user has ManageMessages permission
		if (!interaction.member.permissions.has(PermissionFlagsBits.ManageMessages)) {
			await interaction.reply({ content: 'Desculpa, mas não tens permissão para apagar mensagens.', flags: MessageFlags.Ephemeral });
			return;
		}

		// Option: Delete all messages (owner only)
		if (allMessages) {
			if (interaction.guild.ownerId !== interaction.user.id) {
				await interaction.reply({ content: 'Desculpa, apenas o dono pode usar a opção de apagar todas as mensagens.', flags: MessageFlags.Ephemeral });
				return;
			}
			
			await interaction.reply({ content: 'Vou começar a apagar todas as mensagens deste canal...', flags: MessageFlags.Ephemeral });

			let total = 0;
			let fetched;

			do {
				fetched = await interaction.channel.messages.fetch({ limit: 100 });

				for (const msg of fetched.values()) {
					try {
						await msg.delete();
						total++;
					} catch (e) {
						console.error('Erro ao apagar a mensagem:', e);
					}
				}
			} while (fetched.size > 0);

			await interaction.editReply({ content: `Apaguei ${total} mensagens.`, flags: MessageFlags.Ephemeral });
			return;
		}

		// Ensures that exactly one of 'amount' or 'message_id' is provided
		if ((amount && messageId) || (!amount && !messageId)) {
			await interaction.reply({ content: 'Tens de indicar ou a quantidade de mensagens ou o ID da mensagem, mas não ambos.', flags: MessageFlags.Ephemeral });
			return;
		}

		await interaction.reply({ content: 'Vou apagar as mensagens...', flags: MessageFlags.Ephemeral });

		// Deletion by amount
		if (amount) {
			let count = 0;
			let remaining = amount;

			while (remaining > 0) {
				const batchSize = remaining > 100 ? 100 : remaining;
				const messages  = await interaction.channel.messages.fetch({ limit: batchSize });

				if (messages.size === 0) break;

				for (const msg of messages.values()) {
					try {
						await msg.delete();
						count++;
					} catch (e) {
						console.error('Erro ao apagar a mensagem:', e);
					}
				}
				remaining -= messages.size;
			}

			await interaction.editReply({ content: `Apaguei ${count} mensagens.`, flags: MessageFlags.Ephemeral });
			return;
		}

		// Deletion up to a given message ID
		if (messageId) {
			let targetMessage;
			try {
				targetMessage = await interaction.channel.messages.fetch(messageId);
			} catch (e) {
				await interaction.editReply({ content: 'Não consegui encontrar a mensagem com o ID fornecido.', flags: MessageFlags.Ephemeral });
				return;
			}

			let count = 0;
			let targetDeleted = false;
			let lastId;

			while (true) {
				const fetched = await interaction.channel.messages.fetch({ limit: 100, before: lastId });

				if (fetched.size === 0) break;
				for (const msg of fetched.values()) {
					try {
						await msg.delete();
						count++;
					} catch (e) {
						console.error('Erro ao apagar a mensagem:', e);
					}

					if (msg.id === targetMessage.id) {
						targetDeleted = true;
						break;
					}
				}

				if (targetDeleted) break;

				lastId = fetched.last().id;

				if (fetched.size < 100) break;
			}

			if (!targetDeleted) {
				try {
					await targetMessage.delete();
					count++;
				} catch (e) {
					console.error('Erro ao apagar a mensagem alvo:', e);
				}
			}

			await interaction.editReply({ content: `Apaguei ${count} mensagens até à mensagem especificada.`, flags: MessageFlags.Ephemeral });
		}

		// Tell staff what I did
		await interaction.client.staffChannel.send(`${interaction.user} apagou ${count} mensagens no canal ${interaction.channel}.`);
	}
};
