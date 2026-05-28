<?php

declare(strict_types=1);

use Modules\AI\Database\Seeders\AIDatabaseSeeder;
use Modules\CMS\Database\Seeders\CMSDatabaseSeeder;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\ERP\Services\Company\ErpCompanySettings;
use Modules\MES\Database\Seeders\MESDatabaseSeeder;

uses(Tests\TestCase::class);

it('keeps runtime setting names within the Setting model name limit', function (): void {
    $all_names = collect([
        ...CoreDatabaseSeeder::runtimeSettingDefinitions(),
        ...AIDatabaseSeeder::runtimeSettingDefinitions(),
        ...CMSDatabaseSeeder::runtimeSettingDefinitions(),
        ...MESDatabaseSeeder::runtimeSettingDefinitions(),
        ...ErpCompanySettings::globalSettingDefinitions(),
    ])->pluck('name');

    expect($all_names->every(fn (string $name): bool => mb_strlen($name) <= 255))->toBeTrue();
});

it('defines core runtime settings with current defaults and choices', function (): void {
    $definitions = collect(CoreDatabaseSeeder::runtimeSettingDefinitions())->keyBy('name');

    expect($definitions->get('core.enable_user_registration')['value'])->toBeFalse()
        ->and($definitions->get('core.enable_user_registration')['type'])->toBe(SettingTypeEnum::Boolean)
        ->and($definitions->get('core.auto_translate_provider')['value'])->toBe('deepl')
        ->and($definitions->get('core.auto_translate_provider')['choices'])->toBe(['deepl', 'ai'])
        ->and($definitions->get('search.vector_search.similarity')['choices'])->toBe(['cosine', 'dot_product', 'euclidean']);
});

it('defines ai runtime settings with current defaults and choices', function (): void {
    $definitions = collect(AIDatabaseSeeder::runtimeSettingDefinitions())->keyBy('name');

    expect($definitions->get('ai.features.embeddings.enabled')['value'])->toBeTrue()
        ->and($definitions->get('ai.features.embeddings.default_provider')['value'])->toBe('sentence_transformers')
        ->and($definitions->get('ai.features.embeddings.default_provider')['choices'])
        ->toBe(['sentence_transformers', 'openai', 'ollama', 'voyageai', 'mistral'])
        ->and($definitions->get('ai.features.faq.vector_store')['choices'])->toBe(['filesystem', 'memory'])
        ->and($definitions->get('ai.features.moderation.approval_mode')['choices'])->toBe(['threshold', 'dual']);
});

it('defines cms and mes runtime settings with current defaults and choices', function (): void {
    $cmsDefinitions = collect(CMSDatabaseSeeder::runtimeSettingDefinitions())->keyBy('name');
    $mesDefinitions = collect(MESDatabaseSeeder::runtimeSettingDefinitions())->keyBy('name');

    expect($cmsDefinitions->get('cms.locale.auto_translate')['value'])->toBeFalse()
        ->and($cmsDefinitions->get('cms.services.geocoding.provider')['choices'])->toBe(['nominatim'])
        ->and($cmsDefinitions->get('media-library.image_driver')['choices'])->toBe(['gd', 'imagick'])
        ->and($mesDefinitions->get('mes.rate_limit')['value'])->toBe(60)
        ->and($mesDefinitions->get('mes.lot_number_format')['value'])->toBe('{YEAR}{MONTH}{DAY}-{SEQ}');
});

it('keeps erp company-backed settings selectable where appropriate', function (): void {
    $definitions = collect(ErpCompanySettings::globalSettingDefinitions())->keyBy('name');

    expect($definitions->get(ErpCompanySettings::INVOICE_GENERATION_MODE)['choices'])
        ->toBe([
            ErpCompanySettings::INVOICE_GENERATION_MODE_EXPANDED,
            ErpCompanySettings::INVOICE_GENERATION_MODE_COMPACT,
        ]);
});
