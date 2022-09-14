<?php
include __DIR__ . '/vendor/autoload.php';

$env = Dotenv\Dotenv::createImmutable(__DIR__);
$env->load();
$env->required('TOKEN');

use Discord\Discord;
use Discord\WebSockets\Intents;
use Discord\Parts\Channel\Message;
use Discord\WebSockets\Event;

print("Starting Padrinho\n");

$discord = new Discord([
    'token' => $_ENV['TOKEN'],
    'intents' => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS,
    'loadAllMembers' => true
]);

$discord->on('ready', function (Discord $discord) {
    echo "Bot is ready!", PHP_EOL;

    $discord->on(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) {
        if ($message->author->bot) return; // Ignore bots

        if (str_contains($message->content, 'gay')) {
            $jokes = [
                "Deves querer festa fdp",
                "Até a puta da barraca abana",
                "Cuidadinho com a letra",
                "Não venhas com merdas caralho"
            ];

            $message->reply($jokes[rand(0, count($jokes)-1)] . "...");
        }

        echo "{$message->author->username}: {$message->content}", PHP_EOL;
    });
});

$discord->run();