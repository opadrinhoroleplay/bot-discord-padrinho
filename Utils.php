<?php
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

	if (empty($text)) return 'n-a';

	return $text;
}

function getRandomWord(): string|null {
	$words = file_get_contents("words.txt");
	$words = explode("\n", $words);

	do {
		$word = $words[rand(0, count($words))];
	} while (str_contains($word, "-") || strlen($word) >= 8);

	return strtolower($word);
}

function generateWhatThreeWords(): string {
	$string = sprintf("%s-%s-%s", getRandomWord(), getRandomWord(), getRandomWord());
	// print($string);

	return $string;
}