exports.config = {
    discord: {
        guild: process.env.GUILD_ID,
        channels: {
            main: process.env.DISCORD_CHANNEL_MAIN,
            staff: process.env.DISCORD_CHANNEL_STAFF,
            desenvolvimento: process.env.DISCORD_CHANNEL_DEV,
            clickup: process.env.DISCORD_CHANNEL_CLICKUP,
            github: process.env.DISCORD_CHANNEL_GITHUB,
            voice: {
                admin: process.env.DISCORD_VOICE_ADMIN,
                discussion: process.env.DISCORD_VOICE_DISCUSSION,
                lobby: process.env.DISCORD_VOICE_LOBBY
            },
            log: {
                afk: process.env.DISCORD_LOG_AFK,
                ingame: process.env.DISCORD_LOG_INGAME,
                voice: process.env.DISCORD_LOG_VOICE
            }
        },
        roles: {
            admin: process.env.DISCORD_ROLE_ADMIN,
            ingame: process.env.DISCORD_ROLE_INGAME,
            afk: process.env.DISCORD_ROLE_AFK
        },
        users: {
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