<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NumWords implements ValidationRule
{

    protected int $minExpectedWords, $maxWords;

    protected $minExpectedWordsFailMessage, $maxWordsFailMessage;

    protected $translateMessages;

    public function __construct(int $minExpectedWords, int $maxWords, $minExpectedWordsFailMessage = null, $maxWordsFailMessage = null, $translateMessages = true)
    {
        $this->minExpectedWords = $minExpectedWords;
        $this->maxWords = $maxWords;
        $this->minExpectedWordsFailMessage = $minExpectedWordsFailMessage;
        $this->maxWordsFailMessage = $maxWordsFailMessage;
        $this->translateMessages = $translateMessages;

        if (!$minExpectedWordsFailMessage)
            $this->minExpectedWordsFailMessage = 'messages.expected_min_words';

        if (!$maxWordsFailMessage)
            $this->maxWordsFailMessage = 'messages.max_words_reached';

    }

    /**
     * Run the validation rule.
     *
     * @param \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $trimmed = trim($value);
        $numWords = count(explode(' ', $trimmed));

        if ($numWords < $this->minExpectedWords) {
            if ($this->translateMessages) {
                $fail($this->minExpectedWordsFailMessage)->translate();
            } else {
                $fail($this->minExpectedWordsFailMessage);
            }
        }

        if ($numWords > $this->maxWords) {
            if ($this->translateMessages) {
                $fail($this->maxWordsFailMessage)->translate();
            } else {
                $fail($this->maxWordsFailMessage);
            }
        }
    }
}
