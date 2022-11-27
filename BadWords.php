<?php

use Discord\Parts\Channel\Message;

function CheckForBadWords(Message $message): bool
{
    if($message->member->roles->has(ROLE_ADMIN)) return false;

    $words = [
        "ban" => [CHANNEL_MAIN],
        "nopixel" => NULL,
    ];

    $channel = $message->channel;
    $found   = false;

    foreach ($words as $word => $channels) {
        if ($channels === NULL || in_array($channel->id, $channels)) { // Check if the word is allowed in the channel
            if (strpos($message->content, $word) !== false) {
                $found = true;
                break;
            }
        }
    }

    // If found and not an admin
    if ($found) {
        $message->delete();
        $message->member->sendMessage("Não podes falar sobre isso, nesse canal. (Canal: `$channel->name` - `$message->content`)");

        return true;
    }
}