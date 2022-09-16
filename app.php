<?php
include __DIR__ . '/vendor/autoload.php';
include "language.php";

define("GUILD_ID", 		519268261372755968);
define("CHANNEL_MAIN", 	960555224056086548); 
define("ROLE_AFK", 		1020313717805699185);
define("ROLE_INGAME", 	1020385919695585311);
define("SERVER_NAME", 	"VIRUXE's Sandbox");

$env = Dotenv\Dotenv::createImmutable(__DIR__);
$env->load();
$env->required('TOKEN');

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Command\Command;
use Discord\Parts\Interactions\Interaction;
use Discord\parts\User;
use Discord\Parts\User\Activity;
use Discord\Parts\WebSockets\PresenceUpdate;
use Discord\WebSockets\Event;
use Discord\WebSockets\Intents;

print("Starting Padrinho\n");

$guild = (object) NULL;
$afkRole = NULL;

$discord = new Discord([
    'token' => $_ENV['TOKEN'],
    'intents' => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS | Intents::GUILD_PRESENCES,
    'loadAllMembers' => false
]);

$discord->on('ready', function (Discord $discord) {
	global $guild, $afkRole;

    echo "Bot is ready!", PHP_EOL;

	$guild = $discord->guilds->get("id", GUILD_ID);
	// $afkRole = $guild->roles->get('id', ROLE_AFK);

	// Setup Slash Commands
	// AFK
	/* $guildCommand = new Command($discord, ['name' => 'afk', 'description' => 'Ativa ou Desativa o teu Modo de AFK aqui no Servidor de Discord.']);
	$guild->commands->save($guildCommand); */
});

$discord->on(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) {
	global $guild;

	if ($message->author->bot) return; // Ignore bots

	if($message->member->roles->get("id", ROLE_AFK)) {
		$message->member->removeRole(ROLE_AFK);
		$channel = $guild->channels->get("id", CHANNEL_MAIN);
		$channel->sendMessage("$message->member não está mais AFK.");
	}

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

class GameTracker
{
	public array $playing = [];

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

$tracker = new GameTracker();

$discord->on(Event::PRESENCE_UPDATE, function (PresenceUpdate $presence, Discord $discord) {
	// var_dump($presence->status);
	global $tracker;

	$channel = $presence->guild->channels->get("name", "playing");
	$game    = $presence->activities->filter(fn ($activity) => $activity->type == Activity::TYPE_PLAYING)->first();
	$member  = $presence->member;
	
	// Check if this activity is actually different than what we've got saved already
	if(!$tracker->set($member->username, $game?->name, $game?->state)) return;

	// Apply Ingame Role if inside Gameserver
	if($game?->name == SERVER_NAME || $game?->state == SERVER_NAME)
		$member->addRole(ROLE_INGAME);
	else
		$member->removeRole(ROLE_INGAME);

	$channel->sendMessage("**{$member->username}** " . ($game ? _U("game","playing", $game->name, $game->state) : _U("game", "not_playing")));
});

$discord->listenCommand('afk', function (Interaction $interaction) {
	global $guild;

	$member = $interaction->member;
	$hasRole = $member->roles->get("id", ROLE_AFK);

	if(!$hasRole) $member->addRole(ROLE_AFK); else $member->removeRole(ROLE_AFK);
	$member->moveMember(NULL);

	
	$channel = $guild->channels->get("id", CHANNEL_MAIN);
	$channel->sendMessage($member . ($hasRole ? _U("afk", "not_afk") : _U("afk", "afk")));

	$interaction->respondWithMessage(MessageBuilder::new()->setContent($hasRole ? _U("afk", "me_not_afk") : _U("afk", "me_afk")), true);
});

$discord->run();