<?php
class Jokes {
    static function GetRandomPortugueseJoke(): string|bool {
        $joke = file_get_contents("https://api.chucknorris.io/jokes/random");

        if(!$joke) return false;

		$joke = json_decode($joke, true);

        if(!$joke) return false;

		return $joke["value"];
	}

	static function GetRandomJoke(): string|bool {
        $joke = file_get_contents("https://official-joke-api.appspot.com/random_joke");
		
        if(!$joke) return false;

		$joke = json_decode($joke, true);

        if(!$joke) return false;

		return $joke->setup . PHP_EOL . $joke->punchline;
	}
}