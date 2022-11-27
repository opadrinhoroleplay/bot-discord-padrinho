<?php

use Discord\Parts\Channel\Message;

class BadWords
{
    private $words = [
        "ban"     => [CHANNEL_MAIN],
        "nopixel" => NULL,
        "leaked"  => NULL,
        "leak"    => NULL,
        "leaks"   => NULL,
        "esx"     => NULL,
        "qbcore"  => NULL,
    ];

    // Scan a message for bad words
    public static function Scan(Message $message): bool
    {
        if($message->channel->is_private) return false; // Only scan messages if they are not from the bot's private channel
        if($message->member->roles->has(ROLE_ADMIN)) return false; // Don't scan messages from admins

        $found = false;
        
        foreach (self::$words as $word => $channels) {
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
                print("Deleted message from '{$message->author->username}' in '{$message->channel->name}' for using a bad word.\n");
                $message->member->sendMessage("Não podes falar sobre isso nesse canal (#{$message->channel->name}): `$message->content`");
                return true;
            }, function ($error) use ($message) {
                print("Failed to delete message from '{$message->author->username}' in '{$message->channel->name}' for using a bad word.\n");
                print("Error: $error\n");
            });
        }
    
        return false;
    }
}