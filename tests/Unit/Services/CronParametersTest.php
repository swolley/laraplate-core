<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Modules\Core\Services\CronParameters;

it('keeps a flag as it is', function (): void {
    expect(CronParameters::toScheduleParameters(['--all', '-q']))->toBe(['--all', '-q']);
});

it('splits an option at the first equals sign so that Laravel escapes the value', function (): void {
    expect(CronParameters::toScheduleParameters(['--filter=a=b', '--model=A B\\C']))
        ->toBe(['--filter' => 'a=b', '--model' => 'A B\\C']);
});

it('groups a repeated option into a list', function (): void {
    expect(CronParameters::toScheduleParameters(['--model=A', '--model=B C']))
        ->toBe(['--model' => ['A', 'B C']]);
});

it('keeps an argument and the order of the elements', function (): void {
    expect(CronParameters::toScheduleParameters(['Taylor Otwell', '--force', '-n', '5']))
        ->toBe(['Taylor Otwell', '--force', '-n', '5']);
});

it('turns an empty list into no parameters', function (): void {
    expect(CronParameters::toScheduleParameters([]))->toBe([]);
});

it('accepts an option with an empty value', function (): void {
    expect(CronParameters::toScheduleParameters(['--note=']))->toBe(['--note' => '']);
});

it('rejects a flag that is not a plain name, since Laravel would not escape it', function (string $token): void {
    expect(CronParameters::isValidToken($token))->toBeFalse();
})->with([
    'a command chained after the flag' => '--all; rm -rf /',
    'a substitution' => '--all$(whoami)',
    'a space in the name' => '--all now',
    'a bare dash' => '-',
    'a dash and a digit' => '-5',
    'nothing' => '',
]);

it('rejects an option value that starts with a dash, since Laravel would not escape it', function (): void {
    expect(CronParameters::isValidToken('--model=--rm -rf'))->toBeFalse()
        ->and(CronParameters::isValidToken('--offset=-5'))->toBeFalse();
});

it('accepts the three kinds of element', function (string $token): void {
    expect(CronParameters::isValidToken($token))->toBeTrue();
})->with([
    'a flag' => '--dry-run',
    'a short flag' => '-q',
    'an option with a value' => '--limit=10',
    'an option with a path' => '--model=Modules\\AI\\Models\\ContextualSuggestion',
    'an argument' => 'Taylor Otwell',
]);

it('refuses to convert a list that has an invalid element', function (): void {
    expect(fn () => CronParameters::toScheduleParameters(['--all', '--x; rm -rf /']))
        ->toThrow(InvalidArgumentException::class);
});

it('tells a list of strings from anything else', function (): void {
    expect(CronParameters::isValidList(['--all', 'Taylor']))->toBeTrue()
        ->and(CronParameters::isValidList([]))->toBeTrue()
        ->and(CronParameters::isValidList(['--limit' => 10]))->toBeFalse()
        ->and(CronParameters::isValidList(['--all', 5]))->toBeFalse()
        ->and(CronParameters::isValidList('--all'))->toBeFalse();
});

describe('placeholders', function (): void {
    afterEach(function (): void {
        Carbon::setTestNow();
    });

    it('resolves a placeholder in the value of an option and in an argument', function (): void {
        Carbon::setTestNow('2026-10-14 10:30:00');
        $midnight = Carbon::parse('2026-10-14 00:00:00', date_default_timezone_get())->toIso8601String();
        $now = Carbon::parse('2026-10-14 10:30:00', date_default_timezone_get())->toIso8601String();

        expect(CronParameters::toScheduleParameters(['--before=@today', '@now']))
            ->toBe(['--before' => $midnight, $now]);
    });

    it('resolves a placeholder each time the parameters are converted, not once', function (): void {
        Carbon::setTestNow('2026-10-14 10:30:00');
        $first = CronParameters::toScheduleParameters(['--before=@now']);

        Carbon::setTestNow('2026-10-14 10:31:00');
        $second = CronParameters::toScheduleParameters(['--before=@now']);

        expect($first)->not->toBe($second);
    });

    it('leaves what is not a time placeholder as it is, a user placeholder included', function (): void {
        expect(CronParameters::toScheduleParameters(['--user=@user.id', '--tag=@later', '@unknown']))
            ->toBe(['--user' => '@user.id', '--tag' => '@later', '@unknown']);
    });

    it('accepts a placeholder as a valid element', function (): void {
        expect(CronParameters::isValidToken('--before=@now'))->toBeTrue()
            ->and(CronParameters::isValidToken('@today'))->toBeTrue();
    });
});
