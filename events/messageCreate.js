const { Events } = require('discord.js');
const MemberUtils = require('../utils/memberUtils');
const BadWords = require('../utils/badWords');

module.exports = {
    name: Events.MessageCreate,
    async execute(message, client) {
        // Ignore bot messages
        if (message.author.bot) return;

        // Track message activity
        const counterTypes = {
            [client.config.discord.channels.desenvolvimento]: 'dev_messages',
            [client.config.discord.channels.admin]: 'admin_messages',
            [client.config.discord.channels.clickup]: 'clickup',
            [client.config.discord.channels.github]: 'github'
        };

        const counterType = counterTypes[message.channel.id];
        if (counterType) await client.db.query('UPDATE discord_counters SET count = count + 1 WHERE type = ? AND day = DATE(NOW())', [counterType]);

        // Check for bad words
        if (BadWords.scan(message)) {
            await message.delete();
            await client.adminChannel.send(`Eliminei uma mensagem de '${message.author.username}' no '${message.channel.name}' por utilizar uma palavra banida: - \`${message.content}\``);
        }

        // Update member's last active time
        await MemberUtils.setLastActive(message.member);
    },
}; 