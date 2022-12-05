<?php
use Discord\Parts\User\Member as DiscordMember;

class Member {
    static function Exists(DiscordMember $member) {
        $query = $GLOBALS["db"]->query("SELECT NULL FROM discord_members WHERE id = '{$member->id}';");

        return $query->num_rows > 0;
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
}