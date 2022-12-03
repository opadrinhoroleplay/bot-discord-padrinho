<?php

namespace Utils {
	class Time
	{
		// Hour to milliseconds
		static function hour(int $hours): int
		{
			return self::minute($hours * 60);
		}
		// Minute to milliseconds
		static function minute(int $minutes): int
		{
			return $minutes * 60 * 1000;
		}
	}
}

namespace Utils\Words {
	function slugify($text)
	{
		// replace non letter or digits by -
		$text = preg_replace('~[^\pL\d]+~u', '-', $text);

		// transliterate
		$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

		// remove unwanted characters
		$text = preg_replace('~[^-\w]+~', '', $text);

		// trim
		$text = trim($text, '-');

		// remove duplicate -
		$text = preg_replace('~-+~', '-', $text);

		// lowercase
		$text = strtolower($text);

		if (empty($text)) return slugify(getInsult());

		return $text;
	}

	function getRandomWord(bool $lowercase = true): string|null
	{
		$words = file_get_contents("words.txt");
		$words = explode("\n", $words);

		do {
			$word = $words[rand(0, count($words) - 1)];
		} while (str_contains($word, "-") || strlen($word) >= 8); // Portuguese words can have dashes, so we'll just avoid them in this scenario

		return $lowercase ? strtolower($word) : $word;
	}

	function generateWhatThreeWords(): string
	{
		return sprintf("%s-%s-%s", getRandomWord(), getRandomWord(), getRandomWord());
	}

	function getInsult()
	{
		$insults = file_get_contents("insults.txt");
		$insults = explode("\n", $insults);

		return strtolower(trim($insults[rand(0, count($insults) - 1)]));
	}
}