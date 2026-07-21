<?php

declare(strict_types=1);

use Modules\Core\ApplicationContent\ApplicationContentRetrievalProviderRegistry;
use Modules\Core\ApplicationContent\Contracts\ApplicationContentRetrievalProviderInterface;
use Modules\Core\ApplicationContent\Contracts\ApplicationContentRetrievalProviderRegistryInterface;
use Modules\Core\ApplicationContent\Data\ApplicationContentAuthorization;
use Modules\Core\ApplicationContent\Data\ApplicationContentHit;
use Modules\Core\ApplicationContent\Data\ApplicationContentQuery;
use Modules\Core\ApplicationContent\Data\ApplicationContentResult;
use Modules\Core\ApplicationContent\Data\ApplicationContentSourceDescriptor;
use Modules\Core\ApplicationContent\Exceptions\DuplicateApplicationContentSourceException;

final readonly class RegistryFakeApplicationContentProvider implements ApplicationContentRetrievalProviderInterface
{
    public function __construct(private ApplicationContentSourceDescriptor $source) {}

    public function descriptor(): ApplicationContentSourceDescriptor
    {
        return $this->source;
    }

    public function retrieve(
        ApplicationContentQuery $query,
        ApplicationContentAuthorization $authorization,
    ): ApplicationContentResult {
        return new ApplicationContentResult($query->source, [], 'lexical', false);
    }
}

function applicationContentDescriptor(string $source = 'cms.contents'): ApplicationContentSourceDescriptor
{
    [$module, $entity] = explode('.', mb_strtolower($source), 2);

    return new ApplicationContentSourceDescriptor(
        $source,
        $module,
        $entity,
        ['it', 'en'],
        ['lexical', 'hybrid'],
        ['content_help', 'content_search'],
    );
}

it('binds a deterministic registry that only accepts explicit providers', function (): void {
    $bound_registry = app(ApplicationContentRetrievalProviderRegistryInterface::class);
    $registry = new ApplicationContentRetrievalProviderRegistry;

    expect($bound_registry)->toBeInstanceOf(ApplicationContentRetrievalProviderRegistry::class)
        ->and($registry->descriptors())->toBe([])
        ->and($registry->providerFor('cms.contents'))->toBeNull();

    $registry->register(new RegistryFakeApplicationContentProvider(applicationContentDescriptor('zeta.records')));
    $registry->register(new RegistryFakeApplicationContentProvider(applicationContentDescriptor('alpha.records')));

    expect(array_map(
        static fn (ApplicationContentSourceDescriptor $descriptor): string => $descriptor->source,
        $registry->descriptors(),
    ))->toBe(['alpha.records', 'zeta.records']);
});

it('normalizes source lookup and rejects duplicate registration', function (): void {
    $registry = new ApplicationContentRetrievalProviderRegistry;
    $provider = new RegistryFakeApplicationContentProvider(applicationContentDescriptor());
    $registry->register($provider);

    expect($registry->providerFor(' CMS.CONTENTS '))->toBe($provider)
        ->and($registry->descriptorFor(' CMS.CONTENTS ')?->source)->toBe('cms.contents')
        ->and(fn () => $registry->register(
            new RegistryFakeApplicationContentProvider(applicationContentDescriptor('CMS.CONTENTS')),
        ))->toThrow(DuplicateApplicationContentSourceException::class);
});

it('snapshots the validated descriptor when a provider is registered', function (): void {
    $provider = new class implements ApplicationContentRetrievalProviderInterface
    {
        public int $descriptorCalls = 0;

        public function descriptor(): ApplicationContentSourceDescriptor
        {
            $this->descriptorCalls++;

            return applicationContentDescriptor(
                $this->descriptorCalls === 1 ? 'cms.contents' : 'cms.changed',
            );
        }

        public function retrieve(
            ApplicationContentQuery $query,
            ApplicationContentAuthorization $authorization,
        ): ApplicationContentResult {
            return new ApplicationContentResult($query->source, [], 'lexical', false);
        }
    };
    $registry = new ApplicationContentRetrievalProviderRegistry;
    $registry->register($provider);

    expect($registry->descriptors()[0]->source)->toBe('cms.contents')
        ->and($provider->descriptorCalls)->toBe(1);
});

it('keeps provider DTOs typed and free of control-plane or storage fields', function (): void {
    $descriptor = applicationContentDescriptor('CMS.CONTENTS');
    $query = new ApplicationContentQuery('CMS.CONTENTS', '  renewal policy  ', 'it', 99);
    $authorization = new ApplicationContentAuthorization('default.contents.select', null);
    $hit = new ApplicationContentHit(
        'cms-content-10',
        'cms.contents',
        'cms',
        'contents',
        10,
        'Visible content excerpt.',
        'Visible content',
        '/app/cms/contents/10',
        'it',
        'hybrid',
        0.91,
        '2026-07-19T10:00:00Z',
        false,
    );
    $result = new ApplicationContentResult('cms.contents', [$hit], 'hybrid', false);

    expect($descriptor->source)->toBe('cms.contents')
        ->and($query->source)->toBe('cms.contents')
        ->and($query->query)->toBe('renewal policy')
        ->and($query->limit)->toBe(8)
        ->and($result->hits)->toHaveCount(1)
        ->and($descriptor)->toBeInstanceOf(ApplicationContentSourceDescriptor::class);

    $forbidden = [
        'user_id',
        'tenant_id',
        'roles',
        'permissions',
        'acl',
        'connection',
        'class',
        'index',
        '_source',
        'system_prompt',
    ];
    $found_forbidden = [];

    foreach ([$descriptor, $query, $authorization, $hit, $result] as $dto) {
        array_push($found_forbidden, ...array_intersect(array_keys(get_object_vars($dto)), $forbidden));
    }

    expect($found_forbidden)->toBe([]);
});

it('rejects invalid descriptors queries and unsafe or oversized evidence', function (): void {
    expect(fn () => new ApplicationContentSourceDescriptor(
        'cms.contents',
        'cms',
        'contents',
        ['invalid locale'],
        ['lexical'],
        ['content_help'],
    ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ApplicationContentSourceDescriptor(
            'cms.contents',
            'cms',
            'contents',
            ['primary' => 'it'],
            ['lexical'],
            ['content_help'],
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ApplicationContentQuery('cms.contents', '   ', 'it', 5))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ApplicationContentHit(
            'bad',
            'cms.contents',
            'cms',
            'contents',
            10,
            str_repeat('x', 9000),
            'Visible',
            '/srv/private/content/10',
            'it',
            'lexical',
            null,
            null,
            false,
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ApplicationContentHit(
            'bad-score',
            'cms.contents',
            'cms',
            'contents',
            10,
            'Visible excerpt',
            'Visible',
            '/app/cms/contents/10',
            'it',
            'lexical',
            NAN,
            null,
            false,
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ApplicationContentHit(
            'bad-encoding',
            'cms.contents',
            'cms',
            'contents',
            10,
            "Invalid \xB1 text",
            'Visible',
            '/app/cms/contents/10',
            'it',
            'lexical',
            null,
            null,
            false,
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ApplicationContentHit(
            'bad-record-key',
            'cms.contents',
            'cms',
            'contents',
            "key-\xB1",
            'Visible excerpt',
            'Visible',
            '/app/cms/contents/10',
            'it',
            'lexical',
            null,
            null,
            false,
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ApplicationContentResult(
            'cms.contents',
            ['first' => new ApplicationContentHit(
                'valid',
                'cms.contents',
                'cms',
                'contents',
                10,
                'Visible excerpt',
                'Visible',
                '/app/cms/contents/10',
                'it',
                'lexical',
                null,
                null,
                false,
            )],
            'lexical',
            false,
        ))->toThrow(InvalidArgumentException::class);
});
