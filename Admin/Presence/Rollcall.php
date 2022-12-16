<?php
namespace Admin\Presence\Rollcall;

use Discord\Parts\User\Member;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\WebSockets\MessageReaction;

enum RollcallPresence: string {
    case Yes   = "👍";
    case No    = "👎";
    case Maybe = "🤷";

    static function getValues(): array {
        return array_map(function ($value) {
            return $value->value;
        }, self::getConstants());
    }

    static function getConstants(): array {
        return (new \ReflectionClass(self::class))->getConstants();
    }

    static function coerce(string $value): self {
        foreach (self::getConstants() as $constant) {
            if ($constant->value == $value) return $constant;
        }
    }
}

class RollcallMessage
{
    private readonly Channel $admin_channel;
    public Message $message;
    public array $presences = [];

    // @param string $rollcall The JSON-encoded rollcall data
    public function __construct(string $rollcall = null)
    {
        $this->admin_channel = $GLOBALS["channel_admin"];
        
        // If a rollcall message was already sent today, load it to create the object
        if ($rollcall) {
            $rollcall = json_decode($rollcall, false); // It's JSON, so decode it

            if($rollcall?->message_id) {
                // Get the Message object from the message ID
                $this->admin_channel->messages->fetch($rollcall->message_id)->done(function (Message $message) {
                    print("Loaded rollcall message from database\n");
                    $this->message = $message;
                    $this->reactions = $message->reactions;

                    // Add any missing reactions
                    foreach (RollcallPresence::getValues() as $presence) {
                        if (!$this->message->reactions->has($presence)) {
                            $this->message->react($presence);
                        }
                    }
                    
                    // Sync the current message reactions with the database, just in case the bot was offline
                    foreach ($this->message->reactions as $reaction) {
                        // Ignore the bot's presences
                        if ($reaction->me) continue;
                        // Ignore reactions that are not the ones in RollcallPresence
                        if (!in_array($reaction->emoji->name, RollcallPresence::getValues())) continue;
                        // Ignore if member already reacted with something else
                        if (isset($this->presences[$reaction->member->user->id])) continue;
                        
                        // Add the user to the array
                        $this->presences[$reaction->member->user->id] = RollcallPresence::coerce($reaction->emoji->name);
                    }

                    // Create the reaction collector
                    $this->_createReactionCollector();
                }, function () {
                    // If the message wasn't found, send a new one
                    print("Couldn't find rollcall message in database, sending a new one\n");
                    $this->_sendMessage();
                });
            } else {
                // No message ID was passed, so we need to send a new one
                print("No message ID was passed, sending a new one\n");
                $this->_sendMessage();
            }
        } else { // No rollcall data was passed so that means we didn't send one yet
            print("No rollcall data was passed, sending a new one\n");
            $this->_sendMessage();
        }
    }

    private function _sendMessage()
    {
        $this->admin_channel->sendMessage("<@&929172055977508924> Quem é que vai marcar presença hoje?")->done(function (Message $message) {
            $this->message = $message;

            // Add the reactions
            foreach (RollcallPresence::getValues() as $presence) {
                $this->message->react($presence);
            }

            $this->_createReactionCollector();

            $this->_save(); // Save the message ID to the database
        });
    }

    private function _reply(RollcallPresence $presence, Member $member) {
        $replies = [
            RollcallPresence::Yes->value => [
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
            ],
            RollcallPresence::No->value => [
                "Fdx és um merdas %s",
                "Ya ya %s, já sei que não vais fazer nada",
                "Vai-te foder %s",
                "Vai-te foder %s, não te quero ver por aqui",
                "Tass bem %s, pode ser que amanhã seja melhor",
                "Opa siga %s, não te quero ver por aqui",
                "Vê se te pôes no caralho então %s",
                "Vê se te pôes no caralho então %s, não te quero ver por aqui",
                "Se fosses era para o caralho %s",
                "És sempre a mesma merda %s",
                "Desilusão do caralho %s",
                "Desilusão do caralho %s, não te quero ver por aqui",
                "Nem sequer me surpreendes %s",
                "Já não me surpreendes %s",	
                "Habito já %s, por isso tranquilo",
                "Já nem dá é para contar contigo %s"
            ],
            RollcallPresence::Maybe->value => [
                "Vai ser um dia de indecisão! %s",
                "wtf %s, não te quero ver por aqui",
                "Ya ya %s, já sei que não vais fazer nada"
            ]
        ];

        $random_reply_index = array_rand($replies[$presence->value]);

        $this->message->channel->sendMessage(sprintf($replies[$presence->value][$random_reply_index], $member));
    }

    private function _createReactionCollector()
    {
        // Collect every reaction and store it in an array
        $this->message->createReactionCollector(function (MessageReaction $reaction) {
            // Ignore the bot's presences
            if ($reaction->member->user->id == $GLOBALS["discord"]->id) return false;
            // Ignore presences that are not the ones in RollcallPresence
            if (!in_array($reaction->emoji->name, RollcallPresence::getValues())) {
                $this->admin_channel->sendMessage("$reaction->member para quieto fdp. Estás-te a armar quê? Push, queres é festa.");
                return false;
            }

            // Ignore if member already reacted with something else
            if (isset($this->presences[$reaction->member->user->id])) {
                $reaction->delete();
                return false;
            }
                        
            // Delete the bot's first reaction
            $reaction->message->deleteReaction(Message::REACT_DELETE_ME, $reaction->emoji->name);

            $presence = RollcallPresence::coerce($reaction->emoji->name);

            // Add the user to the array
            $this->presences[$reaction->member->user->id] = $presence;

            $this->_save();

            $this->_reply($presence, $reaction->member);

            // If we got here, the reaction is valid
            return true;
        }, [
            // Collect presences from the time this message was sent until the end of the day
            "time" => strtotime("tomorrow") - time()
        ]);
    }

    private function _save()
    {
        print("Saving rollcall data to database\n");

        $value = json_encode([
            "message_id" => $this->message->id,
            "presences"  => $this->presences
        ]);

        // Save the message ID and the presences to the database
        $GLOBALS["db"]->query("UPDATE discord_settings SET value = '$value', last_updated = NOW() WHERE name = 'rollcall'");
    }
}
