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
}