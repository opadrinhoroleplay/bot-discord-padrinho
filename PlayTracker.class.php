<?php
class GameTracker
{
	private array $playing = [];

	function set($player, $game, $state) {
		if(str_contains($state, "players")) return false;

		$playerGame      = @$this->playing[$player]["game"];
		$playerGameState = @$this->playing[$player]["state"];

		$stateDiff		 = strcmp($state, $playerGameState);

		print("\nMember $player | diff: $stateDiff - $playerGameState - $state\n\n");

		if($playerGame != $game & $playerGame != $state) {
			$this->playing[$player]["game"]  = $game;
			$this->playing[$player]["state"] = $state;
			return true;
		}
		elseif($playerGameState != $state) {
			$this->playing[$player]["state"] = $state;
			return true;
		}
		
		return false;
	}

	function get($player) {
		return $this->playing[$player] ? (object) $this->playing[$player] : NULL;
	}
}