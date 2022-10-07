<?php
$discord->on(Event::PRESENCE_UPDATE, function (PresenceUpdate $presence, Discord $discord) {
	global $channel_log_traidores, $channel_log_ingame, $tracker;

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
	if (!$tracker->set($member->id, $member->username, $game?->name, $game?->state)) return;

	$our_server = $game?->name == SERVER_NAME || $game?->state == SERVER_NAME ? true : false;
	
	$traidorfdp = IsRoleplayServer([$game?->name, $game?->state]) && !$our_server ? true : false;

	SetMemberIngame($member, $our_server);

	if($traidorfdp) $channel_log_traidores->sendMessage("**{$member->username}** está a jogar roleplay noutro servidor.");
	$channel_log_ingame->sendMessage("**{$member->username}** " . ($game ? ($game->state ? _U("game", "playing", $game->name, $game->state) : "está agora a jogar **$game->name**") . ($traidorfdp ? " @here" : NULL) : _U("game", "not_playing")));
});