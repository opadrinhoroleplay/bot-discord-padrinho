<?php

class GameTracker
{
	private array $playing = [];

	function set(int $player_id, string|null $player_username, string|null $game, string|null $state) {
		$last_game       = @$this->playing[$player_id]["game"] ?? NULL;
		$last_game_state = @$this->playing[$player_id]["state"] ?? NULL;

		if(!strcmp($state, $last_game_state)) return false;
		if(str_contains($state, "players")) return false;
		
		// Check if it's a small change or not
		$state_diff = count(array_diff_assoc(str_split($state), str_split($last_game_state)));
		// $state_diff = strlen($last_game_state) - strlen($state);
		// $state_diff = $state_diff < 0 ?: abs($state_diff);

		
		// if($state_diff < 2) return false;

		if($last_game != $game & $last_game != $state) { // It's either not playing anymore or a new game
			$this->playing[$player_id]["game"]  = $game;
			$this->playing[$player_id]["state"] = $state;
			
			if($game) {
				$this->playing[$player_id]["session_id"] = CreateGameSession($player_id, $player_username, $game, $state);
			} else {
				CloseGameSession($this->playing[$player_id]["session_id"]);
				$this->playing[$player_id]["session_id"] = NULL;
			}

			return true;
		}
		elseif($state && $state_diff > 2 && $last_game_state != $state) { // same game, just updating state
			print("Game state difference: $state_diff | Last: $last_game_state - Curr: $state\n");

			UpdateSessionState($this->playing[$player_id]["session_id"], $state);

			$this->playing[$player_id]["state"] = $state;

			return true;
		}
		
		return false;
	}

	/* function get($player) {
		return $this->playing[$player] ? (object) $this->playing[$player] : NULL;
	} */
}

function IsRoleplayServer(array $elements): bool {
	foreach($elements as $element) {
		if(is_null($element)) continue;

		foreach(["rp", "roleplay"] as $word) if(stripos($element, $word) !== false) return true;
	}

	return false;
}

function GetGameId(string $game_title): int|bool {
	global $db;

	if(empty($game_title)) return -1;

	try {
		$db->ping();

		$result = $db->query("SELECT id FROM discord_games WHERE title = LCASE('$game_title');");

		if($result->num_rows) return $result->fetch_column(); else return false;
	} catch (Exception $ex) {
		print("Database Error: {$ex->getMessage()}");
	}

	return false;
}

function GetPlayerUsername(string $id): string|bool {
	global $db;

	if(empty($id)) return false;

	$db->ping();

	$result = $db->query("SELECT username FROM discord_users WHERE id = '$id';");

	if($result->num_rows) return $result->fetch_column();

	return false;
}

function CreateGameSession(string $player_id, string $player_username, string|int $game, string|null $game_state): bool|int {
	global $db;

	$game = $db->escape_string($game);
	$game_state = $db->escape_string($game_state);

	$game_id = gettype($game) === "string" ? GetGameId($game) : CreateGame($game);

	if(!$game_id) return false;

	$query = "INSERT INTO discord_user_game_sessions (game_id, user, state) VALUES('$game_id', '$player_id', '$game_state');";

	if($db->query($query)) {
		$session_id = $db->insert_id;

		$player = $player_username ?? $player_id;

		print("[PlayTracker] New session created for '$player'. ID: $session_id.\n");

		// Check if we have the user's name in the database and add it if we don't
		if($player_username && !GetPlayerUsername($player_id)) {
			$db->query("INSERT INTO discord_users (id, username) VALUES('$player_id', '$player_username');");
			print("Created $player_username in the database.\n");
		}

		return $session_id;
	} else print($query) . PHP_EOL;

	return false;
}

function UpdateSessionState(int $session_id, string $state): bool {
	if($session_id == -1) return false;

	global $db;

	$db->ping();

	$state = $db->escape_string($state);

	$query = "UPDATE discord_user_game_sessions SET state = '$state' WHERE id = $session_id;";

	$result = $db->query($query);
	
	if(!$result) $db->error . PHP_EOL;

	return $result;
}

function CloseGameSession(int $session_id) {
	if($session_id == -1) return false;

	global $db;

	try {
		$db->ping();
	
		$query = "UPDATE discord_user_game_sessions SET end = now() WHERE id = $session_id;";
	
		$result = $db->query($query);
	
		if(!$result) print($query) . PHP_EOL;
	
		return $result;
	} catch (Exception $ex) {

	}

	return false;
}

function CreateGame(string $game_title): bool|int {
	global $db;

	try {
		$db->ping();
	
		$game_title = $db->escape_string($game_title);
	
		if($db->query("INSERT INTO discord_games (title) VALUES(LCASE('$game_title'));")) {
			print("[PlayTracker] '$game_title' added to database.\n");
			return $db->insert_id;
		}
	} catch (Exception $ex) {
		print("Database Error: {$ex->getMessage()}");
	}
	
	return false;
}