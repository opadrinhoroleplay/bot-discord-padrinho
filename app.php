<?php
include "vendor/autoload.php";
include "language.php";
include "PlayTracker.class.php";

define("GUILD_ID", 519268261372755968);
define("CHANNEL_MAIN", 960555224056086548); 
define("CHANNEL_LOG_PLAYING", 1019768367604838460); 
define("CHANNEL_LOG_VOICE", 1020683057835020358); 
define("CHANNEL_VOICE_MAIN", 960557917784920104); 
define("CHANNEL_VOICE_PLAYING", 1019237971217612840); 
define("ROLE_AFK", 1020313717805699185);
define("ROLE_PLAYING", 1020385919695585311);
define("SERVER_NAME", "VIRUXE's Sandbox");

$env = Dotenv\Dotenv::createImmutable(__DIR__);
$env->load();
$env->required('TOKEN');

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\User\Activity;
use Discord\Parts\WebSockets\PresenceUpdate;
use Discord\Parts\WebSockets\VoiceStateUpdate;
use Discord\WebSockets\Event;
use Discord\WebSockets\Intents;

print("Starting Padrinho\n\n");

$guild = (object) NULL;
$tracker = new GameTracker();

$discord = new Discord([
    'token' => $_ENV['TOKEN'],
    'intents' => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS | Intents::GUILD_PRESENCES,
    'loadAllMembers' => false
]);

$discord->on('ready', function (Discord $discord) {
	global $guild;

    echo "Bot is ready!", PHP_EOL;

	$guild = $discord->guilds->get("id", GUILD_ID);

	// include "registerCommands.php";
});

$discord->on(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) {
	if ($message->author->bot) return; // Ignore bots bullshit

	if($message->member->roles->get("id", ROLE_AFK)) $message->member->removeRole(ROLE_AFK);

	include "chatJokes.php";

	echo "{$message->author->username}: {$message->content}", PHP_EOL;
});

$discord->on(Event::PRESENCE_UPDATE, function (PresenceUpdate $presence, Discord $discord) {
	global $tracker;

	$channel = $presence->guild->channels->get("id", CHANNEL_LOG_PLAYING);
	$game    = $presence->activities->filter(fn ($activity) => $activity->type == Activity::TYPE_PLAYING)->first();
	$member  = $presence->member;

	if($member->status == "idle")
		$member->addRole(ROLE_AFK);
	elseif($member->status == "online")
		$member->removeRole(ROLE_AFK);
	
	// Check if this activity is actually different than what we've got saved already
	if(!$tracker->set($member->username, $game?->name, $game?->state)) return;

	// Apply Ingame Role if inside Gameserver
	if($game?->name == SERVER_NAME || $game?->state == SERVER_NAME) {
		$member->addRole(ROLE_PLAYING);
		$member->moveMember(CHANNEL_VOICE_PLAYING);
	} else {
		$member->removeRole(ROLE_PLAYING);
		$member->moveMember(CHANNEL_VOICE_MAIN);
	}

	$channel->sendMessage("**{$member->username}** " . ($game ? _U("game","playing", $game->name, $game->state) : _U("game", "not_playing")));
});

$discord->listenCommand('afk', function (Interaction $interaction) {
	$member = $interaction->member;
	$hasRole = $member->roles->get("id", ROLE_AFK); // Check if the member has the role or not

	if(!$hasRole) $member->addRole(ROLE_AFK); else $member->removeRole(ROLE_AFK); // Add or Remove Role accordingly

	$member->moveMember(NULL); // Remove member from Voice Channels

	$interaction->respondWithMessage(MessageBuilder::new()->setContent($hasRole ? _U("afk", "self_not_afk") : _U("afk", "self_afk")), true);
});

$discord->on(Event::VOICE_STATE_UPDATE, function(VoiceStateUpdate $voiceState, Discord $discord, $oldState) {
	$member = $voiceState->member;

	if($member->roles->get("id", ROLE_PLAYING) && $voiceState->channel_id == CHANNEL_VOICE_MAIN)
		$member->moveMember(CHANNEL_VOICE_PLAYING);

	// var_dump($voiceState);
});

$discord->run();