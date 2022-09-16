<?php
include __DIR__ . '/vendor/autoload.php';

define("GUILD_ID", 519268261372755968);
define("CHANNEL_MAIN", 960555224056086548); 
define("ROLE_AFK", 1020313717805699185);
define("SERVER_NAME", "VIRUXE's Sandbox");

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
	global $tracker;

	$channel = $presence->guild->channels->get("name", "playing");
	$game    = $presence->activities->filter(fn ($activity) => $activity->type == Activity::TYPE_PLAYING)->first();
	$member  = $presence->user->username;
	
	if(!$tracker->set($member, @$game->name, @$game->state)) return;

	$channel->sendMessage("**{$member}** " . ($game ?  "está agora a jogar **$game->name** (**$game->state**)" : "parou de jogar."));

	// Apply Ingame Role if inside Gameserver
	if($game->name == SERVER_NAME|| $game->state == SERVER_NAME) {
		echo "GAMESERVER";
	}
	// elseif($tracker->get($member)->)
});

$discord->listenCommand('afk', function (Interaction $interaction) {
	global $guild;

	$member = $interaction->member;
	$hasAFKRole = $member->roles->get("id", ROLE_AFK);

	if(!$hasAFKRole) $member->addRole(ROLE_AFK); else $member->removeRole(ROLE_AFK);
	$member->moveMember(NULL);

	
	$channel = $guild->channels->get("id", CHANNEL_MAIN);
	$channel->sendMessage($member . ($hasAFKRole ? " não está mais AFK." : " ficou agora AFK."));

	$interaction->respondWithMessage(MessageBuilder::new()->setContent($hasAFKRole ? "Não estás mais como AFK." : "Entraste agora como AFK."), true);
});

$discord->run();