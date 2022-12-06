<?php
use Discord\Parts\User\Member as DiscordMember;

enum MemberStatus: string {
    case Online       = "online";
    case Idle         = "idle";
    case DoNotDisturb = "dnd";
    case Invisible    = "invisible";
    case Offline      = "offline";
}

enum MemberActiveStatus: string {
    case Inactive = "inactive";
    case Active   = "active";
}

class Member {
    static function Exists(DiscordMember $member): MemberActiveStatus|null {
        $query = $GLOBALS["db"]->query("SELECT active FROM discord_members WHERE id = '{$member->id}';");
        if ($query->num_rows) return $query->fetch_column() === "1" ? MemberActiveStatus::Active : MemberActiveStatus::Inactive;

        return null;
    }

    static function Create(DiscordMember $member) {
        $query = $GLOBALS["db"]->query("INSERT INTO discord_members (id, username) VALUES ('{$member->id}', '{$member->username}')");
        if($query) print("[MEMBER] Created member {$member->username} ({$member->id})\n"); else print("[MEMBER] Failed to create member {$member->username} ({$member->id})\n");

        return $query;
    }

    static function SetLastOnline(DiscordMember $member) {
        $member_exists = self::Exists($member);

        // Create member if it doesn't exist
        if(!$member_exists) $member_created = self::Create($member);

        // If the member exists or was created, update the last online time
        if($member_exists || $member_created) {
            $query = $GLOBALS["db"]->query("UPDATE discord_members SET last_online = NOW() WHERE id = '{$member->id}';");
            if($query) print("[MEMBER] Updated last online for {$member->username} ({$member->id})\n"); else print("[MEMBER] Failed to update last online for {$member->username} ({$member->id})\n");

            return $query;
        }
    }

    static function SetLastActive(DiscordMember $member) {
        $member_exists = self::Exists($member);

        // Create member if it doesn't exist
        if(!$member_exists) $member_created = self::Create($member);

        // If the member exists or was created, update the last active time
        if($member_exists || $member_created) {
            $query = $GLOBALS["db"]->query("UPDATE discord_members SET last_active = NOW() WHERE id = '{$member->id}';");
            if($query) print("[MEMBER] Updated last active for {$member->username} ({$member->id})\n"); else print("[MEMBER] Failed to update last active for {$member->username} ({$member->id})\n");

            return $query;
        }
    }

    // Check members in the database are still in the guild
    // If not, set them their 'active' column to 0
    static function Purge() {
        $query = $GLOBALS["db"]->query("SELECT id FROM discord_members WHERE active = 1;");
        while($row = $query->fetch_assoc()) {
            $member = $GLOBALS["guild"]->members->get("id", $row["id"]);
            if(!$member) {
                $query = $GLOBALS["db"]->query("UPDATE discord_members SET active = 0 WHERE id = '{$row["id"]}';");
                if($query) print("[MEMBER] Set {$row["id"]} to inactive\n"); else print("[MEMBER] Failed to set {$row["id"]} to inactive\n");
            }
        }
        // Update 'last_purge' in discord_maintenance
        $query = $GLOBALS["db"]->query("UPDATE discord_maintenance SET last_purge = NOW();");
        if($query) print("[MAINTENANCE] Updated last purge time\n"); else print("[MAINTENANCE] Failed to update last purge time\n");
    }
}