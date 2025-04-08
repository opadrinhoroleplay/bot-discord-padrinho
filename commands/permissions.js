import { SlashCommandBuilder, PermissionFlagsBits, MessageFlags, EmbedBuilder, ChannelType } from 'discord.js';
import config from '../config.js';

export default {
    data: new SlashCommandBuilder()
        .setName('permissoes')
        .setDescription('Mostra as permissões dos cargos de STAFF e ADMIN')
        .setDefaultMemberPermissions(PermissionFlagsBits.Administrator),
    
    async execute(interaction) {
        await interaction.deferReply();
        
        const guild = interaction.guild;
        
        // Get STAFF and ADMIN roles from config
        const staffRoleId = config.discord.role.staff;
        const adminRoleId = config.discord.role.admin;
        
        const staffRole = guild.roles.cache.get(staffRoleId);
        const adminRole = guild.roles.cache.get(adminRoleId);
        
        if (!staffRole || !adminRole) {
            return interaction.editReply({ 
                content: 'Não foi possível encontrar os cargos de STAFF ou ADMIN. Verifique as configurações.', 
                flags: MessageFlags.Ephemeral 
            });
        }
        
        // Create embeds for each role
        const staffEmbed = new EmbedBuilder()
            .setTitle(`Permissões do cargo ${staffRole.name}`)
            .setColor(staffRole.color)
            .setDescription('Lista de permissões e canais acessíveis');
            
        const adminEmbed = new EmbedBuilder()
            .setTitle(`Permissões do cargo ${adminRole.name}`)
            .setColor(adminRole.color)
            .setDescription('Lista de permissões e canais acessíveis');
        
        // Get permissions
        const staffPerms = formatPermissions(staffRole.permissions);
        const adminPerms = formatPermissions(adminRole.permissions);
        
        // Add permissions to embeds
        staffEmbed.addFields({ 
            name: '🛡️ Permissões Gerais', 
            value: staffPerms || '❌ Sem permissões relevantes',
            inline: false
        });
        
        adminEmbed.addFields({ 
            name: '🛡️ Permissões Gerais', 
            value: adminPerms || '❌ Sem permissões relevantes',
            inline: false
        });
        
        // Get all channels and categories
        const channels = guild.channels.cache;
        
        // First get all categories
        const categories = channels
            .filter(channel => channel.type === ChannelType.GuildCategory)
            .sort((a, b) => a.position - b.position);
            
        // Create structure to store channels by category
        const staffCategoryChannels = {};
        const adminCategoryChannels = {};
        
        // Initialize with categories
        for (const [categoryId, category] of categories) {
            if (category.permissionsFor(staffRoleId).has(PermissionFlagsBits.ViewChannel))
                staffCategoryChannels[categoryId] = { name: category.name, channels: [] };
                
            if (category.permissionsFor(adminRoleId).has(PermissionFlagsBits.ViewChannel))
                adminCategoryChannels[categoryId] = { name: category.name, channels: [] };
        }
        
        // Add "No Category" entry
        staffCategoryChannels["noCategory"] = { name: "Sem Categoria", channels: [] };
        adminCategoryChannels["noCategory"] = { name: "Sem Categoria", channels: [] };
        
        // Now sort all non-category channels by their category
        for (const [channelId, channel] of channels) {
            // Skip categories as we already processed them
            if (channel.type === ChannelType.GuildCategory) continue;
            if (!channel.permissionsFor) continue;
            
            const categoryId = channel.parentId || "noCategory";
            
            // Add channel type indicator
            let channelIcon = "";
            if (channel.type === ChannelType.GuildText) channelIcon = "💬";
            else if (channel.type === ChannelType.GuildVoice) channelIcon = "🔊";
            else if (channel.type === ChannelType.GuildAnnouncement) channelIcon = "📢";
            else if (channel.type === ChannelType.GuildForum) channelIcon = "📌";
            else if (channel.type === ChannelType.PublicThread || channel.type === ChannelType.PrivateThread) channelIcon = "🧵";
            else channelIcon = "📄";
            
            const formattedChannelName = `${channelIcon} ${channel.name}`;
            
            if (channel.permissionsFor(staffRoleId).has(PermissionFlagsBits.ViewChannel)) {
                // Check if the category exists in our structure - the category itself might not be visible
                if (!staffCategoryChannels[categoryId])
                    staffCategoryChannels[categoryId] = { name: channel.parent?.name || "Sem Categoria", channels: [] };
                
                staffCategoryChannels[categoryId].channels.push(formattedChannelName);
            }
                
            if (channel.permissionsFor(adminRoleId).has(PermissionFlagsBits.ViewChannel)) {
                if (!adminCategoryChannels[categoryId])
                    adminCategoryChannels[categoryId] = { name: channel.parent?.name || "Sem Categoria", channels: [] };
                
                adminCategoryChannels[categoryId].channels.push(formattedChannelName);
            }
        }
        
        // Group categories for better display (2 columns)
        const staffCategories = Object.values(staffCategoryChannels).filter(cat => cat.channels.length > 0);
        const adminCategories = Object.values(adminCategoryChannels).filter(cat => cat.channels.length > 0);
        
        // Add channels section header
        staffEmbed.addFields({ name: '\u200B', value: '**📂 Canais Acessíveis**', inline: false });
        adminEmbed.addFields({ name: '\u200B', value: '**📂 Canais Acessíveis**', inline: false });
        
        // Add message if no channels
        if (staffCategories.length === 0) {
            staffEmbed.addFields({ 
                name: 'Sem permissões para canais específicos', 
                value: 'Este cargo não tem permissões para ver nenhum canal',
                inline: false
            });
        }
        
        if (adminCategories.length === 0) {
            adminEmbed.addFields({ 
                name: 'Sem permissões para canais específicos', 
                value: 'Este cargo não tem permissões para ver nenhum canal',
                inline: false
            });
        }
        
        // Add channels by category
        for (let i = 0; i < staffCategories.length; i++) {
            const category = staffCategories[i];
            
            staffEmbed.addFields({ 
                name: `📁 ${category.name}`, 
                value: formatChannelList(category.channels),
                inline: true
            });
            
            // Add a blank field for every 2 categories to create 2 columns
            if (i % 2 === 0 && i < staffCategories.length - 1)
                staffEmbed.addFields({ name: '\u200B', value: '\u200B', inline: true });
        }
        
        for (let i = 0; i < adminCategories.length; i++) {
            const category = adminCategories[i];
            
            adminEmbed.addFields({ 
                name: `📁 ${category.name}`, 
                value: formatChannelList(category.channels),
                inline: true
            });
            
            // Add a blank field for every 2 categories to create 2 columns
            if (i % 2 === 0 && i < adminCategories.length - 1)
                adminEmbed.addFields({ name: '\u200B', value: '\u200B', inline: true });
        }
        
        await interaction.editReply({ 
            embeds: [staffEmbed, adminEmbed]
        });
    },
};

// Helper function to format permissions in a readable way
function formatPermissions(permissions) {
    const permissionStrings = {
        Administrator: 'Administrador',
        ManageGuild: 'Gerir Servidor',
        ManageRoles: 'Gerir Cargos',
        ManageChannels: 'Gerir Canais',
        KickMembers: 'Expulsar Membros',
        BanMembers: 'Banir Membros',
        ModerateMembers: 'Moderar Membros',
        ManageMessages: 'Gerir Mensagens',
        MentionEveryone: 'Mencionar @everyone',
        ViewAuditLog: 'Ver Registro de Auditoria',
        ManageWebhooks: 'Gerir Webhooks',
        ManageEmojisAndStickers: 'Gerir Emojis e Stickers',
        ManageEvents: 'Gerir Eventos'
    };
    
    const permArray = [];
    
    for (const [perm, translated] of Object.entries(permissionStrings)) {
        if (permissions.has(PermissionFlagsBits[perm]))
            permArray.push(`✅ ${translated}`);
    }
    
    return permArray.length ? permArray.join('\n') : null;
}

// Helper function to format channel list
function formatChannelList(channels) {
    if (channels.length === 0) return 'Nenhum canal acessível';
    
    // Format nicely with line breaks
    return channels.join('\n');
} 