<?php
class GameTracker
{
	private array $playing = [];

	function set($player, $game, $state) {
		if(str_contains($state, "players")) return false;
		
		$playerGame      = @$this->playing[$player]["game"];
		$playerGameState = @$this->playing[$player]["state"];
		
		// Check if it's a small change or not
		$str_diff = strcmp($state, $playerGameState);
		$str_diff = $str_diff <= 0 ? abs($str_diff) : -$str_diff;
		if($str_diff < 2) return false;

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