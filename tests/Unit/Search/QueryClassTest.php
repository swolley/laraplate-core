<?php

declare(strict_types=1);

use Modules\Core\Search\Enums\QueryClass;
use Modules\Core\Search\Services\SearchQueryAnalyzer;

it('derives the query class from the analyzer output', function (string $query, QueryClass $expected): void {
    $analysis = (new SearchQueryAnalyzer())->analyze($query);

    expect(QueryClass::fromAnalysis($analysis))->toBe($expected);
})->with([
    'structured code' => ['INV-1042 fattura', QueryClass::Identifier],
    'email' => ['mario.rossi@example.com', QueryClass::Identifier],
    'numeric' => ['fattura 1042', QueryClass::Identifier],
    'uuid' => ['3f2b8c1e-9a4d-4e2b-8f1a-2c3d4e5f6a7b', QueryClass::Identifier],
    'two names' => ['Mario Rossi', QueryClass::ShortKeyword],
    'only stopwords' => ['il', QueryClass::ShortKeyword],
    'three terms' => ['fatture fornitori scadute', QueryClass::MultiTerm],
    'long sentence' => ['come faccio ad annullare una fattura già inviata', QueryClass::NaturalLanguage],
    'few terms with stopwords' => ['elenco delle fatture per il cliente', QueryClass::NaturalLanguage],
]);

it('lets an identifier win over a long natural language query', function (): void {
    $analysis = (new SearchQueryAnalyzer())->analyze('come faccio ad annullare la fattura INV-1042 già inviata');

    expect(QueryClass::fromAnalysis($analysis))->toBe(QueryClass::Identifier);
});

it('does not treat short words and acronyms as identifiers', function (): void {
    $analysis = (new SearchQueryAnalyzer())->analyze('IVA su ad');

    expect(QueryClass::fromAnalysis($analysis))->not->toBe(QueryClass::Identifier);
});
