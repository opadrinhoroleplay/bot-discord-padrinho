const { GuildMember } = require('discord.js');

class AFKUtils {
    /**
     * Set a member's AFK status
     * @param {GuildMember} member 
     * @param {boolean} status 
     * @param {string} [reason] 
     */
    static async set(member, status, reason = null) {
        const mainChannel = member.guild.channels.cache.get(member.client.config.discord.channels.main);
        const adminChannel = member.guild.channels.cache.get(member.client.config.discord.channels.admin);
        const isAdmin = member.roles.cache.has(member.client.config.discord.roles.admin);

        let message;

        if (status) {
            await member.roles.add(member.client.config.discord.roles.afk);
            message = `**${member.user.username}** is now **AFK**${reason ? `: \`${reason}\`` : ''}`;
        } else {
            await member.roles.remove(member.client.config.discord.roles.afk);
            message = `**${member.user.username}** is no longer **AFK**`;
        }

        await mainChannel.send(message);
        if (isAdmin) await adminChannel.send(message);
    }

    /**
     * Get a member's AFK status and reason
     * @param {GuildMember} member 
     * @returns {boolean|string} Returns true if AFK without reason, string if AFK with reason, false if not AFK
     */
    static get(member) {
        if (!member.roles.cache.has(member.client.config.discord.roles.afk)) return false;

        // In a real implementation, you might want to store and retrieve the reason from a database
        return true;
    }
}

module.exports = AFKUtils; 