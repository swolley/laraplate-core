<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\ValueObject\PhpVersion;
use RectorLaravel\Set\LaravelSetList;

$paths = array_merge(
    [
        __DIR__ . '/app',
        __DIR__ . '/database',
    ],
);

return RectorConfig::configure()
    ->withSkip([
        AddOverrideAttributeToOverriddenMethodsRector::class,
        __DIR__ . '/vendor',
        __DIR__ . '/node_modules',
        __DIR__ . '/storage',
        // Pattern per qualsiasi file che potrebbe avere conflitti di namespace con Model
        '**/Model.php',
        // Ignora file con troppe righe che potrebbero causare problemi di analisi
        '**/vendor/**',
    ])
    ->withPaths($paths)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        strictBooleans: true,
    )
    ->withSets([
        LaravelSetList::LARAVEL_120,
    ])
    ->withPhpSets(
        php84: true,
    )
    ->withPhpVersion(PhpVersion::PHP_84);
