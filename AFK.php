<?php
use Discord\Parts\User\Member;

class AFKHandler {
	private \mysqli $db;

	function __construct(\mysqli $database_handle) {
		$this->db = $database_handle;
	}

    function set(Member $member, bool $setAFK, string $reason = NULL): bool {
        // Get if Member is already set as AFK in database
        // If he is then check if he passed a reason
        // If he did then update the reason
        // If he didn't then remove the AFK status

        $isAFK = $this->get($member);

        if($setAFK) { // Set AFK
            // If the member is already AFK then update the reason
            if($isAFK) {
                if($reason) {
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
                if($reason) { // Just to leave the field as NULL but meh
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
            if($isAFK) {
                print("Removed AFK status for '$member->username'.\n");
                $this->db->query("UPDATE discord_afk SET time_unset = now() WHERE member_id = '$member->id';");
                $member->removeRole(ROLE_AFK);
                
                return true;
            } else {
                print("'$member->username' is not AFK.\n");
            }
        }

        return false;
    }

    // Get the AFK status of a Member
    function get(Member $member): bool|string {
        $query = $this->db->query("SELECT reason FROM discord_afk WHERE member_id = '$member->id' AND time_unset IS NULL;");
        if($query->num_rows > 0) {
            $row = $query->fetch_assoc();
            return $row['reason'] ?? true;
        }
        return false;
    }
}