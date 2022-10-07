<?php
use Discord\Parts\User\Member;
use Discord\Parts\User\Activity;

class GameSessions {
    private mysqli $db;
    private array $members = [];

    function __construct(mysqli $database_handle) {
        $this->db = $database_handle;
    }

    function open(Member $member, Activity $game) {
        if($this->getMemberSession($member)) return false;

        $session_id = 0;

        $this->members[$member->id] = $session_id;

        return true;
    }

    function close(Member $member) {
        if(!$this->getMemberSession($member)) return false;

        $session_id = $this->members[$member->id];

        // Do database stuff

        $this->members[$member->id] = NULL;
        
        return true;
    }

    function getMemberSession(Member $member): int|bool {
        if(!$this->members[$member->id]) { // There's no session id in memory
            // Check database if there is any open
            $result = $this->db->query("SELECT id FROM discord_game_sessions WHERE user_id = '$member->id';");

            if($result->num_rows) return $result->fetch_column(); else return false;
        }

        return $this->members[$member->id]; // There's a session id so just return it
    }
}