<?php

declare(strict_types=1);

// Get environment debug setting
@define("DEBUG", $argv[1] === "debug" ? true : false);
if (DEBUG) print("Debug mode enabled\n\n");

include("vendor/autoload.php");
include("config.php");
include("Database.class.php");
include("Utils.php");
include("Member.class.php");
include("GameSessions.class.php");
include("TimeKeeping.php");
include("Trivia.php");
include("AFK.php");
include("BadWords.php");
include("FiveM.php");
include("Admin.class.php");

// date_default_timezone_set('Europe/Lisbon');

use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\TextInput;
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Helpers\Collection;
use Discord\InteractionType;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Invite;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Thread\Thread;
use Discord\Parts\User\Activity;
use Discord\Parts\User\Member;
use Discord\Parts\WebSockets\MessageReaction;
use Discord\Parts\WebSockets\PresenceUpdate;
use Discord\Parts\WebSockets\VoiceStateUpdate;
use Discord\WebSockets\Event;
use Discord\WebSockets\Intents;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

use Utils\Words;

print("Loading Fredo...\n\n");

$db = new DatabaseConnection("p:" . config->database->host, config->database->user, config->database->pass, config->database->database);

$start_time            = new DateTime();
$guild                 = (object) NULL;
$channel_admin         = (object) NULL;
$channel_main          = (object) NULL;
$channel_log_traidores = (object) NULL;
$channel_log_ingame    = (object) NULL;
$channel_log_voice     = (object) NULL;
$channel_log_afk       = (object) NULL;
$rollcall_message_id   = null;
$trivia                = null;
$invites_uses          = []; // Array of invites and their uses

$activity_counter = [
	"dev_messages"   => 0,
	"github"         => 0,
	"clickup"        => 0,
	"admin_messages" => 0,
];

$logger = new Logger('DiscordPHP');
$logger->pushHandler(new StreamHandler('php://stdout', Monolog\Level::Info));

$discord = new Discord([
	'logger'         => $logger,
	'token'          => config->discord->token,
	'intents'        => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS | Intents::GUILD_PRESENCES | Intents::GUILD_MESSAGES | Intents::MESSAGE_CONTENT,
	'loadAllMembers' => false,
	'storeMessages'  => true
]);

$discord->on('ready', function (Discord $discord) use ($db) {
	global $guild, $channel_main, $channel_admin, $channel_log_traidores, $channel_log_ingame, $channel_log_voice, $channel_log_afk;

	echo "Bot is ready!\n\n";

	$discord->updatePresence($discord->factory(\Discord\Parts\User\Activity::class, ['name' => 'vocês seus cabrões!', 'type' => Activity::TYPE_WATCHING]));

	$guild                 = $discord->guilds->get("id", config->discord->guild);
	$channel_admin         = $guild->channels->get("id", config->discord->channels->admin);
	$channel_main          = $guild->channels->get("id", config->discord->channels->main);
	$channel_log_afk       = $guild->channels->get("id", config->discord->channels->log->afk);
	$channel_log_ingame    = $guild->channels->get("id", config->discord->channels->log->ingame);
	$channel_log_voice     = $guild->channels->get("id", config->discord->channels->log->voice);



	include("Commands.php");

	// Send startup message to the admin channel, with the bot's version. Only if the bot is not in debug mode
	if (!DEBUG) {
		// Get the bot version from the amount of commits on GitHub
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Don't verify SSL certificate
		curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/VIRUXE/bot-discord-padrinho/commits");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Accept: application/vnd.github.v3+json",
			"User-Agent: VIRUXE"
		]);

		$github = curl_exec($ch);
		if (curl_errno($ch)) echo 'Error:' . curl_error($ch);
		curl_close($ch);

		// If the request was successful, get the amount of commits
		if ($github) {
			$commits = json_decode($github, true);
			$version = substr($commits[0]["sha"], 0, 7);
			$last_commit_message = $commits[0]["commit"]["message"];

			$channel_admin->sendMessage("Iniciado com sucesso. **Versão: $version** `$last_commit_message`");
		}
	}

	// Loop through all the invites, get their uses and build the $invites_uses array
	// TODO: Manage invites being active or not
	print("Checking invites...\n");
	$guild->invites->freshen()->done(function (Collection $invites) use ($discord, $db, $guild, $channel_admin) {
		foreach ($invites as $invite) {
			if ($invite->inviter->id != $discord->id) continue; // Only get invites created by our bot

			print("Invite '{$invite->code}' has '{$invite->uses}' uses\n");
			$invites_uses[$invite->code] = $invite->uses;

			// Check invite uses against the database, in the 'invites_used' table and alert if it's different
			$query = $db->query("SELECT COUNT(*) FROM invites_used WHERE code = '{$invite->code}';");
			$db_invite_uses = $query->fetch_column();

			// If the invite was used more times than the database says, it means the bot was offline when it was used
			// Send a message to the invite creator to let them know and get in contact with VIRUXE
			if ($db_invite_uses < $invite->uses) {
				print("Invite {'$invite->code}' has '{$invite->uses}' uses, but the database says it has {$db_invite_uses} uses\n");
				$channel_admin->sendMessage("O número de convites usados para o convite **{$invite->code}** está diferente do que está na base de dados! ({$invite->uses} vs {$db_invite_uses})");

				// Get the invite creator's member id from database using their invite code
				$query = $db->query("SELECT inviter_id FROM invites WHERE code = '{$invite->code}';");
				$inviter_id = $query->fetch_column();

				if ($inviter_id) {
					// Get the invite creator's member object
					$inviter = $guild->members->get("id", $inviter_id);
					$inviter->sendMessage("O número de entradas para o teu convite está diferente do que está registado na base de dados. Por favor, contacta o <@{config->discord->users->owner}> para resolver isto.");
				}
			}
		}
		print("Done!\n\n");
	});

	TimeKeeping::hour(function ($hour) use ($channel_main, $channel_admin) {
		// Check the status of FiveM every hour
		FiveM::Status(function ($online) use ($channel_main) {
			$channel_main->sendMessage($online ? "O FiveM está de volta! :smiley:" : "O FiveM está com algo problema! :weary:");
		});

		switch ($hour) {
			case 00:
				global $db;
				$activity_counter = [];

				// Retrieve counters from database from the previous day
				$query = $db->query("SELECT type, count FROM discord_counters WHERE day = DATE(DATE_SUB(NOW(), INTERVAL 1 DAY));");
				while ($counter = $query->fetch_assoc()) $activity_counter[$counter["type"]] = $counter["count"];

				// Resumir o dia
				$activity_string = "\n**Desenvolvimento**:";
				switch ($activity_counter["dev_messages"]) {
					case 0:
						$activity_string .= "- Nenhuma mensagem no canal desenvolvimento.";
						break;
					case 1:
						$activity_string .= "- Apenas uma mensagem no canal desenvolvimento.";
						break;
					default:
						$activity_string .= "- {$activity_counter["dev_messages"]} mensagens no canal de desenvolvimento.";
						break;
				}

				$activity_string .= "\n**GitHub**:";
				switch ($activity_counter["github"]) {
					case 0:
						$activity_string .= "- Nenhum push de código.";
						break;
					case 1:
						$activity_string .= "- Apenas um push de código.";
						break;
					default:
						$activity_string .= "- {$activity_counter["github"]} pushes de código.";
						break;
				}

				$activity_string .= "\n**Clickup**:";
				switch ($activity_counter["clickup"]) {
					case 0:
						$activity_string .= "- Nenhuma tarefa foi concluída.";
						break;
					case 1:
						$activity_string .= "- Apenas uma tarefa concluída.";
						break;
					default:
						$activity_string .= "- {$activity_counter["clickup"]} tarefas concluídas hoje.";
						break;
				}

				$activity_string .= "\n**Administração**:";
				switch ($activity_counter["admin_messages"]) {
					case 0:
						$activity_string .= "- Nenhuma mensagem de administração.";
						break;
					case 1:
						$activity_string .= "- Uma mensagem de administração.";
						break;
					default:
						$activity_string .= "- {$activity_counter["admin_messages"]} mensagens de administração enviadas.";
						break;
				}

				$channel_main->sendMessage("**Resumo do dia**:\n{$activity_string}");

				// Init the counters for the next day
				$db->query(
					"INSERT INTO discord_counters (type) VALUES ('dev_messages');
					INSERT INTO discord_counters (type) VALUES ('github');
					INSERT INTO discord_counters (type) VALUES ('clickup');
					INSERT INTO discord_counters (type) VALUES ('admin_messages');"
				);

				/* Admin Reset
				Remove all admin roles from all members
				Send them a private message checking if they are going to be present in the next 24 hours
				If they are, give them the admin role back
				*/
				/*global $guild;
				$channel_admin->sendMessage("A efetuar reset de admins...");
				
				$admins = $guild->members->filter(function ($member) { return $member->roles->has(config->discord->roles->admin); });
				foreach ($admins as $admin) {
					// Don't remove owner
					if ($admin->user->bot || $admin->id == config->discord->users->owner) continue;

					print("Removing admin role from " . $admin->username . " (" . $admin->id . ")\n");

					$admin->removeRole(config->discord->roles->admin);
					$admin->sendMessage(
						"O teu cargo de administração foi removido. Se planeares estar presente nas próximas 24 horas, clica no Emoji.\n
						Se clicares no Emoji e não estiveres presente, serás removido do cargo de administração permanentemente."
					)->then(
						function ($message) use ($admin) {
							$message->react("✅")->done(function () use ($message, $admin) {
								// Collect the reaction from the admin
								$collector = $message->createReactionCollector(function (MessageReaction $reaction) {
									return $reaction->emoji->name == "✅";
								}, [
									"time" => 604800000,   // Wait a week for a response
									"max"  => 1            // Only one reaction allowed
								]);

								// If admin didn't react after a week, send a message to the admin channel and alert the admin that they were removed from the admin role
								$collector->on("end", function ($reactions) use ($admin) {
									if (count($reactions)) return; // admin reacted, don't do anything

									global $channel_admin;
									$channel_admin->sendMessage("$admin não respondeu ao pedido de presença passado uma semana. Foi removido permanentemente.");
									$admin->sendMessage("Não respondeste ao pedido de presença passado uma semana. Foste removido permanentemente do cargo de administração.");
									// Remove Administrador or Moderador role if they have it
									if ($admin->roles->has("1038206722969452644")) $admin->removeRole("1038206722969452644"); // Administrador
									if ($admin->roles->has("1038205676503171132")) $admin->removeRole("1038205676503171132"); // Moderador
								});

								// If the user reacts, give them the admin role back
								$collector->once("collect", function (MessageReaction $reaction) use ($admin) {
									$admin->addRole(config->discord->roles->admin);
									$admin->sendMessage("O teu cargo de administração foi restaurado.")->then(function ($message) {
										$message->delete(60);
									});
									$reaction->message->delete(60); // Delete the original message after 60 seconds
								});
							});
						},
						function ($error) use ($admin) {
							global $channel_admin;
							echo "Error: $error";
							$channel_admin->sendMessage("Erro ao enviar mensagem de presença para $admin. $error");
						}
					);
				} */

				// Verify from the database who was last online over a week ago and remove them from the admin role if they were
				/* $admins = $db->query("SELECT * FROM discord_members WHERE last_active < DATE_SUB(NOW(), INTERVAL 1 WEEK);");
				$admins = $admins->fetch_all(MYSQLI_ASSOC);

				if (count($admins)) {
					$channel_admin->sendMessage("A remover cargos de administração de utilizadores que não estiveram ativos há mais de uma semana...");
					foreach ($admins as $admin) {
						$member = $guild->members->get("id", $admin["id"]);
						if (!$member) continue; // Member left the server

						$member->removeRole(config->discord->roles->admin);
						$member->sendMessage("Não estiveste presente no servidor durante uma semana. Foste removido do cargo de administração.");
						$channel_admin->sendMessage("$member não esteve presente no servidor durante uma semana. Foi removido do cargo de administração.");
						print("[MEMBER] $member->username#$member->discriminator was removed from the admin role because they were inactive for a week.\n");
					}
				} */

				break;
			case 8:
				global $guild;
				// Remove config->discord->roles->present from everyone that has the config->discord->roles->admin role
				foreach ($guild->roles->get("id", config->discord->roles->admin)->members as $member) $member->removeRole(config->discord->roles->present);

				$channel_main->sendMessage("Bom dia pessoal! :partying_face:");
				$channel_admin->sendMessage("<@&929172055977508924> São agora 8 da manhã seus cabrões. Toca a acordar!\nQuem é que vai marcar presença hoje?")->done(function (Message $message) {
					global $rollcall_message_id;

					$message->react("👍");
					$message->react("👎");

					$rollcall_message_id = $message->id;
				});

				break;
			default: // Send a random joke

				break;
		}
	});
});

// When a member joins the server
$discord->on(Event::GUILD_MEMBER_ADD, function (Member $member, Discord $discord) {
	global $guild, $channel_main;

	$new_member = "$member->username#$member->discriminator";

	print("[JOIN] Member $new_member joined the server.\n");

	$channel_main->sendMessage("Bem-vindo ao servidor, $member!")->done(function (Message $message) {
		$message->react("👋");
	});

	// Loop through all the invites and check against the $invites_uses array to see if an invite was used
	$guild->invites->freshen()->done(function (Collection $invites) use ($discord, $guild, $member, $new_member) {
		global $db, $invites_uses, $channel_admin;

		foreach ($invites as $invite) {
			// Only check invites created by our bot and if the uses count has increased since the last time we checked
			if ($invite->inviter->id == $discord->id && $invite->uses > $invites_uses[$invite->code]) {
				$invites_uses[$invite->code] = $invite->uses; // Update the uses count in the array

				// Get the name of the inviter from the database
				$query = $db->query("SELECT m.username FROM invites i INNER JOIN discord_members m ON i.inviter_id = m.id WHERE i.code = '$invite->code';");
				$inviter_name = $query->fetch_column();

				// Save to the database from which invite the new member joined
				$db->query("INSERT INTO invites_used (member_id, code) VALUES ('$member->id', '$invite->code')");

				// Get the Member object of the inviter, using $inviter_name, so we can send him a message afterwards
				$guild->members->get("username", $inviter_name)->done(function (Member $inviter) use ($new_member) {
					$inviter->sendMessage("O utilizador $new_member entrou no servidor através do teu convite.");
				});

				$channel_admin->sendMessage("O utilizador **$new_member** foi convidado por **$inviter_name** através do convite **$invite->code**.");

				break;
			}
		}
	});
});

// Creating Invites
$discord->on(Event::INVITE_CREATE, function (Invite $invite, Discord $discord) {
	global $channel_admin;

	// Delete invites that are not created by our bot or VIRUXE
	if ($invite->inviter->id != $discord->id && $invite->inviter->id != config->discord->users->viruxe) {
		$channel_admin->sendMessage("O utilizador <@{$invite->inviter->id}> tentou criar um convite ($invite->code).");
		$invite->guild->invites->delete($invite);
	}
});

// Any actual message in the guild
$discord->on(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) {
	global $db, $channel_admin;

	// With this it doesn't matter if it was a bot or not
	// Get the channel the message was sent in, so we can increment the activity counter for that channel
	$counter_type = NULL;

	switch ($message->channel_id) {
		case !$message->author->bot && config->discord->channels->desenvolvimento:
			$counter_type = "dev_messages";
			break;
		case !$message->author->bot && config->discord->channels->admin:
			$counter_type = "admin_messages";
			break;
		case config->discord->channels->clickup:
			$counter_type = "clickup";
			break;
		case config->discord->channels->github:
			$counter_type = "github";
			break;
	}

	// If the channel is one of the ones we want to track, then increment the counter
	if ($counter_type) $db->query("UPDATE discord_counters SET count = count + 1 WHERE type = '$counter_type' AND day = DATE(NOW());");

	// Ignore messages from bots
	if (!$message->author->bot) {
		// Check for bad words
		if (BadWords::Scan($message)) {
			$channel_admin->sendMessage("Eliminei uma mensagem de '{$message->author->username}' no '{$message->channel->name}' por utilizar uma palavra banida: - `$message->content`");
		}

		\Member::SetLastActive($message->member);

		// Set a Member to not being AFK if they send a message
		AFK::set($message->member, false);

		/* 
			See if someone mentioned someone, and if they did, check if the mentioned user is AFK.
			If the mentioned user is AFK then send a message to that channel saying the reason why they are AFK.
		*/
		if (preg_match_all("/<@!?(\d+)>/", $message->content, $matches)) {
			foreach ($matches[1] as $id) {
				$member = $message->guild->members->get("id", $id);
				print("Member $member->username#$member->discriminator was mentioned in message $message->id by {$message->author->username}#{$message->author->discriminator}.\n");

				if ($member == NULL || !$member->roles->has(config->discord->roles->afk)) continue; // If the member is not in the server or is not AFK, then skip

				$is_afk = AFK::get($member); // If true then member didn't set a reason, if string then member set a reason

				if ($is_afk) {
					$reason = gettype($is_afk) !== "string" ? "Razão desconhecida." : $is_afk;
					$message->channel->sendMessage("O membro **{$member->username}** está AFK. **Razão**: `$reason`");
				}
			}
		}
	}

	// Detect if user sent an image
	/* if (count($message->attachments) > 0) {
		$activity_counter["images"]++;
		print("Images: {$activity_counter["images"]}\n");
	} */
});

$discord->on(Event::MESSAGE_REACTION_ADD, function (MessageReaction $reaction, Discord $discord) {
	if ($reaction->user->bot) return;

	global $channel_admin, $rollcall_message_id;

	// Check if the reaction was on a greeting message from the bot and if the user reacted with the 👋 emoji, then send a message to the channel
	// ! Fuck this shit. Doesn't work. I'm not going to waste more time on this.
	if ($reaction->emoji->name == "👋" && $reaction->channel->id == config->discord->channels->main && $reaction->user->bot) {
		$mentioned_member = $reaction->message->mentions->first();

		$reaction->channel->sendMessage("{$reaction->user} dá-te as boas-vindas $mentioned_member! :wave:");
	}

	// Check if the reaction was on the rollcall message and if the member reacted with the correct emojis or not
	if ($reaction->message_id == $rollcall_message_id) {
		if ($reaction->emoji->name == "👍") { // If the reaction is a thumbs up
			$replies = [
				"%s ok ok, vou querer ver trabalho então",
				"Fantástico %s! Espero ver trabalho feito daqui a umas horas",
				"Vai lá %s, não te esqueças de fazer o trabalho",
				"%s, não te esqueças de marcar presença no ClickUp!",
				"Vai lá %s, que eu sei que consegues!",
				"%s ok ok, vamos lá ver se não te vais embora",
				"%s ok ok, não me quero queixar de nada",
				"Obrigado %s, agora é que é!",
				"Certo %s, fala aí com o resto do pessoal para ver quais são as tarefas para hoje",
				"Vou querer ver trabalho %s",
				"Porra, %s, que bom ver-te por aqui",
				"Queres mesmo trabalhar %s? 😳",
				"Trabalho, trabalho, trabalho... %s",
				"Vamos lá %s, não te quero ver a dormir",
				"Vou querer ver trabalho %s, mas não te esqueças de descansar também!",
				"Quem é que vai marcar presença hoje? %s",
				"O que é que o %s vai fazer hoje? 🤔",
				"Já estás atrasado %s. Vai-te foder",
				"Trabalho feito %s? Espero que sim!",
				"Boa %s, agora é trabalhar",
				"Vai-te foder %s.",
				"Já estás atrasado %s",
				"%s está presente!",
				"O %s está presente!",
				"Ó %s, calma lá, não te esqueças de comer",
				"Ó %s, não te esqueças de beber água",
				"Ó %s, não te esqueças de ir à casa de banho",
				"Ó %s, não te esqueças de respirar",
				"Ó %s, não te esqueças de dormir",
				"Ó %s, não te esqueças de beber café",
				"Ó %s, não te esqueças de fazer exercício",
				"Ok %s, vamos a isso então! Toca a mostrar trabalho",
				"Tranquilo %s, vamos lá meter mãos a obra",
				"Ok %s, vamos lá ver se hoje é o dia em que vais fazer alguma coisa",
				"Ok %s, vamos lá ver se hoje é o dia em que vais fazer alguma coisa de jeito",
				"Ok %s, vamos lá ver se hoje é o dia em que vais fazer alguma coisa de jeito e que não seja só copiar e colar",
				"Ok %s, vamos lá ver se hoje é o dia em que vais fazer alguma coisa de jeito e que não seja só copiar e colar de um site qualquer"
			];

			$channel_admin->sendMessage(sprintf($replies[rand(0, count($replies) - 1)] . ". :handshake:", $reaction->member));

			// Remove bot's reaction if there is more than 1 reaction
			if (count($reaction->message->reactions) == 2) {
				$reaction->message->deleteReaction(Message::REACT_DELETE_ME, $reaction->emoji); // Remove the bot's reaction
			}
		} elseif ($reaction->emoji->name == "👎") { // If the user reacted with a thumbs down
			$channel_admin->sendMessage("Tranquilo {$reaction->member}, vemos-te amanhã então. :wave:");
			// Remove bot's reaction if there is more than 1 reaction
			if (count($reaction->message->reactions) == 2) {
				$reaction->message->deleteReaction(Message::REACT_DELETE_ME, $reaction->emoji); // Remove the bot's reaction
			}
		} else { // If the reaction is not 👍 or 👎
			$reaction->delete()->done(function () use ($channel_admin, $reaction) {
				$channel_admin->sendMessage("$reaction->member para quieto fdp. Estás-te a armar quê? Push, queres é festa.");
			});
		}
	}
});

$discord->on(Event::INTERACTION_CREATE, function (Interaction $interaction, Discord $discord) {
	switch ($interaction->type) {
		case InteractionType::PING:
			$interaction->acknowledge()->done(function () use ($interaction) {
				// $interaction->reply("Pong!");
				print("Pong!\n");
			});
			break;
		case InteractionType::APPLICATION_COMMAND:
			switch ($interaction->data->id) {
				case 1032023987250794566: // Mandar calar
					$nuances = ["foda o focinho", "foda os cornos", "leves um biqueiro nos cornos", "te abafe todo", "te meta o colhão na virilha"];
					$nuance = $nuances[rand(0, count($nuances) - 1)];

					$message = $interaction->data->resolved->messages->first();
					$insult = Words\getInsult();
					$message->reply("Tu cala-te $insult do caralho, antes que $nuance!");

					$interaction->acknowledgeWithResponse();
					$interaction->deleteOriginalResponse();
					break;
				case 1031932276717662260: // Criar Sugestão
					$data    = $interaction->data->resolved;
					$message = $data->messages->first();
					$author  = $message->author;

					if (strlen($message->content) < 50) {
						$interaction->respondWithMessage(MessageBuilder::new()->setContent("Opá achas que isso é uma sugestão de jeito? Pega em algo com mais conteúdo caralho."), true);
						return;
					}

					$interaction->showModal(
						"Criar Sugestão para $author->username",
						"feedback",
						[
							ActionRow::new()->addComponent(
								TextInput::new("Título", TextInput::STYLE_SHORT, "title")
									->setRequired(true)
									->setPlaceholder("Exemplo: Equilibrar os preços dos Veículos.")
									->setMinLength(10)
									->setMaxLength(100)
							),
							ActionRow::new()->addComponent(
								TextInput::new("Sugestão", TextInput::STYLE_PARAGRAPH, "message")
									->setRequired(true)
									->setValue($message->content)
									->setMinLength(50)
							)
						],
						function (Interaction $interaction, $components) use ($author) {
							// Create the forum thread
							$forum = $interaction->guild->channels->get("id", 1019697596555612160);

							$forum->startThread([
								"name" => $components["title"]->value,
								"message" => MessageBuilder::new()->setContent(
									"Clica no 👍🏻 se concordas com esta sugestão e deixa o teu comentário. Valorizamos a tua opinião!\n\n"
										. "Sugestão feita por $author:\n>>> {$components["message"]->value}"
								),
								"applied_tags" => ["1031013313594802237"]
							])->done(function (Thread $thread) use ($interaction) {
								print("Suggestion '$thread->name' created successfully.\n");
								$interaction->respondWithMessage(MessageBuilder::new()->setContent("Tópico de Sugestão $thread criado com sucesso."), true);
							});
						}
					);
					break;
			}

			break;
		case InteractionType::MESSAGE_COMPONENT:
			$interaction->acknowledge()->done(function () use ($interaction) {
				print("Message component received!\n");
			});
			break;
		case InteractionType::APPLICATION_COMMAND_AUTOCOMPLETE:
			$interaction->acknowledge()->done(function () use ($interaction) {
				print("Autocomplete received!\n");
			});
			break;
		case InteractionType::MODAL_SUBMIT:
			$interaction->acknowledge()->done(function () use ($interaction) {
				print("Modal submit received!\n");
			});
			break;
	}
});

$discord->on(Event::PRESENCE_UPDATE, function (PresenceUpdate $presence, Discord $discord) {
	if ($presence->user->bot) return;

	global $game_sessions;
	static $member_status = [];
	$member = $presence->member;

	// Handle status updates
	if (!array_key_exists($member->id, $member_status)) {
		// print("Setting status '$member->status' for '$member->username'.\n");
		$member_status[$member->id] = $member->status;

		return;
	} else { // We already have a previous status saved
		$prev_status = $member_status[$member->id];
		$curr_status = $member->status;

		if ($prev_status != $curr_status) {
			if ($curr_status == "idle") { // User is AFK
				// Kick out of any voice channel if AFK
				// TODO: Only do it if the member is not on a mobile

				// if ($member->getVoiceChannel()) $member->moveMember(NULL, "Became AFK."); // Remove member from the voice channels if they become AFK
			} elseif ($curr_status == "offline") { // User is now offline (or invisible) so update the last seen time in the database
				\Member::SetLastOnline($member);
			}

			// print("'$member->username' updated status: '$prev_status' -> '$curr_status'\n");
			$member_status[$member->id] = $curr_status; // Update the status

			return;
		} else {
			// print("'$member->username' updated their presence, other than the status.\n");
		}
	}

	// Handle game sessions
	$game = $presence->activities->filter(fn ($activity) => $activity->type == Activity::TYPE_GAME)->first();

	if ($game) { // Playing a game
		// $game_sessions->open($member, $game);

		// Check if they are playing on our server or not
		if ($game->name == config->server->name || $game?->state == config->server->name) { // Playing on our server
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

$discord->listenCommand("adminactivity", function (Interaction $interaction) {
	// Send a list of the time everyone with the admin role was last active, to the admin channel
	$message_string = "**Última atividade da Equipa**:\n";
	foreach (Admin::GetAdmins() as $admin) {
		$last_online = $admin["last_online"] ?? "Nunca";
		$last_online_over_a_day = strtotime($last_online) < strtotime("-1 day");
		$last_active = $admin["last_active"] ?? "Nunca";
		$last_active_over_a_day = strtotime($last_active) < strtotime("-1 day");
		// Was admin not active but was online?
		$last_online_but_not_active = $last_online_over_a_day && !$last_active_over_a_day;
		// Show an :x: emoji if the admin was online but not active, a :warning: emoji if they were not online during that period and a :white_check_mark: emoji if they were active
		$emoji = $last_online_but_not_active ? ":warning:" : ($last_active_over_a_day ? ":x:" : ":white_check_mark:");
		// $admin_string = $last_online_but_not_active ? "<@$admin[id]>" : $admin["username"];
		// $message_string .= "**$admin_string**: $last_active $emoji\n";
		$message_string .= "**$admin[username]**: $last_active $emoji\n";
	}

	$interaction->respondWithMessage(MessageBuilder::new()->setContent($message_string), false);

	return true;
});

$discord->listenCommand("rollcall", function (Interaction $interaction) use (&$rollcall_message_id) {
	if ($rollcall_message_id) return;

	global $channel_admin;

	$channel_admin->sendMessage("<@&929172055977508924> Como é meus meninos?! Quem é que vai marcar presença hoje?")->done(function (Message $message) use (&$rollcall_message_id) {
		$message->react("👍");
		$message->react("👎");

		$rollcall_message_id = $message->id;
	});

	$interaction->acknowledgeWithResponse();
	$interaction->deleteOriginalResponse();
});

$discord->listenCommand('convite', function (Interaction $interaction) {
	global $db;

	$username = $interaction->user->username;
	$inviter_slug = Words\slugify($username);

	// Check if Member already has an invite code for himself
	$query = $db->query("SELECT code FROM invites WHERE inviter_id = '{$interaction->user->id}';");
	if ($query->num_rows > 0) {
		$interaction->respondWithMessage(MessageBuilder::new()->setContent("Olá $username, este é o teu link de convite: http://opadrinhoroleplay.pt/convite.php?slug=$inviter_slug"), true);
	} else { // Member doesn't have an invite code yet
		// Create an Invite so we can get the code
		global $guild;
		$guild->channels->get("id", config->discord->channels->main)->createInvite([
			"max_age"   => 0,
			"max_uses"  => 0,
			"temporary" => false,
			"unique"    => true
		], "Codigo de Convite para '$username'")->done(function (Invite $invite) use ($interaction, $db, $username, $inviter_slug) {
			// Check if the member already exists in the database, if not, create a new one
			if (!\Member::Exists($interaction->member)) \Member::Create($interaction->member);

			// Get the code and insert it into the database
			$invite_insert = $db->query("INSERT INTO invites (code, inviter_id, inviter_slug) VALUES ('$invite->code', '{$interaction->user->id}', '$inviter_slug')");
			if ($invite_insert === TRUE) {
				global $channel_admin;
				$invite_url = "http://opadrinhoroleplay.pt/convidar.php?slug=$inviter_slug";
				$interaction->respondWithMessage(MessageBuilder::new()->setContent("Olá $username, este é o teu link de convite: $invite_url"), true);
				$channel_admin->sendMessage("O utilizador **$username** criou um convite. (Slug: '$inviter_slug')");
			} else {
				$interaction->respondWithMessage(MessageBuilder::new()->setContent("Ocorreu um erro ao gerar o teu código de convite! Fala com o <@{config->discord->users->owner}>"), true);
			}
			return;
		});
	}
});

// Check the bot's uptime
$discord->listenCommand('uptime', function (Interaction $interaction) use ($start_time) {
	$uptime = $start_time->diff(new DateTime());
	$uptime_string = $uptime->format("%a dias, %h horas, %i minutos e %s segundos");

	$interaction->respondWithMessage(MessageBuilder::new()->setContent("Estou online a $uptime_string"), false);
});

/* 
	Set's the member's AFK status. Useful for other when other member's tag them in a message.
	The bot will check if the member is AFK and if so, it will send a message to the channel where the member was tagged. To remind the other member that the tagged member is AFK.
 */
$discord->listenCommand('afk', function (Interaction $interaction) {
	global $afk, $channel_main, $channel_admin;

	$member  = $interaction->member;
	$is_afk  = AFK::get($member);
	$message = null;

	if ($interaction->data->options) { // Member provided a reason so set them AFK with one
		$reason = $interaction->data->options["razao"]->value;
		AFK::set($member, true, $reason);

		if ($is_afk) { // Member is already AFK
			$message = "**$member->username** actualizou a sua razão de **AFK** para: `$reason`";
		} else { // Member is not AFK
			$message = "**$member->username** ficou agora **AFK**: `$reason`";
		}
	} else { // No reason provided
		if (!$is_afk) { // Member is not AFK so we set them AFK, without a reason
			AFK::set($member, true);
			$message = "**$member->username** ficou agora **AFK**";
		} else {
			AFK::set($member, false); // Remove the AFK status
			$message = "**$member->username** voltou de **AFK**";
		}
	}

	// Send a message to channels
	$channel_main->sendMessage("$message.");
	if (IsMemberAdmin($member)) $channel_admin->sendMessage("$message.");

	// $interaction->respondWithMessage(MessageBuilder::new()->setContent($is_afk ? _U("afk", "self_not_afk") : _U("afk", "self_afk")), true);
	$interaction->acknowledgeWithResponse();
	$interaction->deleteOriginalResponse();
});

$discord->on(Event::VOICE_STATE_UPDATE, function (VoiceStateUpdate $newState, Discord $discord, $oldState) {
	global $channel_admin, $channel_log_voice;

	$member = $newState->member;

	// Don't let the member move to the lobby channel, unless he's an admin
	if (!IsMemberAdmin($member) && IsMemberIngame($member) && $newState->channel_id == config->discord->channels->voice->discussion) {
		$member->moveMember($oldState->channel?->id ?? config->discord->channels->voice->lobby, "Tentou voltar para a Discussão Geral.");
		$member->sendMessage("Não podes voltar para Discussão Geral enquanto estiveres a jogar.");
	}

	// First time joining a voice channel
	if (!$oldState?->channel) {
		$channel_log_voice->sendMessage("**$member->username** entrou no canal de voz **$newState->channel**.");

		// Send messages if member is not an admin
		if (!IsMemberAdmin($member)) {
			if ($newState->channel_id == config->discord->channels->voice->discussion) { // If member joined the main discussion voice channel
				$member->sendMessage("Olá! Este canal de voz é para conversas gerais, enquanto não estas a jogar. Se quiseres um canal privado, para ti e para os teus amigos/equipa utiliza o comando `/voz`.");
			} elseif ($newState->channel_id == config->discord->channels->voice->lobby) { // If member joined the lobby voice channel
				$member->sendMessage("Olá! Cria um canal de voz privado para ti e para os teus amigos/equipa utilizando o comando `/voz`. Não é suposto ficar aqui a conversar com os outros membros.");
			}
		} else { // Member is an admin
			if ($newState->channel_id == config->discord->channels->voice->admin) { // If member joined the admin voice channel
				$channel_admin->sendMessage("**$member->username** entrou no canal de voz de Administração $newState->channel.");
			}
		}
	} else { // Was already in a voice channel
		// if($newState->channel_id == $oldState->channel_id) return; // Member didn't change channels. We don't need to do anything

		// Left the voice channel
		if (!$newState->channel) {
			$channel_log_voice->sendMessage("**$member->username** saiu do canal de voz **$oldState->channel**");
		} else { // Moved to another voice channel
			$channel_log_voice->sendMessage("**$member->username** saiu do canal de voz **$oldState->channel** e entrou no canal de voz **$newState->channel**");
		}
	}
});

$discord->listenCommand('voz', function (Interaction $interaction) {
	$member         = $interaction->member;
	$options        = $interaction->data->options;
	$member_channel = GetMemberVoiceChannel($member);

	// Get allowed members from interaction arguments
	if (!preg_match_all('<@([0-9]+)>', $options["membros"]->value, $matches)) {
		$interaction->respondWithMessage(MessageBuilder::new()->setContent("Tens que especificar/mencionar (@membro) pelomenos um membro do Discord para fazer parte do teu canal."), true);
		return;
	}

	$channel_members = [];

	foreach ($matches[1] as $member_id) {
		if ($member_id == $member->id) continue;

		$member_object = $interaction->guild->members->get("id", $member_id);

		if ($member_object) $channel_members[] = $member_object;
	}

	// Check if anyone was mentioned
	if (!count($channel_members)) {
		$interaction->respondWithMessage(MessageBuilder::new()->setContent("Não consegui identificar algum Membro. Tens que '@mencionar' cada um deles."), true);
		return;
	}

	// Member has a channel for themselves already, so let's edit that instead
	if ($member_channel) {
		// Grab the Channel object first
		$member_channel = $interaction->guild->channels->get("id", $member_channel);

		// Set a new name if one was provided
		if ($options["nome"]) $member_channel->name = Words\slugify($options["nome"]->value);

		// Delete all members, minus owner
		foreach ($member_channel->overwrites as $part) {
			if ($part->type != 1) continue; // Ignore whatever is not a Member
			if ($part->id == $member->id) continue; // Don't remove owner perms

			$member_channel->overwrites->delete($part);
		}

		// Add new members
		foreach ($channel_members as $channel_member) {
			$member_channel->setPermissions($channel_member, ['connect', 'use_vad']);
			$channel_member->sendMessage("$member autorizou-te a entrar no Canal de Voz Privado '$member_channel->name'.");
		}

		if ($member->getVoiceChannel()) $member->moveMember($member_channel->id); // Move the Member who executed the command.
		$interaction->respondWithMessage(MessageBuilder::new()->setContent("Alteraste o teu Canal de Voz Privado: $member_channel."), true);
	} else { // Member doesn't have a channel, so let's create one
		// Create the Channel Part
		$new_channel = $interaction->guild->channels->create([
			"parent_id" => 1030787112628400198, // 'Voz' Category
			"name" => $options["nome"] ? Words\slugify($options["nome"]->value) : Words\generateWhatThreeWords(),
			"type" => Channel::TYPE_GUILD_VOICE,
			"bitrate" => 96000
		]);

		// Submit the part
		$interaction->guild->channels->save($new_channel, "Canal de Voz para '$member->username'")->done(
			function (Channel $channel) use ($interaction, $member, $channel_members) {
				global $channel_admin;

				print("Created a new Voice Channel: '$channel->name' Members: ");
				$channel_admin->sendMessage("$member criou um novo Canal de Voz Privado: $channel");

				// Set permissions for each member and send them a message
				foreach ($channel_members as $channel_member) {
					$channel->setPermissions($channel_member, ['connect', 'use_vad']);
					$channel_member->sendMessage("$member autorizou-te a entrar no Canal de Voz Privado '$channel->name'.");
					print("'$channel_member->username' ");
				}
				print("Owner: ");

				$channel->setPermissions($member, ['connect', 'use_vad', 'priority_speaker', 'mute_members']);
				print("'$member->username'\n");
				if ($member->getVoiceChannel()) $member->moveMember($channel->id); // Move the Member who executed the command.
				$interaction->respondWithMessage(MessageBuilder::new()->setContent("Criei o Canal $channel para ti e para os teus amigos."), true);
			},
			function ($error) {
				print("Impossivel criar canal privado.\n$error\n");
			}
		);
	}
});

$discord->listenCommand('trivia', function (Interaction $interaction) {
	global $trivia;

	$interaction->respondWithMessage(MessageBuilder::new()->setContent("Vamos lá então a um jogo de **Trívia** sobre _Roleplay_! Quero ver quem é que percebe desta merda."));
	$trivia = new Trivia($interaction->channel);
});

// Listen to the command 'fivem' to check the status
$discord->listenCommand('fivem', function (Interaction $interaction) {
	$fivem_status = FiveM::Status();

	if ($fivem_status != null) $interaction->respondWithMessage(MessageBuilder::new()->setContent("**Estado actual do FiveM**: " . (FiveM::Status() ? 'Online' : 'Offline')));
});

$discord->run();

function SetMemberIngame(Member $member, bool $toggle): bool
{
	$is_ingame = IsMemberIngame($member);

	if ($is_ingame === $toggle) return false;

	global $channel_admin;

	if ($toggle) {
		$member->addRole(config->discord->roles->ingame, "Entrou no Servidor."); // Set the AFK role
		if ($member->getVoiceChannel() && !IsMemberAdmin($member)) $member->moveMember(config->discord->channels->voice->lobby, "Entrou no Servidor."); // Move member to the in-game channel when in-game
	} else {
		$member->removeRole(config->discord->roles->ingame, "Saiu do Servidor.");
		if ($member->getVoiceChannel() && !IsMemberAdmin($member)) $member->moveMember(config->discord->channels->voice->discussion, "Saiu do Servidor."); // Move member to the voice lobby if not in-game anymore
	}

	$channel_admin->sendMessage("**$member->username**" . ($toggle ? " entrou no servidor." : " saiu do servidor."));

	return true;
}

function IsMemberAdmin(Member $member): bool
{
	return $member->roles->get("id", config->discord->roles->admin) ? true : false;
}

function IsMemberIngame(Member $member): bool
{
	return $member->roles->get("id", config->discord->roles->ingame) ? true : false;
}

function GetMemberVoiceChannel(Member $member): string|null
{
	global $guild;

	foreach ($guild->channels as $channel) {
		if ($channel->parent_id != 1030787112628400198) continue; // Other categories
		if ($channel->id == 1019237971217612840) continue; // Lobby

		// Loop through permissions
		foreach ($channel->permission_overwrites as $permission) {
			if ($permission->type != 1) continue; // Ignore whatever is not a Member
			if ($permission->id == $member->id) return $channel->id;
		}
	}

	return NULL;
}
