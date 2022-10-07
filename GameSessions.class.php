<?php
use Discord\Parts\User\Member;
use Discord\Parts\User\Activity;

class GameSessions {
    private \mysqli $db;
    private array $members = [];

    function __construct(\mysqli $database_handle) {
        $this->db = $database_handle;
    }

    function open(Member $member, Activity $game) {
        if($this->members[$member->id]) return false;

        // Check if we already have this game in database. If not then create it
        $game_id = 

        $result = $this->db->query("INSERT INTO discord_member_game_sessions (member_id, game_id, game_state) VALUES('$member->id', '$game_id', '$game->state');");

        if($result) {
            $session_id = $this->db->insert_id;

            $this->members[$member->id] = $session_id;
    
            print("Opened session '$session_id' for member '$member->username'.");
    
            return true;
        } else {
            print("Unable to open session for '$member->username'");

            return false;
        }
    }

    function close(Member $member) {
        $session_id = $this->getMemberSession($member);

        if(!$session_id) {
            print("$member->username doesn't have an open session.");

            return false;
        }

        // Do database stuff

        $this->members[$member->id] = NULL;

        print("Session '$session_id' closed for '$member->username'.");

        return true;
    }

    private function getMemberSession(Member $member): int|bool {
        if(!$this->members[$member->id]) { // There's no session id in memory
            // Check database if there is any open
            $result = $this->db->query("SELECT id FROM discord_game_sessions WHERE user_id = '$member->id' AND `end` IS NULL;");

            if($result->num_rows) return $result->fetch_column(); else return false;
        }

        return $this->members[$member->id]; // There's a session id so just return it
    }

    private function getGameId(string $game_title): int|bool {
        if(empty($game_title)) return false;
    
        try {
            $this->db->ping();
    
            $result = $this->db->query("SELECT id FROM discord_games WHERE title = LCASE('$game_title');");
    
            if($result->num_rows) return $result->fetch_column(); else return 0;
        } catch (Exception $ex) {
            print("Database Error: {$ex->getMessage()}");
        }
    
        return false;
    }
}