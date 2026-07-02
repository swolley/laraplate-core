<?php

declare(strict_types=1);

uses(Modules\Core\Tests\ApplicationTestCase::class);

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\TaskResult;
use Modules\Core\Console\View\Components\MigrationTask;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

it('renders module badge before status with adjusted dots', function (): void {
    $buffer = new BufferedOutput();
    $output = new OutputStyle(new ArrayInput([]), $buffer);
    $component = new MigrationTask($output);

    $component->render(
        '2026_01_24_174744_create_ai_messages_table',
        'AI',
        static fn (): bool => true,
    );

    $content = $buffer->fetch();

    expect($content)
        ->toContain('2026_01_24_174744_create_ai_messages_table')
        ->toContain('AI')
        ->toContain('DONE')
        ->and(mb_strpos($content, 'AI'))->toBeLessThan(mb_strpos($content, 'DONE'));
});

it('renders skipped and failed migration task statuses', function (): void {
    $skipped_buffer = new BufferedOutput();
    $skipped_output = new OutputStyle(new ArrayInput([]), $skipped_buffer);
    (new MigrationTask($skipped_output))->render('Skipped migration', 'Core', static fn (): int => TaskResult::Skipped->value);

    $failed_buffer = new BufferedOutput();
    $failed_output = new OutputStyle(new ArrayInput([]), $failed_buffer);
    (new MigrationTask($failed_output))->render('Failed migration', 'Core', static fn (): int => TaskResult::Failure->value);

    expect($skipped_buffer->fetch())->toContain('SKIPPED')
        ->and($failed_buffer->fetch())->toContain('FAIL');
});

it('renders a successful migration task without a callable', function (): void {
    $buffer = new BufferedOutput();
    $output = new OutputStyle(new ArrayInput([]), $buffer);

    (new MigrationTask($output))->render('No task migration', 'Core');

    expect($buffer->fetch())->toContain('DONE');
});

it('rethrows task exceptions after rendering failure status', function (): void {
    $buffer = new BufferedOutput();
    $output = new OutputStyle(new ArrayInput([]), $buffer);
    $component = new MigrationTask($output);

    expect(fn () => $component->render('Exploding migration', 'Core', static function (): never {
        throw new RuntimeException('migration failed');
    }))->toThrow(RuntimeException::class, 'migration failed');

    expect($buffer->fetch())->toContain('FAIL');
});
