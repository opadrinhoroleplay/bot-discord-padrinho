import { Events, ActivityType } from 'discord.js';
import config from '../config.js';

export default {
    name: Events.ClientReady,
    once: true,
    async execute(client) {
        console.log(`Logged in as ${client.user.tag}`);

        // Set bot activity
        client.user.setActivity('vocês seus cabrões!', { type: ActivityType.Watching });

        // Cache important channels
        client.mainChannel  = await client.channels.fetch(config.discord.channel.main);
        client.staffChannel = await client.channels.fetch(config.discord.channel.staff);
        client.logChannels  = {
            ingame: await client.channels.fetch(config.discord.channel.log.ingame),
            voice : await client.channels.fetch(config.discord.channel.log.voice)
        };
    }
}; 