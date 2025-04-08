const { SlashCommandBuilder, PermissionFlagsBits, EmbedBuilder, PermissionsBitField } = require('discord.js');

module.exports = {
	data: new SlashCommandBuilder()
		.setName('unlock')
		.setDescription('Desativa o modo de bloqueio, restaurando as permissões originais do cargo Staff.')
		.setDefaultMemberPermissions(PermissionFlagsBits.Administrator) // Require Admin perms by default
		.setDMPermission(false), // Disable in DMs
	async execute(interaction) {
		// Check if lockdown state exists and is active
		if (typeof interaction.client.isLockedDown === 'undefined' || !interaction.client.isLockedDown) return interaction.reply({ content: 'ℹ️ O modo de bloqueio não está ativo.', ephemeral: true });

		// Check if original permissions were stored
		if (typeof interaction.client.originalStaffPermissions === 'undefined' || interaction.client.originalStaffPermissions === null) {
			console.error('[ERROR] Unlock attempted but originalStaffPermissions is null or undefined.');
			return interaction.reply({ content: '❌ Não foi possível encontrar as permissões originais para restaurar. O estado pode estar inconsistente.', ephemeral: true });
		}

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

			// Restore original permissions
			const originalPermissions = new PermissionsBitField(interaction.client.originalStaffPermissions);
			await staffRole.setPermissions(originalPermissions, `Bloqueio desativado por ${interaction.user.tag}`);

			// Reset lockdown state
			interaction.client.isLockedDown = false;
			interaction.client.originalStaffPermissions = null;
			console.log(`[INFO] Lockdown mode deactivated by ${interaction.user.tag}. Staff role (${staffRole.name}) permissions restored.`);

			const embed = new EmbedBuilder()
				.setColor('#00FF00') // Green color for success
				.setTitle('✅ Modo de Bloqueio Desativado ✅')
				.setDescription(`O modo de bloqueio foi desativado. As permissões originais do cargo **${staffRole.name}** foram restauradas.`)
				.addFields({ name: 'Desativado por', value: interaction.user.tag })
				.setTimestamp();

			// Announce in the channel (ephemeral reply was already sent, send a public follow-up)
			await interaction.editReply({ content: 'Modo de bloqueio desativado com sucesso!', embeds: [] }); // Clear ephemeral content
			await interaction.followUp({ embeds: [embed], ephemeral: false });
		} catch (error) {
			console.error('[ERROR] Failed to deactivate lockdown:', error);
			// Avoid resetting state if the permission restore failed, as it's inconsistent
			return interaction.editReply({ content: '❌ Ocorreu um erro ao tentar desativar o modo de bloqueio e restaurar as permissões. Verifique os logs.' });
		}
	},
}; 