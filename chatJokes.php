<?php
$triggers = [
    "gay", "fdp", "paneleiro"
];

$jokes = [
    "Deves querer festa fdp",
    "Até a puta da barraca abana",
    "Cuidadinho com a letra",
    "Não venhas com merdas caralho"
];

if(array_search($string, $triggers))
    $message->reply($jokes[rand(0, count($jokes)-1)] . "...");