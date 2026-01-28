<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use ArrayAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Actions\Docs\MergeSwaggerDocsAction;
use Override;
use UnexpectedValueException;
use Wotz\SwaggerUi\Http\Controllers\OpenApiJsonController;

final class DocsController extends OpenApiJsonController
{
    public function __construct(
        private readonly MergeSwaggerDocsAction $mergeSwaggerDocsAction,
    ) {}

    /**
     * @route-comment
     * Route(path: 'swagger/{filename}', name: 'core.docs.swaggerDocs', methods: [GET, HEAD], middleware: [web])
     */
    public function mergeDocs(Request $request, string $version = 'v1')
    {
        return Cache::tags(Cache::getCacheTags('docs'))->rememberForever(
            $request->route()->getName() . $version,
            fn () => response()->json(($this->mergeSwaggerDocsAction)($version)),
        );
    }

    /**
     * @throws UnexpectedValueException if no documentation is found
     *
     * @return ArrayAccess|array
     *
     * @psalm-return ArrayAccess|array{paths: mixed,...}
     */
    #[Override]
    protected function getJson(string $version): array
    {
        return ($this->mergeSwaggerDocsAction)($version);
    }
}
