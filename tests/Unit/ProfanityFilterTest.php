<?php

use App\Services\ProfanityFilterService;

beforeEach(function () {
    $this->filter = new ProfanityFilterService;
});

test('filters out plain english and tagalog curse words', function () {
    $input = 'Sobrang gago at bobo magturo';
    $output = $this->filter->clean($input);

    expect($output)->toBe('Sobrang at magturo')
        ->and($this->filter->hasProfanity($input))->toBeTrue();
});

test('filters out leetspeak symbol substitutions', function () {
    $input = 'Napakababang klase p@ng1t and b0b0 magturo';
    $output = $this->filter->clean($input);

    expect($output)->not->toContain('p@ng1t')
        ->and($output)->not->toContain('b0b0')
        ->and($this->filter->hasProfanity($input))->toBeTrue();
});

test('filters out spaced and punctuation evasions', function () {
    $input = 'Ang t a n g a naman mag-explain g_a_g_o pa';
    $output = $this->filter->clean($input);

    expect($output)->not->toContain('t a n g a')
        ->and($output)->not->toContain('g_a_g_o')
        ->and($this->filter->hasProfanity($input))->toBeTrue();
});

test('filters out repeated character vulgarities', function () {
    $input = 'Sobrang taaaaangaaaa talaga';
    $output = $this->filter->clean($input);

    expect($output)->not->toContain('taaaaangaaaa')
        ->and($this->filter->hasProfanity($input))->toBeTrue();
});

test('preserves legitimate words with strict boundaries without false positives', function () {
    $cleanComments = [
        'Great class and very good assessment.',
        'Mabait si sir at madaling lapitan.',
        'Mahirap ang subject pero passionate ang professor.',
        'Very helpful in clarifying our doubts.',
    ];

    foreach ($cleanComments as $comment) {
        expect($this->filter->hasProfanity($comment))->toBeFalse()
            ->and($this->filter->clean($comment))->toBe($comment);
    }
});
