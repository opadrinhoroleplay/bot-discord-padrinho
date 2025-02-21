import { GuildMember, PermissionFlagsBits, ChannelType } from 'discord.js';
import config from '../config.js';

export default class Member {
    /**
     * Check if a member is the owner
     * @param {string} id 
     * @returns {boolean}
     */
    static isOwner(id) {
        return id === config.discord.user.owner;
    }

    /**
     * Check if a member is an admin
     * @param {GuildMember} member 
     * @returns {boolean}
     */
    static isAdmin(member) {
        return member.roles.cache.has(config.discord.role.admin);
    }

    /**
     * Check if a member is in game
     * @param {GuildMember} member 
     * @returns {boolean}
     */
    static isInGame(member) {
        return member.roles.cache.has(config.discord.role.ingame);
    }

    /**
     * Set a member's in-game status
     * @param {GuildMember} member 
     * @param {boolean} status 
     */
    static async setInGame(member, status) {
        const isInGame = this.isInGame(member);
        if (isInGame === status) return false;

        const staffChannel = member.guild.channels.cache.get(config.discord.channel.admin);
        
        if (status) {
            await member.roles.add(config.discord.role.ingame, 'Entered the server');
            if (member.voice.channel && !this.isAdmin(member)) await member.voice.setChannel(config.discord.channel.voice.lobby, 'Entered the server');
        } else {
            await member.roles.remove(config.discord.role.ingame, 'Left the server');
            if (member.voice.channel && !this.isAdmin(member)) await member.voice.setChannel(config.discord.channel.voice.discussion, 'Left the server');
        }

        await staffChannel.send(`**${member.user.username}** ${status ? 'entered' : 'left'} the server.`);
        return true;
    }

    /**
     * Get a member's voice channel
     * @param {GuildMember} member 
     * @returns {GuildChannel|null}
     */
    static async getPrivateVoiceChannel(member) {
        // Fetch channels if not already in cache
        await member.guild.channels.fetch();

        // Use .cache to get all voice channels in the voice category
        const voiceChannels = member.guild.channels.cache.filter(channel => 
            channel.type === ChannelType.GuildVoice && 
            channel.parentId === config.discord.category.voice &&
            channel.id !== config.discord.channel.voice.lobby
        );

        // Find the channel where the member has PrioritySpeaker permissions
        for (const [channelId, channel] of voiceChannels) {
            const memberPermission = channel.permissionOverwrites.cache.find(
                overwrite => 
                    overwrite.id === member.id && 
                    overwrite.allow.has(PermissionFlagsBits.PrioritySpeaker)
            );

            if (memberPermission) return channel;
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