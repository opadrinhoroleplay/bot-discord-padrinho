<?php
include "vendor/autoload.php";
include "language.php";
include "PlayTracker.class.php";

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

define("SERVER_NAME", "VIRUXE's Sandbox");

$env = Dotenv\Dotenv::createImmutable(__DIR__);
$env->load();
$env->required('TOKEN');

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\User\Member;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\User\Activity;
use Discord\Parts\WebSockets\PresenceUpdate;
use Discord\Parts\WebSockets\VoiceStateUpdate;
use Discord\WebSockets\Event;
use Discord\WebSockets\Intents;

print("Starting Padrinho\n\n");

$guild              = (object) NULL;
$channel_admin      = (object) NULL;
$channel_main       = (object) NULL;
$channel_log_ingame = (object) NULL;
$channel_log_voice  = (object) NULL;
$channel_log_afk    = (object) NULL;

$tracker = new GameTracker();

$discord = new Discord([
	'token'          => $_ENV['TOKEN'],
	'intents'        => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS | Intents::GUILD_PRESENCES,
	'loadAllMembers' => false
]);

$discord->on('ready', function (Discord $discord) {
	global $guild, $channel_main, $channel_admin, $channel_log_ingame, $channel_log_voice, $channel_log_afk;

	echo "Bot is ready!", PHP_EOL;

	$guild              = $discord->guilds->get("id", GUILD_ID);
	$channel_admin      = $guild->channels->get("id", CHANNEL_ADMIN);
	$channel_main       = $guild->channels->get("id", CHANNEL_MAIN);
	$channel_log_afk    = $guild->channels->get("id", CHANNEL_LOG_AFK);
	$channel_log_ingame = $guild->channels->get("id", CHANNEL_LOG_INGAME);
	$channel_log_voice  = $guild->channels->get("id", CHANNEL_LOG_VOICE);

	// include "registerCommands.php";
});

$discord->on(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) {
	if ($message->author->bot) return; // Ignore bots bullshit

	if ($message->member->roles->get("id", ROLE_AFK)) {
		$message->member->removeRole(ROLE_AFK);
	}

	include "chatJokes.php";

	echo "{$message->author->username}: {$message->content}", PHP_EOL;
});

$discord->on(Event::PRESENCE_UPDATE, function (PresenceUpdate $presence, Discord $discord) {
	global $channel_log_ingame, $tracker;

	// if($presence->author->bot) return;

	$game    = $presence->activities->filter(fn ($activity) => $activity->type == Activity::TYPE_PLAYING)->first();
	$member  = $presence->member;

	if ($member->status == "idle") {
		SetMemberAFK($member, true);
		if ($member->getVoiceChannel()) $member->moveMember(NULL, "Became AFK."); // Remove member from the voice channels if they become AFK
	} elseif ($member->status == "online") {
		SetMemberAFK($member, false);
	}

	// Check if this activity is actually different than what we've got saved already, if so then save
	if (!$tracker->set($member->username, $game?->name, $game?->state)) return;

	SetMemberIsIngame($member, $game?->name == SERVER_NAME || $game?->state == SERVER_NAME ? true : false);
	
	$channel_log_ingame->sendMessage("**{$member->username}** " . ($game ? ($game->state ? _U("game", "playing", $game->name, $game->state) : "está agora a jogar **$game->name**") : _U("game", "not_playing")));
});

$discord->listenCommand('afk', function (Interaction $interaction) {
	global $channel_main, $channel_admin;

	$member  = $interaction->member;
	$is_afk   = IsMemberAFK($member);    // Check if the member has the role or not

	if (!$is_afk) {
		SetMemberAFK($member, true);
		$member->moveMember(NULL); // Remove member from Voice Channels
	} else {
		SetMemberAFK($member, false); // Add or Remove Role accordingly
	}

	$message = $is_afk ? "$member não está mais AFK." : "$member ficou agora AFK";
	$channel_main->sendMessage($message);
	if (IsMemberAdmin($member)) $channel_admin->sendMessage($message);

	$interaction->respondWithMessage(MessageBuilder::new()->setContent($is_afk ? _U("afk", "self_not_afk") : _U("afk", "self_afk")), true);
});

$discord->on(Event::VOICE_STATE_UPDATE, function (VoiceStateUpdate $voiceState, Discord $discord, $oldState) {
	global $channel_admin, $channel_log_voice;

	$member 	= $voiceState->member;
	$channel = $voiceState->channel;

	// Don't let the player move to the lobby channel, unless he's an admin
	if (!IsMemberAdmin($member) && IsMemberIngame($member) && $voiceState->channel_id == CHANNEL_VOICE_LOBBY) {
		$member->moveMember(CHANNEL_VOICE_INGAME, "Tentou voltar ao lobby.");
		return;
	}

	if ($channel->id == CHANNEL_VOICE_ADMIN) $channel_admin->sendMessage("$member->username entrou no $channel. @here");

	$channel_log_voice->sendMessage($member->username . ($channel ?  " entrou no canal $channel." : " saiu do canal de voz."));
});

$discord->run();

function SetMemberAFK(Member $member, bool $toggle): bool
{
	$is_afk = IsMemberAFK($member);

	print($is_afk . " " . $toggle);

	if ($is_afk === $toggle) return false;

	global $channel_log_afk;

	if ($toggle) $member->addRole(ROLE_AFK, "Ficou AFK.");
	else $member->removeRole(ROLE_AFK, "Voltou de AFK.");

	$channel_log_afk->sendMessage($member->username . ($toggle ? " ficou AFK." : " voltou de AFK."));

	return true;
}

function SetMemberIsIngame(Member $member, bool $toggle): bool {
	$is_ingame      = IsMemberIngame($member);
	$member_channel = $member->getVoiceChannel();

	print($is_ingame . " " . $toggle);

	if ($is_ingame === $toggle) return false;

	global $channel_log_ingame;

	if ($toggle) {
		$member->addRole(ROLE_INGAME, "Ficou AFK."); // Set the AFK role
		if ($member_channel) $member->moveMember(CHANNEL_VOICE_INGAME, "Começou a jogar."); // Move member to the in-game channel when in-game
	}
	else {
		$member->removeRole(ROLE_INGAME, "Voltou de AFK.");
		if ($member_channel && !IsMemberAdmin($member)) $member->moveMember(CHANNEL_VOICE_LOBBY, "Parou de jogar."); // Move member to the voice lobby if not in-game anymore
	}

	$channel_log_ingame->sendMessage($member->username . ($toggle ? " ficou AFK." : " voltou de AFK."));

	return true;
}

function IsMemberAFK(Member $member): bool {
	return $member->roles->get("id", ROLE_AFK) ? true : false;
}

function IsMemberAdmin(Member $member): bool {
	return $member->roles->get("id", ROLE_ADMIN) ? true : false;
}

function IsMemberIngame(Member $member): bool {
	return $member->roles->get("id", ROLE_INGAME) ? true : false;
}