<?php

declare(strict_types=1);

use Modules\Core\Search\Contracts\IQueryIntentParser;
use Modules\Core\Search\Services\SimpleQueryIntentParser;

it('implements IQueryIntentParser contract', function (): void {
    expect(new SimpleQueryIntentParser)->toBeInstanceOf(IQueryIntentParser::class);
});

it('extracts keywords removing stopwords', function (): void {
    $parser = new SimpleQueryIntentParser;
    $result = $parser->parse('the quick brown fox');

    expect($result['keywords'])->toContain('quick', 'brown', 'fox');
    expect($result['keywords'])->not->toContain('the');
});

it('removes Italian stopwords', function (): void {
    $parser = new SimpleQueryIntentParser;
    $result = $parser->parse('il gatto della nonna');

    expect($result['keywords'])->toContain('gatto', 'nonna');
    expect($result['keywords'])->not->toContain('il', 'della');
});

it('returns original query as expanded', function (): void {
    $parser = new SimpleQueryIntentParser;
    $result = $parser->parse('test query');

    expect($result['query']['expanded'])->toBe('test query');
});

it('returns null date_range', function (): void {
    $parser = new SimpleQueryIntentParser;
    $result = $parser->parse('test');

    expect($result['date_range'])->toBeNull();
});

it('deduplicates keywords', function (): void {
    $parser = new SimpleQueryIntentParser;
    $result = $parser->parse('test test test unique');

    expect($result['keywords'])->toBe(['test', 'unique']);
});

it('filters single-character words', function (): void {
    $parser = new SimpleQueryIntentParser;
    $result = $parser->parse('a b testing');

    expect($result['keywords'])->toBe(['testing']);
});
