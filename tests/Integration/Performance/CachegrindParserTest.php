<?php

declare(strict_types=1);

use Modules\Core\Performance\CachegrindParser;

function write_cachegrind(string $body): string
{
    $path = tempnam(sys_get_temp_dir(), 'cg_');
    file_put_contents($path, $body);

    return $path;
}

it('sums self cost per function and excludes call costs', function (): void {
    // Foo::a has 100 units of self cost plus a 50-unit call into Foo::b (not self).
    // Foo::b has 40 units of self cost.
    $path = write_cachegrind(<<<'CG'
        version: 1
        creator: xdebug
        fl=(1) /app/Foo.php
        fn=(1) Foo::a
        1 100
        cfn=(2) Foo::b
        calls=1 0 0
        1 50
        fl=(1)
        fn=(2) Foo::b
        2 40
        CG);

    $summary = app(CachegrindParser::class)->summarize($path, 10);
    unlink($path);

    expect($summary->totalSelf)->toBe(140)
        ->and($summary->functions[0]->name)->toBe('Foo::a')
        ->and($summary->functions[0]->self)->toBe(100)
        ->and($summary->functions[1]->name)->toBe('Foo::b')
        ->and($summary->functions[1]->self)->toBe(40);
});

it('resolves compressed name references reused without the label', function (): void {
    // Foo::a is defined with its label once, then reused as "fn=(1)" only.
    $path = write_cachegrind(<<<'CG'
        fl=(1) /app/Foo.php
        fn=(1) Foo::a
        1 30
        fl=(1)
        fn=(1)
        1 70
        CG);

    $summary = app(CachegrindParser::class)->summarize($path, 10);
    unlink($path);

    expect($summary->functions[0]->name)->toBe('Foo::a')
        ->and($summary->functions[0]->self)->toBe(100);
});

it('honours the limit and computes percentages', function (): void {
    $path = write_cachegrind(<<<'CG'
        fn=(1) A
        1 75
        fl=(1)
        fn=(2) B
        1 25
        CG);

    $summary = app(CachegrindParser::class)->summarize($path, 1);
    unlink($path);

    expect($summary->functions)->toHaveCount(1)
        ->and($summary->functions[0]->name)->toBe('A')
        ->and($summary->functions[0]->percent)->toBe(75.0);
});
