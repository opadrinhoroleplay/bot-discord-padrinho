const { Events, ActivityType } = require('discord.js');
const MemberUtils = require('../utils/memberUtils');
const FiveMUtils = require('../utils/fivemUtils');

module.exports = {
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
                await MemberUtils.setLastOnline(member);
            }

            client.memberStatus.set(member.id, newStatus);
        }

        // Handle game sessions
        const gameActivity = newPresence.activities.find(activity => activity.type === ActivityType.Playing);

        if (gameActivity) {
            // Check if playing on our server
            if (gameActivity.name === client.config.server.name || 
                gameActivity.state === client.config.server.name) {
                await MemberUtils.setInGame(member, true);
            } else {
                // Check if playing on another roleplay server
                const isRoleplayServer = FiveMUtils.isRoleplayServer([ gameActivity.name, gameActivity.state ]);
                if (isRoleplayServer) {
                    // Handle member playing on another RP server
                    // You might want to log this or take action
                }
            }
        } else {
            // Not playing any game
            await MemberUtils.setInGame(member, false);
        }
    },
}; 