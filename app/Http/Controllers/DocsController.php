<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use ArrayAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Actions\Docs\MergeSwaggerDocsAction;
use Modules\Core\Services\Docs\ModuleInfoService;
use Override;
use UnexpectedValueException;
use Wotz\SwaggerUi\Http\Controllers\OpenApiJsonController;

final class DocsController extends OpenApiJsonController
{
    public function __construct(
        private readonly MergeSwaggerDocsAction $mergeSwaggerDocsAction,
        private readonly ModuleInfoService $moduleInfoService,
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
     * @route-comment
     * Route(path: 'welcome', name: 'core.docs.welcome', methods: [GET, HEAD], middleware: [web])
     */
    public function welcome(): View
    {
        return view('core::welcome', [
            'grouped_modules' => $this->moduleInfoService->groupedModules(),
            'translations' => translations(),
        ]);
    }

    /**
     * @route-comment
     * Route(path: 'phpinfo', name: 'core.docs.phpinfo', methods: [GET, HEAD], middleware: [web])
     */
    public function phpinfo(): View
    {
        return view('core::phpinfo');
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
