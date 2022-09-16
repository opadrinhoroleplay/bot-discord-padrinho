<?php
$strings = json_decode(file_get_contents("language.json"), true);

function _U($keyPrimary, $keySecundary, ...$values) {
    global $strings;

    $format = @$strings[strtoupper($keyPrimary)][strtoupper($keySecundary)];

    return $format ? sprintf($format, ...$values) : "UNKNOWN_STRING";
}