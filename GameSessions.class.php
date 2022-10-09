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
		if(array_key_exists($member->id, $this->members)) return false;

		// Check if we already have this game in the database. If not then create it
		$game_name = $this->db->escape_string($game->name);
		$game_id = $this->GetGameId($game_name);

		if($game_id === 0) $game_id = $this->CreateGame($game_name);
		elseif(!$game_id) {
			print("Unable to get or create a game.\n");
			return false;
		}

		$game_state = $this->db->escape_string($game->state);

		$result = $this->db->query("INSERT INTO discord_member_game_sessions (member_id, game_id, game_state) VALUES('$member->id', '$game_id', '$game_state');");

		if(!$result) {
			print("Unable to open session for '$member->username'.\n");
			return false;
		}

		$session_id = $this->db->insert_id;

		$this->members[$member->id] = $session_id;

		print("Opened session '$session_id' for member '$member->username'.\n");

		// Check if we have the user's name in the database and add it if we don't
		if($member->username && !$this->GetMemberUsername($member->id)) {
			$this->db->query("INSERT INTO discord_members (id, username) VALUES('$member->id', '$member->username');");
			print("Created '$member->username' in the database.\n");
		}

		return true;
	}

	function close(Member $member) {
		$session = $this->getMemberSession($member);

		if(!$session) {
			print("'$member->username' doesn't have an open session.\n");
			return false;
		}

		try {
			$this->db->query("UPDATE discord_member_game_sessions SET `end` = NOW() WHERE id = $session->id;");
		} catch (Exception $ex) {
			print("Unable to close the session in the database.\n");
		}

		unset($this->members[$member->id]);

		print("Session '$session->id' closed for '$member->username'.\n");

		return true;
	}

	private function getMemberSession(Member $member): object|bool {
		print("Getting Session for '$member->username' ($member->id)... ");

		try {
			if(!array_key_exists($member->id, $this->members)) { // There's no session id in memory
				$this->db->ping();

				// Check database if there is any open
				$query = "SELECT s.id as id, g.title as game_title, s.game_state as game_state, s.`start` as `start` FROM discord_member_game_sessions s INNER JOIN discord_games g ON s.game_id = g.id WHERE member_id = '$member->id' AND `end` IS NULL;";
				print($query);
				$result = $this->db->query($query);

				if($result->num_rows) {
					$session_object = $result->fetch_object();
					var_dump($session_object);
					return $session_object;
				} else return false;
			} else
				return $this->members[$member->id]; // There's a session id so just return it
		} catch (Exception $ex) {
			print("Database Error: {$ex->getMessage()}\n");
		}

		return false;
	}

	private function GetGameId(string $game_title): int|bool {
		if(empty($game_title)) return false;
	
		try {
			$this->db->ping();
	
			$result = $this->db->query("SELECT id FROM discord_games WHERE title = LCASE('$game_title');");
	
			if($result->num_rows) return $result->fetch_column(); else return 0;
		} catch (Exception $ex) {
			print("Database Error: {$ex->getMessage()}\n");
		}
	
		return false;
	}

	static function IsRoleplayServer(array $elements): bool {
		foreach($elements as $element) {
			if(is_null($element)) continue;
	
			foreach(["rp", "roleplay"] as $word) if(stripos($element, $word) !== false) return true;
		}
	
		return false;
	}

	private function GetMemberUsername(string $id): string|bool {
		if(empty($id)) return false;
	
		// $this->db->ping();
	
		$result = $this->db->query("SELECT username FROM discord_members WHERE id = '$id';");
	
		if($result->num_rows) return $result->fetch_column();
	
		return false;
	}

	private function CreateGame(string $game_title): bool|int {
		try {
			// $this->db->ping();
		
			$game_title = $this->db->escape_string($game_title);
		
			if($this->db->query("INSERT INTO discord_games (title) VALUES(LCASE('$game_title'));")) {
				print("Added game '$game_title' to database.\n");
				return $this->db->insert_id;
			}
		} catch (Exception $ex) {
			print("Database Error: {$ex->getMessage()}");
		}
		
		return false;
	}
}