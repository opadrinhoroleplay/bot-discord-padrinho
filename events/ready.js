const { Events, ActivityType } = require('discord.js');
const FiveMUtils = require('../utils/fivemUtils');

module.exports = {
    name: Events.ClientReady,
    once: true,
    async execute(client) {
        console.log(`Bot is ready! Logged in as ${client.user.tag}`);

        // Set bot activity
        client.user.setActivity('vocês seus cabrões!', { type: ActivityType.Watching });

        // Cache important channels
        client.mainChannel = await client.channels.fetch(client.config.discord.channels.main);
        client.adminChannel = await client.channels.fetch(client.config.discord.channels.admin);
        client.logChannels = {
            afk: await client.channels.fetch(client.config.discord.channels.log.afk),
            ingame: await client.channels.fetch(client.config.discord.channels.log.ingame),
            voice: await client.channels.fetch(client.config.discord.channels.log.voice)
        };

        // Cache guild invites
        const guild = await client.guilds.fetch(client.config.discord.guild);
        const invites = await guild.invites.fetch();
        
        for (const [code, invite] of invites) {
            if (invite.inviterId === client.user.id) {
                console.log(`Invite '${code}' has ${invite.uses} uses`);
                client.invitesUses.set(code, invite.uses);

                // Check invite uses against database
                const [rows] = await client.db.query('SELECT COUNT(*) as count FROM invites_used WHERE code = ?',[code]);
                const dbInviteUses = rows[0].count;

                if (dbInviteUses < invite.uses) console.log(`Invite '${code}' has ${invite.uses} uses, but database shows ${dbInviteUses}`);
            }
        }

        // Set up hourly tasks
        setInterval(async () => {
            const hour = new Date().getHours();
            
            // Check FiveM status every hour
            const status = await FiveMUtils.status();
            if (status !== null) client.mainChannel.send(status ? 'O FiveM está de volta! :smiley:' : 'O FiveM está com algo problema! :weary:' );

            // Handle specific hour tasks
            switch(hour) {
                case 0:
                    // Reset daily counters
                    // Add your daily summary logic here
                    break;
                case 8:
                    // Morning rollcall
                    // Add your rollcall logic here
                    break;
            }
        }, 3600000); // Run every hour
    },
}; 