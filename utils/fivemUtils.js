const fetch = require('node-fetch');

class FiveMUtils {
    static async status(callback = null) {
        try {
            const response = await fetch('http://fivem.net/status');
            const isOnline = response.status === 200;
            
            if (callback && typeof callback === 'function') callback(isOnline);
            
            return isOnline;
        } catch (error) {
            console.error('Error checking FiveM status:', error);
            if (callback && typeof callback === 'function') callback(false);
            return false;
        }
    }

    static isRoleplayServer(serverInfo) {
        if (!Array.isArray(serverInfo)) return false;
        
        const roleplayKeywords = ['rp', 'roleplay', 'role-play', 'role play'];
        const serverString = serverInfo.join(' ').toLowerCase();
        
        return roleplayKeywords.some(keyword => serverString.includes(keyword));
    }
}

module.exports = FiveMUtils; 