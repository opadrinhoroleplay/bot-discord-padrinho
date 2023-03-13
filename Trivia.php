<?php

use Discord\Parts\Channel\Channel;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\Components\Option;

class Trivia
{
    private $channel = null;
    private $questions_pool = [];

    private $questions = []; // Our current questions for this instance
    private $current_question = 0;

    function __construct(Channel $channel)
    {
        $file = file_get_contents("quiz.json");

        if (!$file) {
            print("Unable to locate 'quiz.json'.");
            return;
        }

        $this->questions_pool = json_decode($file); // Fill the pool with water

        if (!$this->questions_pool) {
            print("[Trivia] 'quiz.json' is not valid JSON.");
            return;
        }

        $this->channel = $channel;

        $questions_amount = count($this->questions_pool);
        print("Starting Trivia with $questions_amount questions.\n\n");

        // Populate with random questions from the questions pool
        for ($i = 0; $i < $questions_amount; $i++) {
            do $question_index = rand(0, $questions_amount - 1);
            while ($this->hasQuestionBeenSelected($question_index));

            $this->questions[$i] = $question_index;
            print("Slot $i was assigned Question $question_index\n");
        }

        $question = $this->getQuestion();

        $message  = "**Pergunta**: `{$question->pergunta}`";
        $message .= "\n**Respostas:**\n";
        $message .= "> 1. {$question->respostas[0]}\n\n";
        $message .= "> 2. {$question->respostas[1]}\n\n";
        $message .= "> 3. {$question->respostas[2]}\n\n";
        $message .= "> 4. {$question->respostas[3]}";

        $channel->sendMessage($message);
    }

    function getQuestion()
    {
        if ($this->current_question == count($this->questions_pool) - 1) return false; // No more questions

        $question_index = $this->questions[$this->current_question];
        $question_data  = (object) $this->questions_pool[$question_index];

        $this->current_question++;

        print_r($question_data);

        return $question_data;
    }

    // Check if the question has already been selected
    private function hasQuestionBeenSelected(int $question_index)
    {
        foreach ($this->questions as $question)
            if ($question == $question_index) return true;

        return false;
    }
}
