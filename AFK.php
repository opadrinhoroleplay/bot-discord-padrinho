<?php

use Discord\Parts\User\Member;

class AFK
{
    static function set(Member $member, bool $setAFK, string $reason = NULL): bool
    {
        global $db;

        $isAFK = self::get($member);

        // If we're setting the member as AFK
        if ($setAFK) {
            // If the member is already AFK then update the reason if one was provided, otherwise remove the AFK status
            if ($isAFK) {
                if ($reason) {
                    print("Updated AFK reason for '$member->username'. Reason: '$reason'\n");
                    $reason = $db->connection->escape_string($reason);
                    $db->query("UPDATE discord_afk SET reason = '$reason' WHERE member_id = '$member->id' AND time_unset IS NULL;");

                    return true;
                } else {
                    print("Removed AFK status from '$member->username'.\n");
                    $db->query("UPDATE discord_afk SET time_unset = now() WHERE member_id = '$member->id';");
                    $member->removeRole(config->discord->roles->afk);

                    return true;
                }
            } else { // If the member is not AFK then set him as AFK
                print("Set '$member->username' as AFK. Reason: '$reason'\n");
                if ($reason) { // Just to leave the field as NULL but meh
                    $reason = $db->connection->escape_string($reason);
                    $db->query("INSERT INTO discord_afk (member_id, reason) VALUES ('$member->id', '$reason');");
                } else {
                    $db->query("INSERT INTO discord_afk (member_id) VALUES ('$member->id');");
                }
                $member->addRole(config->discord->roles->afk);

                return true;
            }
        } else { // Removing AFK status
            // If the member is AFK then remove the AFK status
            if ($isAFK) {
                print("Removed AFK status for '$member->username'.\n");
                $db->query("UPDATE discord_afk SET time_unset = now() WHERE member_id = '$member->id';");
                $member->removeRole(config->discord->roles->afk);

                return true;
            }
        }

        return false;
    }

    // Get the AFK status of a Member
    static function get(Member $member): bool|string
    {
        $query = $GLOBALS["db"]->query("SELECT reason FROM discord_afk WHERE member_id = '$member->id' AND time_unset IS NULL;");
        if ($query->num_rows > 0) return $query->fetch_column() ?? true; // If the member is AFK then return the reason or true if there is no reason

        return false;
    }
}