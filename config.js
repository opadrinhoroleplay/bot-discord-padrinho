export default {
    discord: {
        guild: process.env.GUILD_ID,
        category: {
            voice: process.env.DISCORD_CATEGORY_VOICE
        },
        channel: {
            main: process.env.DISCORD_CHANNEL_MAIN,
            tickets: process.env.DISCORD_CHANNEL_TICKETS,
            staff: process.env.DISCORD_CHANNEL_STAFF,
            dev: process.env.DISCORD_CHANNEL_DEV,
            clickup: process.env.DISCORD_CHANNEL_CLICKUP,
            changelog: process.env.DISCORD_CHANNEL_CHANGELOG,
            voice: {
                lobby: process.env.DISCORD_VOICE_LOBBY,
                staff: process.env.DISCORD_VOICE_STAFF
            },
            log: {
                afk: process.env.DISCORD_LOG_AFK,
                ingame: process.env.DISCORD_LOG_INGAME,
                voice: process.env.DISCORD_LOG_VOICE
            }
        },
        role: {
            staff: process.env.DISCORD_ROLE_STAFF,
            ingame: process.env.DISCORD_ROLE_INGAME,
        },
        user: {
            owner: process.env.DISCORD_USER_OWNER,
            viruxe: process.env.DISCORD_USER_VIRUXE
        }
    },
    database: {
        host: process.env.DB_HOST,
        user: process.env.DB_USER,
        password: process.env.DB_PASSWORD,
        database: process.env.DB_NAME
    },
    server: {
        name: process.env.SERVER_NAME
    }
};