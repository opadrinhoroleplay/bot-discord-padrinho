import Anthropic from '@anthropic-ai/sdk';

export const Models = Object.freeze({
	SONNET: 'claude-3-5-sonnet-20241022',
	HAIKU: 'claude-3-5-haiku-20241022',
	HAIKU3: 'claude-3-haiku-20240307',
	OPUS: 'claude-3-opus-20240229',
});

export default class Claude {
	constructor(apiKey, model = Models.SONNET) {
		this.client = new Anthropic({ apiKey });
		this.model = model;
	}

	async sendPrompt(prompt, temperature = 0.7, max_tokens = 1024, modelOverride) {
		const model = modelOverride || this.model;

		try {
			return await this.client.messages.create({
				model,
				max_tokens,
				temperature,
				messages: [{ role: 'user', content: prompt }],
			});
		} catch (error) {
			console.error('Sending prompt to Claude:', error);
			throw error;
		}
	}
}
