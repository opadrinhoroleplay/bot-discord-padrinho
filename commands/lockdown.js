const { SlashCommandBuilder, PermissionFlagsBits, EmbedBuilder, PermissionsBitField } = require('discord.js');

module.exports = {
	data: new SlashCommandBuilder()
		.setName('lockdown')
		.setDescription('Ativa o modo de bloqueio, removendo permissões perigosas do cargo Staff.')
		.setDefaultMemberPermissions(PermissionFlagsBits.Administrator) // Require Admin perms by default
		.setDMPermission(false), // Disable in DMs
	async execute(interaction) {
		// Initialize state if it doesn't exist (fallback, should be done at startup)
		if (typeof interaction.client.isLockedDown === 'undefined') {
			interaction.client.isLockedDown = false;
			console.warn('[WARN] client.isLockedDown was not initialized. Initializing to false.');
		}
		if (typeof interaction.client.originalStaffPermissions === 'undefined') {
			interaction.client.originalStaffPermissions = null;
			console.warn('[WARN] client.originalStaffPermissions was not initialized. Initializing to null.');
		}

		if (interaction.client.isLockedDown) return interaction.reply({ content: '❌ O modo de bloqueio já está ativo.', ephemeral: true });

		const staffRoleId = process.env.DISCORD_ROLE_STAFF;
		if (!staffRoleId) return interaction.reply({ content: '❌ ID do cargo Staff não configurado no ambiente.', ephemeral: true });

		await interaction.deferReply({ ephemeral: true }); // Defer while we modify roles

		try {
			// Check bot permissions
			const botMember = await interaction.guild.members.fetch(interaction.client.user.id);
			if (!botMember.permissions.has(PermissionFlagsBits.ManageRoles)) return interaction.editReply({ content: '❌ Eu não tenho a permissão `Manage Roles` para modificar o cargo Staff.' });

			// Fetch the staff role
			const staffRole = await interaction.guild.roles.fetch(staffRoleId);
			if (!staffRole) return interaction.editReply({ content: `❌ Cargo Staff com ID \`${staffRoleId}\` não encontrado.` });

			// Check role hierarchy
			if (botMember.roles.highest.position <= staffRole.position) return interaction.editReply({ content: '❌ Meu cargo mais alto precisa estar acima do cargo Staff na hierarquia para que eu possa modificá-lo.' });

			// Store original permissions
			interaction.client.originalStaffPermissions = staffRole.permissions.bitfield;

			// Calculate new permissions (remove dangerous ones)
			const currentPermissions = new PermissionsBitField(staffRole.permissions.bitfield);
			const newPermissions = currentPermissions.remove(PermissionsBitField.resolve([
				PermissionFlagsBits.Administrator,
				PermissionFlagsBits.KickMembers,
				PermissionFlagsBits.BanMembers,
				PermissionFlagsBits.ManageChannels,
				PermissionFlagsBits.ManageGuild,
				PermissionFlagsBits.ManageMessages,
				PermissionFlagsBits.ManageRoles,
				PermissionFlagsBits.MentionEveryone,
				PermissionFlagsBits.ModerateMembers,
			]));

			// Apply new permissions
			await staffRole.setPermissions(newPermissions, `Bloqueio ativado por ${interaction.user.tag}`);

			// --- BEGIN CHANNEL OVERRIDE LOGIC ---
			// Iterate through guild channels and deny dangerous permissions for the staff role
			const dangerousPermissionsToDeny = { // Permissions to explicitly deny in channel overrides
				Administrator: false,
				KickMembers: false,
				BanMembers: false,
				ManageChannels: false,
				ManageGuild: false,
				ManageMessages: false,
				ManageRoles: false,
				MentionEveryone: false,
				ModerateMembers: false,
			};

			const channels = await interaction.guild.channels.fetch();
			let channelErrors = 0;
			const botMemberForPermCheck = interaction.guild.members.cache.get(interaction.client.user.id) || await interaction.guild.members.fetch(interaction.client.user.id); // Re-use fetched member if possible

			console.log(`[INFO] Attempting to deny permissions on applicable channels (${channels.size} total) for role ${staffRole.name} (${staffRoleId})...`);

			for (const [, channel] of channels) {
				// Check channel types that support permission overwrites
				if (channel.permissionOverwrites) {
					try {
						// Check if the bot has MANAGE_ROLES permission in this specific channel
						const botPermissionsInChannel = channel.permissionsFor(botMemberForPermCheck);
						if (botPermissionsInChannel?.has(PermissionFlagsBits.ManageRoles)) {
							await channel.permissionOverwrites.edit(
								staffRoleId,
								dangerousPermissionsToDeny,
								{ reason: `Bloqueio ativado por ${interaction.user.tag}` }
							);
						} else {
							// Log if bot lacks specific permission, but don't count as error unless edit fails
							console.warn(`[WARN] Bot lacks ManageRoles permission in channel ${channel.name} (${channel.id}). Skipping override update.`);
						}
					} catch (channelError) {
						console.error(`[ERROR] Failed to update permissions for channel ${channel.name} (${channel.id}):`, channelError);
						channelErrors++; // Count actual errors during the edit attempt
					}
				}
				// Optionally log channels skipped due to type:
				// else { console.log(`[DEBUG] Skipping channel ${channel.name} (${channel.id}) as it does not support permission overwrites (Type: ${channel.type}).`); }
			}
			console.log(`[INFO] Channel permission override update complete. Errors: ${channelErrors}.`);
			// --- END CHANNEL OVERRIDE LOGIC ---

			// Set lockdown state
			interaction.client.isLockedDown = true;
			console.log(`[INFO] Lockdown mode activated by ${interaction.user.tag}. Staff role (${staffRole.name}) permissions restricted globally and in channel overrides.`);
			if (channelErrors > 0) console.warn(`[WARN] Failed to update permissions for ${channelErrors} channels during lockdown.`);

			const embed = new EmbedBuilder()
				.setColor('#FF0000') // Red color for alert
				.setTitle('🚨 Modo de Bloqueio Ativado 🚨')
				.setDescription(`O modo de bloqueio foi ativado. As permissões perigosas do cargo **${staffRole.name}** foram removidas do cargo e explicitamente negadas em todas as permissões de canal aplicáveis. Use \`/unlock\` para desativar e restaurar as permissões do *cargo*. As permissões de canal precisarão ser ajustadas manualmente, se necessário.`)
				.addFields({ name: 'Ativado por', value: interaction.user.tag })
				.setTimestamp();

			if (channelErrors > 0) embed.addFields({ name: 'Aviso de Canal', value: `Não foi possível atualizar as permissões para ${channelErrors} canais. Verifique os logs para detalhes.` });

			// Announce in the channel (ephemeral reply was already sent, send a public follow-up)
			await interaction.editReply({ content: 'Modo de bloqueio ativado com sucesso!', embeds: [] }); // Clear ephemeral content
			await interaction.followUp({ embeds: [embed], ephemeral: false });

		} catch (error) {
			console.error('[ERROR] Failed to activate lockdown:', error);
			// Attempt to revert state if something failed mid-process
			interaction.client.isLockedDown = false;
			interaction.client.originalStaffPermissions = null; // Clear potentially stored permissions
			// We don't try to restore permissions here as the error might be related to that
			return interaction.editReply({ content: '❌ Ocorreu um erro ao tentar ativar o modo de bloqueio. Verifique os logs.' });
		}
	},
}; 