import { MessageFlags, SlashCommandBuilder } from 'discord.js';
import moment from 'moment';

export default {
    data: new SlashCommandBuilder().setName('uptime').setDescription('Mostra há quanto tempo o bot está online'),
    async execute(interaction) {
        const uptime = moment.duration(interaction.client.uptime);
        const days = Math.floor(uptime.asDays());
        const hours = uptime.hours();
        const minutes = uptime.minutes();
        const seconds = uptime.seconds();

        const uptimeString = `${days} dias, ${hours} horas, ${minutes} minutos e ${seconds} segundos`;

        await interaction.reply({ content: `Estou online a ${uptimeString}`, flags: MessageFlags.Ephemeral });
    },
}; 