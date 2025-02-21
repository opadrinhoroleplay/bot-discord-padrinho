import { Events, ActivityType } from 'discord.js';
import MemberUtils from '../utils/Member.js';
import config from '../config.js';

export default {
    name: Events.PresenceUpdate,
    async execute(oldPresence, newPresence, client) {

        if (newPresence.user.bot) return;

        const member = newPresence.member;

        // Handle status updates
        const oldStatus = client.memberStatus.get(member.id) || member.presence?.status;
        const newStatus = newPresence.status;

        if (oldStatus !== newStatus) {
            if (newStatus === 'idle') {
                // Handle AFK status
                if (member.voice?.channel) {
                    // Optionally handle voice channel changes for AFK members
                }
            } else if (newStatus === 'offline') {
                // console.log('Calling setLastOnline with:', member);
                await MemberUtils.setLastOnline(member);
            }

            client.memberStatus.set(member.id, newStatus);
        }

        // Handle game sessions
        const gameActivity = newPresence.activities.find(activity => activity.type === ActivityType.Playing);

        if (gameActivity) {
            // Check if playing on our server
            if (gameActivity.name === config.server.name || gameActivity.state === config.server.name) {
                // console.log('Calling setInGame(true) with:', member);
                await MemberUtils.setInGame(member, true);
            } else {
                // Check if playing on another roleplay server
                const isRoleplayServer = ['rp', 'roleplay', 'role-play', 'role play'].some(keyword => [gameActivity.name, gameActivity.state].join(' ').toLowerCase().includes(keyword));
                if (isRoleplayServer) {
                    // Handle member playing on another RP server
                    // You might want to log this or take action
                }
            }
        } else {
            // Not playing any game
            // console.log('Calling setInGame(false) with:', member);
            await MemberUtils.setInGame(member, false);
        }
    },
}; 