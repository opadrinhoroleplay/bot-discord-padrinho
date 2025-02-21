import { REST, Routes } from 'discord.js';
import { readdir } from 'fs/promises';
import 'dotenv/config';

const CLIENT_ID = process.env.CLIENT_ID;
const GUILD_ID = process.env.GUILD_ID;

const commandFiles = (await readdir('commands')).filter(file => file.endsWith('.js'));
const commands = [];

for (const file of commandFiles) {
    const command = await import(`./commands/${file}`);
    if (command.default?.data) commands.push(command.default.data.toJSON());
}

const rest = new REST().setToken(process.env.DISCORD_TOKEN);

try {
    console.log(`Started refreshing ${commands.length} application (/) commands.`);

    const existingCommands = await rest.get(Routes.applicationGuildCommands(CLIENT_ID, GUILD_ID));

    // Delete commands that exist on Discord but aren't in our local commands folder
    for (const command of existingCommands) {
        if (commands.some(cmd => cmd.name === command.name)) continue;
		
        try {
            await rest.delete(Routes.applicationGuildCommand(CLIENT_ID, GUILD_ID, command.id));
            console.log(`Deleted command with ID: ${command.id} and name: ${command.name}`);
        } catch (error) {
            if (error.code === 10063) {
                console.log(`Command ${command.name} already deleted or doesn't exist`);
            } else {
                console.error(`Failed to delete command ${command.name}: ${error.message}`);
            }
        }
    }

    const data = await rest.put(Routes.applicationGuildCommands(CLIENT_ID, GUILD_ID), { body: commands });

    console.log(`Successfully reloaded ${data.length} application (/) commands.`);
} catch (error) {
    console.error(error);
} 