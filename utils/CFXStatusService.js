import { readFile, writeFileSync } from 'fs';
import { EmbedBuilder } from 'discord.js';

export class CFXStatus {
	static #STATUS_FILE_NAME = 'last-cfx-status.json';

	constructor(data) {
		this.status        = data.status;
		this.description   = data.description;
		this.updatedAt     = data.updatedAt;
		this.incidents     = data.incidents || [];
		this.isOperational = data.isOperational;
	}

	/**
	 * Translate status descriptions to European Portuguese
	 * @param {string} text - Original status text
	 * @returns {string} Translated text
	 */
	static translateStatus(text) {
		return {
			'All Systems Operational': 'Todos os Sistemas Operacionais',
			'Degraded Performance'   : 'Desempenho Degradado',
			'Partial Outage'         : 'Paragem Parcial',
			'Major Outage'           : 'Paragem Significativa',
			'Under Maintenance'      : 'Em Manutenção'
		}[text] || text;
	}

	/**
	 * Check if the status has significantly changed compared to another status
	 * @param {CFXStatus} previousStatus - Previous status to compare
	 * @returns {boolean} Whether the status has changed significantly
	 */
	hasSignificantChange(previousStatus) {
		if (!previousStatus) return true;
		return this.isOperational !== previousStatus.isOperational || this.incidents.length !== previousStatus.incidents.length;
	}

	/**
	 * Generate a formatted status message
	 * @returns {Object} Message with text and embed
	 */
	generateMessage() {
		const reportMessage = {
			text: this.isOperational ? '✅ **CFX.re está totalmente operacional**' : '⚠️ **Possíveis problemas no CFX.re detectados**',
			embed: new EmbedBuilder()
				.setTitle('Estado do CFX.re')
				.setColor(this.isOperational ? 0x00FF00 : 0xFF0000)
				.setDescription(`**Estado Geral**: ${this.description}`)
				.setTimestamp(new Date(this.updatedAt))
				.setURL('https://status.cfx.re')
		};

		// Add incident details if any
		if (this.incidents.length > 0) {
			reportMessage.text += '\n\n**Incidentes Ativos:**\n' + 
				this.incidents.map(incident => `• **${incident.name}**: ${incident.status} (Impacto: ${incident.impact})`).join('\n');

			// Add incidents to embed
			reportMessage.embed.addFields(
				this.incidents.map(incident => ({
					name  : `📢 ${incident.name}`,
					value : `**Estado**: ${incident.status}\n**Impacto**: ${incident.impact}`,
					inline: false
				}))
			);
		}

		return reportMessage;
	}

	/**
	 * Check if there are any active incidents
	 * @returns {boolean} True if there are incidents, false otherwise
	 */
	hasActiveIncidents = () => this.incidents.length > 0;
}

export default class CFXStatusService {
	static #STATUS_FILE_NAME = 'last-cfx-status.json';
	/**
	 * Fetch the current status of CFX.re from their status API
	 * @returns {Promise<CFXStatus|null>} Parsed status data
	 */
	static async fetchStatus() {
		try {
			const response = await fetch('https://status.cfx.re/api/v2/status.json');
			const statusData = await response.json();

			const status = new CFXStatus({
				status: statusData.status.indicator,
				description: CFXStatus.translateStatus(statusData.status.description),
				updatedAt: statusData.page.updated_at,
				incidents: statusData.incidents?.map(incident => ({
					...incident,
					status: CFXStatus.translateStatus(incident.status),
					impact: CFXStatus.translateStatus(incident.impact)
				})) || [],
				isOperational: statusData.status.indicator === 'none'
			});

			writeFileSync(CFXStatusService.#STATUS_FILE_NAME, JSON.stringify(status, null, 2), () => console.error(`Erro ao salvar estado do CFX.re: ${err.message}`));
			return status;
		} catch (error) {
			console.error(`Erro ao verificar estado do CFX.re: ${error.message}`);
			return null;
		}
	}

	
	/**
	 * Read the last saved status from file
	 * @returns {CFXStatus|null} Parsed status data or null if file not found or invalid
	 */
	static async getLastSavedStatus() {
		try {
			const rawData = await readFile(CFXStatusService.#STATUS_FILE_NAME, 'utf8');
			const parsedStatus = JSON.parse(rawData);

			// Validate the status object has required properties - probably overkill but wtv
			if (
				parsedStatus &&
				'status' in parsedStatus &&
				'description' in parsedStatus &&
				'updatedAt' in parsedStatus &&
				'incidents' in parsedStatus &&
				'isOperational' in parsedStatus
			) {
				return new CFXStatus(parsedStatus);
			}

			return null;
		} catch (error) {
			console.error(`Erro ao ler estado do CFX.re: ${error.message}`);
			return null;
		}
	}
}