<?php
include "vendor/autoload.php";
include "config.php";
include "language.php";
include "GameSessions.class.php";

define("GUILD_ID", 519268261372755968);

define("CHANNEL_ADMIN", 641102112981385226);
define("CHANNEL_MAIN", 960555224056086548);

define("CHANNEL_LOG_AFK", 1020745035169415219);
define("CHANNEL_LOG_INGAME", 1019768367604838460);
define("CHANNEL_LOG_VOICE", 1020683057835020358);

define("CHANNEL_VOICE_ADMIN", 1018817931200700436);
define("CHANNEL_VOICE_LOBBY", 960557917784920104);
define("CHANNEL_VOICE_INGAME", 1019237971217612840);

define("ROLE_ADMIN", 929172055977508924);
define("ROLE_AFK", 1020313717805699185);
define("ROLE_INGAME", 1020385919695585311);

define("SERVER_NAME", $config->server->name);

use React\EventLoop\Loop;

// use Discord\Parts\Channel\Channel;
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\User\Activity;
use Discord\Parts\User\Member;
use Discord\Parts\WebSockets\PresenceUpdate;
use Discord\Parts\WebSockets\VoiceStateUpdate;

use Discord\WebSockets\Event;
use Discord\WebSockets\Intents;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

print("Starting Padrinho\n\n");

$guild                 = (object) NULL;
$channel_admin         = (object) NULL;
$channel_main          = (object) NULL;
$channel_log_traidores = (object) NULL;
$channel_log_ingame    = (object) NULL;
$channel_log_voice     = (object) NULL;
$channel_log_afk       = (object) NULL;

$db = new mysqli("p:{$config->database->host}", $config->database->user, $config->database->pass, $config->database->database);

// $game_sessions = new GameSessions($db);

$logger = new Logger('DiscordPHP');
$logger->pushHandler(new StreamHandler('php://stdout', Monolog\Level::Info));

$discord = new Discord([
	'logger'		 => $logger,
	'token'          => $config->discord->token,
	'intents'        => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS | Intents::GUILD_PRESENCES,
	'loadAllMembers' => false
]);

$discord->on('ready', function (Discord $discord) {
	global $guild, $channel_main, $channel_admin, $channel_log_traidores, $channel_log_ingame, $channel_log_voice, $channel_log_afk;

	echo "Bot is ready!", PHP_EOL;

	$guild                 = $discord->guilds->get("id", GUILD_ID);
	$channel_admin         = $guild->channels->get("id", CHANNEL_ADMIN);
	$channel_main          = $guild->channels->get("id", CHANNEL_MAIN);
	$channel_log_traidores = $guild->channels->get("id", "1026667050489352272");
	$channel_log_afk       = $guild->channels->get("id", CHANNEL_LOG_AFK);
	$channel_log_ingame    = $guild->channels->get("id", CHANNEL_LOG_INGAME);
	$channel_log_voice     = $guild->channels->get("id", CHANNEL_LOG_VOICE);

	// include "registerCommands.php";
});

$discord->on(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) {
	if ($message->author->bot) return; // Ignore bots bullshit

	if ($message->member->roles->get("id", ROLE_AFK)) $message->member->removeRole(ROLE_AFK); // Remove their AFK role if they write something

	include "chatJokes.php";

	// echo "{$message->author->username}: {$message->content}", PHP_EOL;
});

$discord->on(Event::PRESENCE_UPDATE, function (PresenceUpdate $presence, Discord $discord) {
	if ($presence->user->bot) return;

	// var_dump($presence);

	global $game_sessions;
	static $member_status = [];
	$member = $presence->member;

	// Handle status updates
	if (!array_key_exists($member->id, $member_status)) {
		print("Setting status '$member->status' for '$member->username'.\n");
		$member_status[$member->id] = $member->status;

		return;
	} else { // We already have a previous status saved
		$last_status = $member_status[$member->id];
		$curr_status = $member->status;

		if ($last_status != $curr_status) {
			if ($curr_status == "idle") {
				SetMemberAFK($member, true);
				if ($member->getVoiceChannel()) $member->moveMember(NULL, "Became AFK."); // Remove member from the voice channels if they become AFK
			} else SetMemberAFK($member, false);

			print("'$member->username' updated status: '$last_status' -> '$curr_status'\n");

			$member_status[$member->id] = $curr_status; // Update the status

			return;
		} else {
			print("'$member->username' updated their presence, other than the status.\n");
		}
	}

	// Handle game sessions
	$game = $presence->activities->filter(fn ($activity) => $activity->type == Activity::TYPE_PLAYING)->first();

	if ($game) { // Playing a game
		// $game_sessions->open($member, $game);

		// Check if they are playing on our server or not
		if($game->name == SERVER_NAME || $game?->state == SERVER_NAME) { // Playing on our server
			SetMemberIngame($member, true);
		} else { // Not playing on our server
			$traidorfdp = GameSessions::IsRoleplayServer([$game->name, $game?->state]);
			
		}
	} else { // Not playing a game
		// $game_sessions->close($member);
		SetMemberIngame($member, false);
	}


	// if($traidorfdp) $channel_log_traidores->sendMessage("**{$member->username}** está a jogar roleplay noutro servidor.");
	// $channel_log_ingame->sendMessage("**{$member->username}** " . ($game ? ($game->state ? _U("game", "playing", $game->name, $game->state) : "está agora a jogar **$game->name**") . ($traidorfdp ? " @here" : NULL) : _U("game", "not_playing")));
});

$discord->listenCommand('afk', function (Interaction $interaction) {
	global $channel_main, $channel_admin;

	$member  = $interaction->member;
	$is_afk   = IsMemberAFK($member);    // Check if the member has the role or not

	SetMemberAFK($member, !$is_afk);

	$message = $is_afk ? "$member não está mais AFK." : "$member ficou agora AFK";
	$channel_main->sendMessage($message);
	if (IsMemberAdmin($member)) $channel_admin->sendMessage($message);

	$interaction->respondWithMessage(MessageBuilder::new()->setContent($is_afk ? _U("afk", "self_not_afk") : _U("afk", "self_afk")), true);
});

$discord->on(Event::VOICE_STATE_UPDATE, function (VoiceStateUpdate $voiceState, Discord $discord, $oldState) {
	global $channel_admin, $channel_log_voice;

	$member  = $voiceState->member;
	$channel = $voiceState->channel;

	// Don't let the player move to the lobby channel, unless he's an admin
	if (!IsMemberAdmin($member) && IsMemberIngame($member) && $voiceState->channel_id == CHANNEL_VOICE_LOBBY) {
		$member->moveMember(CHANNEL_VOICE_INGAME, "Tentou voltar ao lobby.");
		return;
	}

	if ($channel?->id == CHANNEL_VOICE_ADMIN) $channel_admin->sendMessage("$member->username entrou no $channel. @here");

	$channel_log_voice->sendMessage($member->username . ($channel ?  " entrou no canal $channel." : " saiu do canal de voz."));
});

$discord->run();

/*
	For some reason even a persistent connection goes away after some time.
	So I figured I would put a ping on a loop instead of pinging with every database query.
*/
/* Loop::addPeriodicTimer(.1, function () {
	global $db;
	
	if($db->ping()) print("Database Connection has gone away. Reconnecting...\n");
    $memory = memory_get_usage() / 1024;
    $formatted = number_format($memory, 3).'K';
    echo "Current memory usage: {$formatted}\n";
}); */

function SetMemberAFK(Member $member, bool $toggle): bool
{
	$is_afk = IsMemberAFK($member);

	// print($is_afk . " " . $toggle);

	if ($is_afk === $toggle) return false;

	global $channel_log_afk;

	if ($toggle) {
		$member->addRole(ROLE_AFK, "Ficou AFK.");
		$member->moveMember(NULL); // Remove member from Voice Channels
	} else $member->removeRole(ROLE_AFK, "Voltou de AFK.");

	$channel_log_afk->sendMessage($member->username . ($toggle ? " ficou AFK." : " voltou de AFK."));

	return true;
}

function SetMemberIngame(Member $member, bool $toggle): bool
{
	$is_ingame      = IsMemberIngame($member);
	$member_channel = $member->getVoiceChannel();

	// print($is_ingame . " " . $toggle);

	if ($is_ingame === $toggle) return false;

	global $channel_admin;

	if ($toggle) {
		$member->addRole(ROLE_INGAME, "Entrou no Servidor."); // Set the AFK role
		if ($member_channel) $member->moveMember(CHANNEL_VOICE_INGAME, "Entrou no Servidor."); // Move member to the in-game channel when in-game
	} else {
		$member->removeRole(ROLE_INGAME, "Saiu do Servidor.");
		if ($member_channel && !IsMemberAdmin($member)) $member->moveMember(CHANNEL_VOICE_LOBBY, "Saiu do Servidor."); // Move member to the voice lobby if not in-game anymore
	}

	$channel_admin->sendMessage($member->username . ($toggle ? " entrou no servidor." : " saiu do servidor."));

	return true;
}

function IsMemberAFK(Member $member): bool
{
	return $member->roles->get("id", ROLE_AFK) ? true : false;
}

function IsMemberAdmin(Member $member): bool
{
	return $member->roles->get("id", ROLE_ADMIN) ? true : false;
}

function IsMemberIngame(Member $member): bool
{
	return $member->roles->get("id", ROLE_INGAME) ? true : false;
}
