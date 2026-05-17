<?php

declare(strict_types=1);

uses(Modules\Core\Tests\ApplicationTestCase::class);

use Illuminate\Console\OutputStyle;
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
