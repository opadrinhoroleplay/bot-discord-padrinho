<?php
class GameTracker
{
	private array $playing = [];

	function set($player, $game, $state) {
		$playerGame      = @$this->playing[$player]["game"];
		$playerGameState = @$this->playing[$player]["state"];

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