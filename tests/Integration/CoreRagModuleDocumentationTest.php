<?php

declare(strict_types=1);

it('Core RAG MODULE.md includes mermaid diagrams for core flows', function (): void {
    $path = dirname(__DIR__, 2) . '/docs/rag/MODULE.md';

    expect(file_exists($path))->toBeTrue();

    $content = (string) file_get_contents($path);

    expect(substr_count($content, '```mermaid'))->toBeGreaterThanOrEqual(11)
        ->and(substr_count($content, '```mermaid'))->toEqual(substr_count($content, "```\n"))
        ->and($content)->toContain('### Module boundaries')
        ->and($content)->toContain('### Identity, authentication and license')
        ->and($content)->toContain('### Authorization: roles, permissions and ACLs')
        ->and($content)->toContain('### Record lifecycle traits stack')
        ->and($content)->toContain('### Versioning flow')
        ->and($content)->toContain('### Approvals and preview')
        ->and($content)->toContain('### Dynamic entities, presets and fields')
        ->and($content)->toContain('### Schema inspector and DynamicEntity')
        ->and($content)->toContain('### CRUD pipeline')
        ->and($content)->toContain('### Translations and locale')
        ->and($content)->toContain('### Search abstractions and engines')
        ->and($content)->toContain('### Place and geocoding')
        ->and($content)->toContain('### Settings and module activation')
        ->and($content)->toContain('AclResolverService')
        ->and($content)->toContain('AuthorizationService')
        ->and($content)->toContain('VersioningService')
        ->and($content)->toContain('SchemaInspector')
        ->and($content)->toContain('DynamicEntityService')
        ->and($content)->toContain('LocaleScope')
        ->and($content)->toContain('ModuleDatabaseActivator');
});
