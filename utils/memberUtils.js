const { GuildMember } = require('discord.js');

class MemberUtils {
    /**
     * Check if a member is an admin
     * @param {GuildMember} member 
     * @returns {boolean}
     */
    static isAdmin(member) {
        return member.roles.cache.has(member.client.config.discord.roles.admin);
    }

    /**
     * Check if a member is in game
     * @param {GuildMember} member 
     * @returns {boolean}
     */
    static isInGame(member) {
        return member.roles.cache.has(member.client.config.discord.roles.ingame);
    }

    /**
     * Set a member's in-game status
     * @param {GuildMember} member 
     * @param {boolean} status 
     */
    static async setInGame(member, status) {
        const isInGame = this.isInGame(member);
        if (isInGame === status) return false;

        const adminChannel = member.guild.channels.cache.get(member.client.config.discord.channels.admin);
        
        if (status) {
            await member.roles.add(member.client.config.discord.roles.ingame, 'Entered the server');
            if (member.voice.channel && !this.isAdmin(member)) await member.voice.setChannel(member.client.config.discord.channels.voice.lobby, 'Entered the server');
        } else {
            await member.roles.remove(member.client.config.discord.roles.ingame, 'Left the server');
            if (member.voice.channel && !this.isAdmin(member)) await member.voice.setChannel(member.client.config.discord.channels.voice.discussion, 'Left the server');
        }

        await adminChannel.send(`**${member.user.username}** ${status ? 'entered' : 'left'} the server.`);
        return true;
    }

    /**
     * Get a member's voice channel
     * @param {GuildMember} member 
     * @returns {string|null}
     */
    static getMemberVoiceChannel(member) {
        const voiceCategory = '1030787112628400198';
        const lobbyChannel = '1019237971217612840';

        for (const [channelId, channel] of member.guild.channels.cache) {
            if (channel.parentId !== voiceCategory) continue;
            if (channel.id === lobbyChannel) continue;

            const memberPerms = channel.permissionOverwrites.cache.get(member.id);
            if (memberPerms && memberPerms.type === 1) return channel.id;
        }

        return null;
    }

    /**
     * Update member's last active timestamp
     * @param {GuildMember} member 
     */
    static async setLastActive(member) {
        await member.client.db.query('UPDATE discord_members SET last_active = NOW() WHERE id = ?', [member.id]);
    }

    /**
     * Update member's last online timestamp
     * @param {GuildMember} member 
     */
    static async setLastOnline(member) {
        await member.client.db.query('UPDATE discord_members SET last_online = NOW() WHERE id = ?', [member.id]);
    }

    /**
     * Check if member exists in database
     * @param {GuildMember} member 
     * @returns {Promise<boolean>}
     */
    static async exists(member) {
        const [rows] = await member.client.db.query('SELECT COUNT(*) as count FROM discord_members WHERE id = ?', [member.id]);
        return rows[0].count > 0;
    }

    /**
     * Create member in database
     * @param {GuildMember} member 
     */
    static async create(member) {
        await member.client.db.query('INSERT INTO discord_members (id, username) VALUES (?, ?)', [member.id, member.user.username]);
    }
}

module.exports = MemberUtils; 