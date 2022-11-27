<?php

use Discord\Parts\Channel\Message;

function CheckForBadWords(Message $message): bool
{
    if($message->member->roles->has(ROLE_ADMIN)) return false;

    $words = [
        "ban" => [CHANNEL_MAIN],
        "nopixel" => NULL,
    ];

    $found   = false;

    foreach ($words as $word => $channels) {
        if ($channels === NULL || in_array($message->channel->id, $channels)) { // Check if the word is allowed in the channel
            if (strpos($message->content, $word) !== false) {
                $found = true;
                break;
            }
        }
    }

    // If found and not an admin
    if ($found) {
        $message->delete()->done(function () use ($message) {
            global $channel_admin;
            print("Deleted message from '{$message->author->username}' in '{$message->channel->name}' for using a bad word.\n");
            $message->member->sendMessage("Não podes falar sobre isso nesse canal (#{$message->channel->name}): - `$message->content`");
            $channel_admin->sendMessage("Eliminei uma mensagem de '{$message->author->username}' no '{$message->channel->name}' por utilizar uma palavra banida: - `$message->content`");
        }, function ($error) use ($message) {
            print("Failed to delete message from '{$message->author->username}' in '{$message->channel->name}' for using a bad word.\n");
            print("Error: $error\n");
        });

        return true;
    }
}