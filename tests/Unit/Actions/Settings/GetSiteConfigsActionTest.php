<?php

declare(strict_types=1);

use Modules\Core\Actions\Settings\GetSiteConfigsAction;
use Tests\TestCase;

final class GetSiteConfigsActionTest extends TestCase
{
    public function test_builds_settings_array(): void
    {
        $settings = [
            (object) ['name' => 'foo', 'value' => 'bar'],
            (object) ['name' => 'baz', 'value' => 'qux'],
        ];

        $action = new GetSiteConfigsAction(
            settingsProvider: fn () => $settings,
            modulesProvider: fn () => ['mod1'],
        );

        $result = $action();

        $this->assertSame('bar', $result['foo']);
        $this->assertSame('qux', $result['baz']);
        $this->assertSame(['mod1'], $result['active_modules']);
    }
}

