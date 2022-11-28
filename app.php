<?php

declare(strict_types=1);

include "vendor/autoload.php";
include "config.php";
include "Utils.php";
include "language.php";
include "GameSessions.class.php";
include "TimeKeeping.php";
include "Trivia.php";
include "AFK.php";
include "BadWords.php";

// date_default_timezone_set('Europe/Lisbon');

define("OWNER_ID", 159298655361171456); // VIRUXE

define("GUILD_ID", 519268261372755968);

define("CHANNEL_ADMIN", 641102112981385226);
define("CHANNEL_MAIN", 960555224056086548);

define("CHANNEL_LOG_AFK", 1020745035169415219);
define("CHANNEL_LOG_INGAME", 1019768367604838460);
define("CHANNEL_LOG_VOICE", 1020683057835020358);

define("CHANNEL_VOICE_ADMIN", 1018817931200700436);
define("CHANNEL_VOICE_DISCUSSION", 960557917784920104);
define("CHANNEL_VOICE_LOBBY", 1019237971217612840);

define("ROLE_PRESENT", 1046384929803608114);
define("ROLE_ADMIN", 929172055977508924);
define("ROLE_AFK", 1020313717805699185);
define("ROLE_INGAME", 1020385919695585311);

define("SERVER_NAME", $config->server->name);

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

use Discord\Parts\Interactions\Command\Command;

print("Starting Padrinho\n\n");

$db = new mysqli("p:{$config->database->host}", $config->database->user, $config->database->pass, $config->database->database);

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
$afk                   = new AFKHandler($db);
$invites_uses          = [];

$activity_counter = [
	"dev_messages"   => 0,
	"github"         => 0,
	"clickup"        => 0,
	"admin_messages" => 0,
];

// $game_sessions = new GameSessions($db);

$logger = new Logger('DiscordPHP');
$logger->pushHandler(new StreamHandler('php://stdout', Monolog\Level::Info));

$discord = new Discord([
	'logger'         => $logger,
	'token'          => $config->discord->token,
	'intents'        => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS | Intents::GUILD_PRESENCES | Intents::GUILD_MESSAGES | Intents::MESSAGE_CONTENT,
	'loadAllMembers' => false,
	'storeMessages'  => true
]);

function GetFiveMStatus()
{
	// Scrape https://status.cfx.re/
	// Get the last rect element in the first svg element
	// Get the fill attribute of that element of the rect
	// If it's #05f4a7 then it's online, otherwise it's offline

	$doc = new DOMDocument();
	@$doc->loadHTML(file_get_contents("https://status.cfx.re/"));
	$xpath = new DOMXPath($doc,);
	$rect  = $xpath->query("//svg/rect[last()]")[0]; // Other elements are generated during runtime so this seemed the best bet
	$color = $rect->getAttribute("fill");

	return $color === "#05f4a7" ? true : false;
}

$discord->on('ready', function (Discord $discord) use ($start_time, &$activity_counter) {
	global $guild, $channel_main, $channel_admin, $channel_log_traidores, $channel_log_ingame, $channel_log_voice, $channel_log_afk;

	echo "Bot is ready!", PHP_EOL;

	$discord->updatePresence($discord->factory(\Discord\Parts\User\Activity::class, [
	'name' => 'vocês seus cabrões!',
		'type' => Activity::TYPE_WATCHING
	]));

	$guild                 = $discord->guilds->get("id", GUILD_ID);
	$channel_admin         = $guild->channels->get("id", CHANNEL_ADMIN);
	$channel_main          = $guild->channels->get("id", CHANNEL_MAIN);
	$channel_log_traidores = $guild->channels->get("id", "1026667050489352272");
	$channel_log_afk       = $guild->channels->get("id", CHANNEL_LOG_AFK);
	$channel_log_ingame    = $guild->channels->get("id", CHANNEL_LOG_INGAME);
	$channel_log_voice     = $guild->channels->get("id", CHANNEL_LOG_VOICE);

	// Loop through all the invites, get their uses and build the $invites_uses array
	$guild->invites->freshen()->done(function (Collection $invites) use ($discord) {
		foreach ($invites as $invite) {
			if ($invite->inviter->id != $discord->id) continue; // Only get invites created by our bot
	
			print("Invite {$invite->code} has {$invite->uses} uses");
			$invites_uses[$invite->code] = $invite->uses;
		}
	});

	TimeKeeping::hour(function ($hour) use ($discord, $start_time, $channel_main, $channel_admin) {
		// Check the status of FiveM every hour
		static $fivem = NULL; // 99.97% uptime so yes it's mostly up

		$online = GetFiveMStatus();

		if ($fivem == NULL) { // First hour without having set a first value so we set it now
			// $channel_main->sendMessage("O FiveM encontra-se " . ($online ? "online" : "offline") . "! **Nota**: Monitorizamos o estado do FiveM a cada hora e notificamos se o mesmo se altera.");
			$fivem = $online;
		}

		if ($online) {
			if (!$fivem) {
				$channel_main->sendMessage("O FiveM está de volta! :partying_face:");
				$fivem = true;
			}
		} else {
			if ($fivem) {
				$channel_main->sendMessage("O FiveM ficou offline! :sob:");
				$fivem = false;
			}
		}

		switch ($hour) {
			case 00:
				global $db;
				$activity_counter = [];

				// Retrieve counters from database from the previous day
				$query = $db->query("SELECT type, count FROM discord_counters WHERE day = DATE(DATE_SUB(NOW(), INTERVAL 1 DAY));");
				while ($counter = $query->fetch_assoc()) $activity_counter[$counter["type"]] = $counter["count"];

				// Resumir o dia
				$activity_string = "";

				switch ($activity_counter["dev_messages"]) {
					case 0:
						$activity_string .= "- Nenhuma mensagem de desenvolvimento foi enviada hoje.";
						break;
					case 1:
						$activity_string .= "- Uma mensagem de desenvolvimento foi enviada hoje.";
						break;
					default:
						$activity_string .= "- {$activity_counter["dev_messages"]} mensagens de desenvolvimento foram enviadas hoje. 🥳";
						break;
				}
				$activity_string .= PHP_EOL;

				switch ($activity_counter["github"]) {
					case 0:
						$activity_string .= "- Nenhum commit foi feito hoje.";
						break;
					case 1:
						$activity_string .= "- Um commit foi feito hoje.";
						break;
					default:
						$activity_string .= "- {$activity_counter["github"]} pushes foram feitos hoje. 🥳";
						break;
				}
				$activity_string .= PHP_EOL;

				switch ($activity_counter["clickup"]) {
					case 0:
						$activity_string .= "- Nenhuma tarefa foi concluída hoje.";
						break;
					case 1:
						$activity_string .= "- Uma tarefa foi concluída hoje.";
						break;
					default:
						$activity_string .= "- {$activity_counter["clickup"]} tarefas foram concluídas hoje. 🥳";
						break;
				}
				$activity_string .= PHP_EOL;

				switch ($activity_counter["admin_messages"]) {
					case 0:
						$activity_string .= "- Nenhuma mensagem de administração foi enviada hoje.";
						break;
					case 1:
						$activity_string .= "- Uma mensagem de administração foi enviada hoje.";
						break;
					default:
						$activity_string .= "- {$activity_counter["admin_messages"]} mensagens de administração foram enviadas hoje. 🥳";
						break;
				}

				// Init the counters for the next day
				global $db;
				foreach ($activity_counter as $type => $value) {
					$db->query("INSERT INTO discord_counters (type) VALUES ('$type');");
				}

				$uptime = $start_time->diff(new DateTime());

				if ($uptime < 86400) { // 24 hours
					$uptime_string = $uptime->format("%a dias, %h horas, %i minutos e %s segundos");

					$activity_string .= "\n\n**Ainda não passaram 24 horas ($uptime_string) desde que o bot foi ligado, portanto estas estatísticas não estão completas.**";
				}

				$channel_main->sendMessage("**Resumo do dia**:\n{$activity_string}");
				break;
			case 8:
				global $guild;
				// Remove ROLE_PRESENT from everyone that has the ROLE_ADMIN role
				foreach ($guild->roles->get("id", ROLE_ADMIN)->members as $member) $member->removeRole(ROLE_PRESENT);

				$channel_main->sendMessage("Bom dia pessoal! :partying_face:");
				$channel_admin->sendMessage("<@&929172055977508924> São agora 8 da manhã seus cabrões. Toca a acordar!\nQuem é que vai marcar presença hoje?")->done(function (Message $message) {
					global $rollcall_message_id;

					$message->react("👍");
					$message->react("👎");

					$rollcall_message_id = $message->id;
				});
				break;
			default:
				// Send a random joke
				$chance = rand(1, 100);

				if ($chance > 10) break;

				$ch = curl_init();

				curl_setopt($ch, CURLOPT_URL, "https://evilinsult.com/generate_insult.php?lang=pt&type=json");
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

				$result = curl_exec($ch);
				$result = json_decode($result);

				// Convert html entities in $result->comment to utf-8
				$result->comment = html_entity_decode($result->comment, ENT_QUOTES | ENT_HTML5, 'UTF-8');

				curl_close($ch);

				$channel_main->sendMessage("**$result->insult** - *$result->comment*")->done(function (Message $message) {
					$message->react("😂");
				});

				// $channel_admin->sendMessage("São agora " . date("H:i"));
				break;
		}
	});

	/* 	function GetRandomPortugueseJoke() {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://api.chucknorris.io/jokes/random");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($ch);
		curl_close($ch);

		$json = json_decode($output, true);

		return $json["value"];
	}

	function GetRandomJoke() {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://official-joke-api.appspot.com/random_joke");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($ch);
		curl_close($ch);

		$joke = json_decode($output);

		return $joke->setup . PHP_EOL . $joke->punchline;
	} */


	// include "registerCommands.php";
	/* $discord->application->commands->save(new Command($discord, [
		'name' => 'afk', 
		'description' => 'Define o teu estado de AFK. (Caso alguém te mencione, o bot irá responder com a tua razão de AFK.)',
		"options" => [
			[
				"type"        => 3,
				"name"        => "razao",
				"description" => "Razão pela qual estás AFK.",
				"required"    => false
			]
		]
	])); */
	/* foreach($guild->commands as $command) {
		if($command->type != 3) continue;

		print("Deleting $command->name.\n");
		$guild->commands->delete($command);
	} */

	// $discord->application->commands->delete("1046060312647979179");
	// $guild->commands->delete("1020083011934507141");

	/* $discord->application->commands->save(new Command($discord, [
		"custom_id" => "shutup",
		"name" => "Mandar calar",
		"type" => 3,
	])); */

	/* $discord->application->commands->save(
		new Command($discord, [
			'name' => 'convidar',
			'description' => 'Cria um link de convite poderes convidar os teus amigos.',
			"options" => [
				[
					"type"        => 3,
					"name"        => "utilizador",
					"description" => "Nome e Discriminador do Utilizador. (Exemplo: Utilizador#1234)",
					"required"    => true
				]
			]
		])
	); */

	/* $discord->application->commands->save(new Command($discord, [
		"name" => "convite",
		"description" => "Obtém o teu código de convite, para que os teus amigos possam entrar no Servidor.",
	])); */
});

$discord->on(Event::GUILD_MEMBER_ADD, function (Member $member, Discord $discord) {
	global $guild, $channel_main;
	
	print("Member $member->username#$member->discriminator joined the server.\n");

	$channel_main->sendMessage("Bem-vindo ao servidor, $member! :godfather:")->done(function (Message $message) {
		$message->react("👋");
	});

	// Loop through all the invites and check against the $invites_uses array
	$guild->invites->freshen()->done(function (Collection $invites) use ($member, $discord) {
		global $db, $invites_uses, $channel_admin;

		foreach ($invites as $invite) {
			// Only check invites created by our bot and if the uses count has increased since the last time we checked
			if ($invite->inviter->id == $discord->id && $invite->uses > $invites_uses[$invite->code]) {
				$invites_uses[$invite->code] = $invite->uses;
				$inviter = $invite->inviter;

				$db->query("INSERT INTO invites_used (member_id, code) VALUES ('$member->id', '$invite->code')");

				// Send a message to the invite creator telling them who joined
				$inviter->sendMessage("O utilizador $member->username#$member->discriminator ($member->id) entrou no servidor através do teu convite.");
				$channel_admin->sendMessage("O utilizador **$member->username#$member->discriminator** foi convidado por **$inviter->username#$inviter->discriminator** através do convite $invite->code.");
			}
		}
	});
});

// Creating Invites
$discord->on(Event::INVITE_CREATE, function (Invite $invite, Discord $discord) {
	/* global $channel_admin;

	// Delete invites that are not created by our bot and VIRUXE
	if ($invite->inviter->id != $discord->id && $invite->inviter->id != OWNER_ID) {
		$channel_admin->sendMessage("O utilizador tentou <@{$invite->inviter->id}> criar um convite ($invite->code).");
		$invite->guild->invites->delete($invite);
	} else {
		$channel_admin->sendMessage("<@{$invite->inviter->id}> criou um convite ($invite->code) para o servidor.");
	} */
});

// Any actual message in the guild
$discord->on(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) use ($afk, &$activity_counter) {
	// With this it doesn't matter if it was a bot or not
	// Get the channel the message was sent in, so we can increment the activity counter for that channel
	$counter_type = NULL;

	switch ($message->channel_id) {
		case 1019389839457652776: // #desenvolvimento
			$counter_type = "dev_messages";
			break;
		case CHANNEL_ADMIN:
			$counter_type = "admin_messages";
			break;
		case 1038814705197781044: // #clickup
			$counter_type = "clickup";
			break;
		case 1038958502405754922: // #github
			$counter_type = "github";
			break;
	}

	// If the channel is one of the ones we want to track, then increment the counter
	if($counter_type) {
		global $db;
		$query = $db->query("UPDATE discord_counters SET count = count + 1 WHERE type = '$counter_type' AND day = DATE(NOW());");
		if(!$query) {
			print("Error updating counter: " . $db->error . "\n");
		}
	}

	// Ignore messages from bots
	if ($message->author->bot) {
		
	} else { // If the message was not sent by a bot, then it was sent by a human
		// Check for bad words
		if (BadWords::Scan($message)) {
			global $channel_admin;
			$channel_admin->sendMessage("Eliminei uma mensagem de '{$message->author->username}' no '{$message->channel->name}' por utilizar uma palavra banida: - `$message->content`");
		}

		// Set a Member to not being AFK if they send a message
		$afk->set($message->member, false);

		/* 
			See if someone mentioned someone, and if they did, check if the mentioned user is AFK.
			If the mentioned user is AFK then send a message to that channel saying the reason why they are AFK.
		*/
		if (preg_match_all("/<@!?(\d+)>/", $message->content, $matches)) {
			foreach ($matches[1] as $id) {
				$member = $message->guild->members->get("id", $id);

				if ($member == NULL || !$member->roles->has(ROLE_AFK)) continue; // If the member is not in the server or is not AFK, then skip

				$is_afk = $afk->get($member); // Get the AFK reason

				if ($is_afk) {
					$reason = $is_afk ?? "Burro(a) do caralho não utilizou `/afk`, por isso não sei qual é..";
					$message->channel->sendMessage("<@{$member->id}> está **AFK**. (**Razão**: {$reason}.)");
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
	if ($reaction->member->user->bot) return;

	global $channel_admin, $rollcall_message_id;

	$message         = $reaction->message;
	$message_author  = $message->member->user;
	$reaction_author = $reaction->member;

	// Check if the reaction was on a greeting message from the bot and if the user reacted with the 👋 emoji, then send a message to the channel
	if($reaction->emoji->name == "👋" && $message->channel_id == CHANNEL_MAIN && $message_author->bot) {
		$mentioned_member = $message->mentions->first();
		
		$message->channel->sendMessage("$reaction_author dá-te as boas-vindas $mentioned_member! :wave:");
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

			$reaction->member->addRole(ROLE_PRESENT);
		} elseif ($reaction->emoji->name == "👎") { // If the user reacted with a thumbs down
			$channel_admin->sendMessage("Tranquilo {$reaction->member}, vemos-te amanhã então. :wave:");

			// Remove the present role if the user has it
			if ($reaction->member->roles->has(ROLE_PRESENT)) $reaction->member->removeRole(ROLE_PRESENT);
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
					$insult = getInsult();
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
		print("Setting status '$member->status' for '$member->username'.\n");
		$member_status[$member->id] = $member->status;

		return;
	} else { // We already have a previous status saved
		global $afk;

		$prev_status = $member_status[$member->id];
		$curr_status = $member->status;

		if ($prev_status != $curr_status) {
			if ($curr_status == "idle") {
				$afk->set($member, true);
				// if ($member->getVoiceChannel()) $member->moveMember(NULL, "Became AFK."); // Remove member from the voice channels if they become AFK
			} else $afk->set($member, false);

			print("'$member->username' updated status: '$prev_status' -> '$curr_status'\n");

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
		if ($game->name == SERVER_NAME || $game?->state == SERVER_NAME) { // Playing on our server
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
	$inviter_slug = slugify($username);

	// Check if Member already has an invite code for himself
	$query = $db->query("SELECT code FROM invites WHERE inviter_id = '{$interaction->user->id}';");
	if($query->num_rows > 0) {
		$interaction->respondWithMessage(MessageBuilder::new()->setContent("Olá $username, este é o teu link de convite: http://opadrinhoroleplay.pt/convidar.php?membro=" . $inviter_slug), true);
	} else { // Member doesn't have an invite code yet
		// Create an Invite so we can get the code
		global $guild;
		$guild->channels->get("id", CHANNEL_MAIN)->createInvite([
			"max_age"   => 0,
			"max_uses"  => 0,
			"temporary" => false,
			"unique"    => true
		], "Codigo de Convite para '{$interaction->user->username}'")->done(function (Invite $invite) use ($interaction, $db, $username, $inviter_slug) {
			// Check in the 'discord_members' table if the member already exists. If not, create a new entry
			$query = $db->query("SELECT username FROM discord_members WHERE id = {$interaction->user->id}");
			if($query->num_rows == 0) {
				$db->query("INSERT INTO discord_members (id, username) VALUES ({$interaction->user->id}, '{$interaction->user->username}')");
			}

			// Get the code and insert it into the database
			$invite_insert = $db->query("INSERT INTO invites (code, inviter_id, inviter_slug) VALUES ('$invite->code', '{$interaction->user->id}', '$inviter_slug')");
			if($invite_insert === TRUE) {
				$interaction->respondWithMessage(MessageBuilder::new()->setContent("Olá $username, este é o teu link de convite: http://opadrinhoroleplay.pt/convidar.php?membro=" . $inviter_slug), true);
			} else {
				$interaction->respondWithMessage(MessageBuilder::new()->setContent("Ocorreu um erro ao gerar o teu código de convite! Fala com o <@" . OWNER_ID . ">"), true);
			}
			return;
		});
	}
});

$discord->listenCommand('uptime', function (Interaction $interaction) use ($start_time) {
	$uptime = $start_time->diff(new DateTime());
	$uptime_string = $uptime->format("%a dias, %h horas, %i minutos e %s segundos");

	$interaction->respondWithMessage(MessageBuilder::new()->setContent("Estou online a $uptime_string"), true);
});

$discord->listenCommand('afk', function (Interaction $interaction) {
	global $afk, $channel_main, $channel_admin;

	$member  = $interaction->member;
	$is_afk  = $afk->get($member);
	$message = null;

	if ($interaction->data->options) { // Member provided a reason so set them AFK with one
		$reason = $interaction->data->options["razao"]->value;
		$afk->set($member, true, $reason);

		if ($is_afk) { // Member is already AFK
			$message = "**$member->username** actualizou a sua razão de **AFK** para: `$reason`";
		} else { // Member is not AFK
			$message = "**$member->username** ficou agora **AFK**: `$reason`";
		}
	} else { // No reason provided
		if (!$is_afk) { // Member is not AFK so we set them AFK, without a reason
			$afk->set($member, true);
			$message = "**$member->username** ficou agora **AFK**";
		} else {
			$afk->set($member, false); // Remove the AFK status
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

	$member  = $newState->member;
	$channel = $newState->channel;

	// Don't let the player move to the lobby channel, unless he's an admin
	if (!IsMemberAdmin($member) && IsMemberIngame($member) && $newState->channel_id == CHANNEL_VOICE_DISCUSSION) {
		$member->moveMember($oldState->channel?->id ?? CHANNEL_VOICE_LOBBY, "Tentou voltar para a Discussão Geral.");
		$member->sendMessage("Não podes voltar para Discussão Geral enquanto estiveres a jogar.");
		return;
	}

	if ($channel?->id == CHANNEL_VOICE_ADMIN && !$oldState?->channel) $channel_admin->sendMessage("$member->username entrou no $channel.");

	$channel_log_voice->sendMessage($member->username . ($channel ?  " entrou no canal $channel." : " saiu do canal de voz."));
});

$discord->listenCommand('voz', function (Interaction $interaction) {
	$member  = $interaction->member;
	$options = $interaction->data->options;
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

	if (!count($channel_members)) {
		$interaction->respondWithMessage(MessageBuilder::new()->setContent("Não consegui identificar algum Membro. Tens que '@mencionar' cada um deles."), true);
		return;
	}

	if ($member_channel) { // Member has a channel for themselves already, so let's edit that instead
		// Grab the Channel object first
		$member_channel = $interaction->guild->channels->get("id", $member_channel);

		// Set a new name if one was provided
		if ($options["nome"]) $member_channel->name = slugify($options["nome"]->value);

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
			"name" => $options["nome"] ? slugify($options["nome"]->value) : generateWhatThreeWords(),
			"type" => Channel::TYPE_GUILD_VOICE,
			"bitrate" => 96000
		]);

		// Submit the part
		$interaction->guild->channels->save($new_channel, "Canal de Voz para '$member->username'")->done(
			function (Channel $channel) use ($interaction, $member, $channel_members) {
				print("Created a new Voice Channel: '$channel->name' Members: ");

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

	$member  = $interaction->member;
	$channel = $interaction->channel;

	$interaction->respondWithMessage(MessageBuilder::new()->setContent("Vamos lá então a um jogo de **Trívia** sobre _Roleplay_! Quero ver quem é que percebe desta merda."));
	$trivia = new Trivia($channel);
});

// Listen to the command 'fivem' to check the status
$discord->listenCommand('fivem', function (Interaction $interaction) {
	$interaction->acknowledgeWithResponse()->done(function () use ($interaction) {
		$interaction->respondWithMessage(MessageBuilder::new()->setContent("A verificar o estado do servidor..."));
	}, function ($error) {
		print("Impossivel verificar o estado do servidor.\n$error\n");
	});
	$interaction->respondWithMessage(MessageBuilder::new()->setContent("**Estado actual do FiveM**: " . (GetFiveMStatus() ? 'Online' : 'Offline')));
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

function SetMemberIngame(Member $member, bool $toggle): bool
{
	$is_ingame      = IsMemberIngame($member);
	// print($is_ingame . " " . $toggle);

	if ($is_ingame === $toggle) return false;

	global $channel_admin;

	if ($toggle) {
		$member->addRole(ROLE_INGAME, "Entrou no Servidor."); // Set the AFK role
		if ($member->getVoiceChannel() && !IsMemberAdmin($member)) $member->moveMember(CHANNEL_VOICE_LOBBY, "Entrou no Servidor."); // Move member to the in-game channel when in-game
	} else {
		$member->removeRole(ROLE_INGAME, "Saiu do Servidor.");
		if ($member->getVoiceChannel() && !IsMemberAdmin($member)) $member->moveMember(CHANNEL_VOICE_DISCUSSION, "Saiu do Servidor."); // Move member to the voice lobby if not in-game anymore
	}

	$channel_admin->sendMessage($member->username . ($toggle ? " entrou no servidor." : " saiu do servidor."));

	return true;
}

function IsMemberAdmin(Member $member): bool
{
	return $member->roles->get("id", ROLE_ADMIN) ? true : false;
}

function IsMemberIngame(Member $member): bool
{
	return $member->roles->get("id", ROLE_INGAME) ? true : false;
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
