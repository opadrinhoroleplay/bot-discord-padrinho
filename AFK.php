<?php

use Discord\Parts\User\Member;

class AFKHandler
{
    private \mysqli $db;

    function __construct(\mysqli &$database_handle)
    {
        $this->db = $database_handle;
    }

    // Check if the database connection is still alive or not and reconnect if needed
    private function checkConnection()
    {
        if (!$this->db->ping()) {
            global $config;

            $this->db = new \mysqli(
                "p:{$config->database->host}",
                $config->database->user,
                $config->database->pass,
                $config->database->database
            );

            if ($this->db->connect_errno) {
                throw new \Exception("Failed to connect to MySQL: (" . $this->db->connect_errno . ") " . $this->db->connect_error);
            }
        }
    }

    function set(Member $member, bool $setAFK, string $reason = NULL): bool
    {
        // Get if Member is already set as AFK in database
        // If he is then check if he passed a reason
        // If he did then update the reason
        // If he didn't then remove the AFK status

        try {
            $isAFK = $this->get($member);

            if ($setAFK) { // Set AFK
                // If the member is already AFK then update the reason
                if ($isAFK) {
                    if ($reason) {
                        print("Updated AFK reason for '$member->username'. Reason: '$reason'\n");
                        $reason = $this->db->escape_string($reason);
                        $this->db->query("UPDATE discord_afk SET reason = '$reason' WHERE member_id = '$member->id' AND time_unset IS NULL;");

                        return true;
                    } else {
                        print("Removed AFK status from '$member->username'.\n");
                        $this->db->query("UPDATE discord_afk SET time_unset = now() WHERE member_id = '$member->id';");
                        $member->removeRole(ROLE_AFK);

                        return true;
                    }
                } else { // If the member is not AFK then set him as AFK
                    print("Set '$member->username' as AFK. Reason: '$reason'\n");
                    if ($reason) { // Just to leave the field as NULL but meh
                        $reason = $this->db->escape_string($reason);
                        $this->db->query("INSERT INTO discord_afk (member_id, reason) VALUES ('$member->id', '$reason');");
                    } else {
                        $this->db->query("INSERT INTO discord_afk (member_id) VALUES ('$member->id');");
                    }
                    $member->addRole(ROLE_AFK);

                    return true;
                }
            } else { // Setting AFK to false
                // If the member is AFK then remove the AFK status
                if ($isAFK) {
                    print("Removed AFK status for '$member->username'.\n");
                    $this->db->query("UPDATE discord_afk SET time_unset = now() WHERE member_id = '$member->id';");
                    $member->removeRole(ROLE_AFK);

                    return true;
                } else {
                    print("'$member->username' is not AFK.\n");
                }
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

        return false;
    }

    // Get the AFK status of a Member
    function get(Member $member): bool|string
    {
        /* try {
            $this->checkConnection();

            $stmt = $this->db->prepare("SELECT reason FROM afk WHERE user_id = ? AND time_unset IS NULL");
            $stmt->bind_param("i", $member->id);
            $stmt->execute();
            $stmt->bind_result($reason);
            $stmt->fetch();

            if ($reason) {
                return $reason;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        } */

        // Try to get the AFK status of the Member
        // If he is AFK then return the reason
        // If he isn't AFK then return false
        try {
            $this->db->checkConnection();

            $query = $this->db->query("SELECT reason FROM discord_afk WHERE member_id = '$member->id' AND time_unset IS NULL;");

            if ($query->num_rows > 0) return $query->fetch_column() ?? true;
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        return false;
    }
}
