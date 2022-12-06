<?php
class Admin {
    // Get a list of all admins, ordered by last_active
    static function GetAdmins() {
        $query = $GLOBALS["db"]->query("SELECT id, admin_level, username, last_online, last_active FROM discord_members WHERE admin_level IS NOT NULL AND active = 1 ORDER BY last_active DESC;");
        $admins = $query->fetch_all(MYSQLI_ASSOC);
        
        return $admins;
    }
}