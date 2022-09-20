# The _**Godfather**_ - Guild/Server-specific Discord bot, made in PHP.
_Padrinho_, as we say it in Portuguese, is the literal translation for _Godfather_, that one mob kingpin that makes his minions tremble.
Which is perfectly fitting for what he actually does, which is managing a _Discord_ server for a Mafia themed _Roleplay_ server (on the _FiveM_ platform, a GTA 5 Multiplayer modification)

## Observation
This bot utilizes the new _Interaction_ reference from Discord. Which means all of it's commands are done via the native Slash commands.

## Features
As it sits currently there aren't that many features to brag about. But I'll leave you to a list of what it can do up until this point:
### _IRC_ style AFK system
`/afk (reason)` - If you're old enough in the computer world you've used IRC at one point and eventually used this command.
It sets/unsets you as being AFK (_Away From Keyboard_), with an optional reason. What it does exactly is assign you an AFK role, which has a black color, so that people can clearly distinguish who is online or not (silly tiny status circles), plus also notifying the main channel with a message and removing you from any voice channel you are currently on.

It will also set the role if it sees your status change to idle. When coming out of AFK, you can either just type anything on any channel or use the `/afk` command again.

### Game Tracker
The bot will keep track of all the games you play, log it to a private channel and also assign a "In-game" role when it knows you are playing on the server you are __actually__ supposed to be playing on :). Whilst also managing the voice channels you should be on when playing, so if you're in the lobby channel, and playing on __our__ server, it will them move you to the "In-game" voice channel and prevent you from going back to the lobby (you can go anywhere else, but not the lobby), until you stop playing. When you exit the game, if you're a regular player you will then be moved back to the lobby channel.
