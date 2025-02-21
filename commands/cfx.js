import { SlashCommandBuilder, MessageFlags } from 'discord.js';
import CFXStatusService from '../utils/CFXStatusService.js';

export default {
    data: new SlashCommandBuilder().setName('cfx').setDescription('Verifica o estado do CFX.re (FiveM)'),

    async execute(interaction) {
        await interaction.deferReply();
        
        try {
            const statusData = await CFXStatusService.fetchStatus();

            if (!statusData) throw new Error('Não foi possível obter o estado');

            const statusMessage = statusData.generateMessage();

            await interaction.editReply({ 
                content: statusMessage.text, 
                embeds: [statusMessage.embed], 
                flags: MessageFlags.Ephemeral 
            });
        } catch (error) {
            console.error(`Erro ao verificar estado do CFX.re: ${error.message}`);
            await interaction.editReply({ 
                content: 'Não foi possível verificar o estado do CFX.re.', 
                flags: MessageFlags.Ephemeral
            });
        }
    }
};