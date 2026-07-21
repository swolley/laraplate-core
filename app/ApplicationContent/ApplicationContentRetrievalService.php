<?php

declare(strict_types=1);

namespace Modules\Core\ApplicationContent;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Core\ApplicationContent\Contracts\ApplicationContentRetrievalProviderRegistryInterface;
use Modules\Core\ApplicationContent\Data\ApplicationContentAuthorization;
use Modules\Core\ApplicationContent\Data\ApplicationContentQuery;
use Modules\Core\ApplicationContent\Data\ApplicationContentResult;
use Modules\Core\ApplicationContent\Data\ApplicationContentSourceDescriptor;
use Modules\Core\ApplicationContent\Exceptions\ApplicationContentUnavailableException;
use Modules\Core\Models\User;
use Modules\Core\Services\Authorization\AuthorizationService;
use Nwidart\Modules\Facades\Module;
use Throwable;

final readonly class ApplicationContentRetrievalService
{
    public function __construct(
        private ApplicationContentRetrievalProviderRegistryInterface $providers,
        private AuthorizationService $authorization,
    ) {}

    public function retrieve(Request $request, ApplicationContentQuery $query): ApplicationContentResult
    {
        try {
            $this->assertConsistentIdentity($request);
            $provider = $this->providers->providerFor($query->source)
                ?? throw new ApplicationContentUnavailableException;
            $descriptor = $this->providers->descriptorFor($query->source)
                ?? throw new ApplicationContentUnavailableException;

            $this->assertDescriptorAvailable($descriptor, $query);

            $permission_name = $this->authorization->ensurePermission(
                $request,
                $descriptor->entity,
                'select',
            );
            $acl_filters = $this->authorization->getAclFilters($permission_name);
            $result = $provider->retrieve(
                $query,
                new ApplicationContentAuthorization($permission_name, $acl_filters),
            );

            $this->assertResultInvariants($result, $descriptor, $query);

            return $result;
        } catch (ApplicationContentUnavailableException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ApplicationContentUnavailableException;
        }
    }

    private function assertConsistentIdentity(Request $request): void
    {
        $request_user = $request->user();
        $guard_user = Auth::user();

        if (! $request_user instanceof User
            || ! $guard_user instanceof User
            || $request_user !== $guard_user
            || $request_user->getAuthIdentifier() === null) {
            throw new ApplicationContentUnavailableException;
        }
    }

    private function assertDescriptorAvailable(
        ApplicationContentSourceDescriptor $descriptor,
        ApplicationContentQuery $query,
    ): void {
        $module = Str::studly($descriptor->module);

        if ($descriptor->source !== $query->source
            || ! Module::isEnabled($module)
            || ! in_array($query->locale, $descriptor->supportedLocales, true)) {
            throw new ApplicationContentUnavailableException;
        }
    }

    private function assertResultInvariants(
        ApplicationContentResult $result,
        ApplicationContentSourceDescriptor $descriptor,
        ApplicationContentQuery $query,
    ): void {
        if ($result->source !== $descriptor->source
            || ! in_array($result->strategy, $descriptor->capabilities, true)
            || count($result->hits) > $query->limit) {
            throw new ApplicationContentUnavailableException;
        }

        $hit_ids = [];

        foreach ($result->hits as $hit) {
            if ($hit->source !== $descriptor->source
                || $hit->module !== $descriptor->module
                || $hit->entity !== $descriptor->entity
                || ! in_array($hit->locale, $descriptor->supportedLocales, true)) {
                throw new ApplicationContentUnavailableException;
            }

            $hit_ids[] = $hit->id;
        }

        if (count(array_unique($hit_ids)) !== count($hit_ids)) {
            throw new ApplicationContentUnavailableException;
        }
    }
}
