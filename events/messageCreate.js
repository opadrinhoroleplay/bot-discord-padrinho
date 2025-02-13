const { Events } = require('discord.js');
const MemberUtils = require('../utils/memberUtils');
const AFKUtils = require('../utils/afkUtils');
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

        // Set member as not AFK if they send a message
        if (AFKUtils.get(message.member)) await AFKUtils.set(message.member, false);

        // Check for mentions of AFK members
        const mentions = message.mentions.members;
        if (mentions.size > 0) {
            mentions.forEach(async (mentionedMember) => {
                if (!mentionedMember || !mentionedMember.roles.cache.has(client.config.discord.roles.afk)) return;

                const afkStatus = AFKUtils.get(mentionedMember);
                if (afkStatus) await message.channel.send(`O membro **${mentionedMember.user.username}** está AFK. **Razão**: \`${afkStatus}\``);
            });
        }
    },
}; 