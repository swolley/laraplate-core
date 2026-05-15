<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\Core\Casts\IParsableRequest;
use Modules\Core\Grids\Casts\GridAction;
use Modules\Core\Grids\Casts\GridRequestData;
use Modules\Core\Http\Requests\ListRequest;
use Modules\Core\Http\Requests\ModifyRequest;
use Override;

final class GridRequest extends FormRequest implements IParsableRequest
{
    public private(set) GridAction $action;

    private ListRequest|ModifyRequest $realMainRequest;

    /**
     * @var array<int,ListRequest>
     */
    private array $realOptionRequests = [];

    /**
     * @var array<int,ListRequest>
     */
    private array $realFunnelRequests = [];

    public function rules(): array
    {
        /** @phpstan-ignore method.notFound */
        $url = $this->url();

        if (Str::contains($url, '/' . GridAction::Funnels->value)) {
            $grid_rules = $this->remapListRules('funnels.*');
        } elseif (Str::contains($url, '/' . GridAction::Options->value)) {
            $grid_rules = $this->remapListRules('options.*');
        } elseif (Str::contains($url, '/' . GridAction::Select->value)) {
            $grid_rules = [
                'options' => ['sometimes'],
                'funnels' => ['sometimes'],
                ...$this->remapListRules('funnels.*'),
                ...$this->remapListRules('options.*'),
            ];
            // TODO: serve anche l'entità o parto da quella della griglia e poi guardo che colonne vengono chieste?
        } elseif (Str::contains($url, [
            '/' . GridAction::Insert->value,
            '/' . GridAction::Update->value,
            '/' . GridAction::Delete->value,
            '/' . GridAction::ForceDelete->value,
            '/' . GridAction::Approve->value,
            '/' . GridAction::Lock->value,
            '/' . GridAction::Check->value,
        ])) {
            $grid_rules = ['funnels' => 'exclude', 'options' => 'exclude'];
        } else {
            $grid_rules = [];
        }

        return $grid_rules;
    }

    #[Override]
    public function validateResolved(): void
    {
        parent::validateResolved();

        if (isset($this->realMainRequest)) {
            $this->realMainRequest->validateResolved();
        }

        foreach ($this->realOptionRequests as $request) {
            $request->validateResolved();
        }

        foreach ($this->realFunnelRequests as $request) {
            $request->validateResolved();
        }
    }

    #[Override]
    public function validated(mixed $key = null, mixed $default = null): mixed
    {
        $validated = $this->realMainRequest->validated($key, $default);
        $funnels = $this->input('funnels');

        if ($funnels) {
            for ($i = 0; count($funnels); $i++) {
                $validated['funnels'][$i] = $this->realFunnelRequests[$i]->validated();
            }
        }

        $options = $this->input('options');

        if ($options) {
            for ($i = 0; count($options); $i++) {
                $validated['options'][$i] = $this->realOptionRequests[$i]->validated();
            }
        }

        return $validated;
    }

    #[Override]
    public function parsed(): GridRequestData
    {
        /** @var string $main_entity */
        /** @phpstan-ignore method.notFound */
        $main_entity = $this->route()->entity;
        $remapped = $this->validated();

        return new GridRequestData($this->action, $this, $main_entity, $remapped, $this->realMainRequest->getPrimaryKey());
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        /** @phpstan-ignore method.notFound */
        $exploded_url = explode('/', $this->url());
        $this->action = GridAction::from($exploded_url[count($exploded_url) - 2]);

        switch ($this->action) {
            case GridAction::Data:
            case GridAction::Select:
            case GridAction::Export:
            case GridAction::Funnels:
            case GridAction::Options:
                /** @phpstan-ignore staticMethod.notFound */
                $this->realMainRequest = ListRequest::createFrom($this);
                $this->realMainRequest->setContainer($this->container);
                $funnels = $this->input('funnels');

                if (property_exists($this, 'funnels') && $funnels !== null) {
                    foreach ($funnels as $funnel) {
                        /** @phpstan-ignore staticMethod.notFound */
                        $sub_request = ListRequest::createFrom($this);
                        $sub_request->setContainer($this->container);
                        $sub_request->replace($funnel);
                        $this->realFunnelRequests[] = $sub_request;
                    }
                }

                $options = $this->input('options');

                if (property_exists($this, 'options') && $options !== null) {
                    foreach ($options as $option) {
                        /** @phpstan-ignore staticMethod.notFound */
                        $sub_request = ListRequest::createFrom($this);
                        $sub_request->setContainer($this->container);
                        $sub_request->replace($option);
                        $this->realOptionRequests[] = $sub_request;
                    }
                }

                break;
                // case GridAction::LAYOUT:
                // case GridAction::COUNT:
            case GridAction::Insert:
            case GridAction::Update:
                /** @phpstan-ignore staticMethod.notFound */
                // $this->realMainRequest = ModifyRequest::createFrom($this);
            case GridAction::Check:
            case GridAction::ForceDelete:
            case GridAction::Delete:
                // case GridAction::Restore:
                /** @phpstan-ignore staticMethod.notFound */
                $this->realMainRequest = ModifyRequest::createFrom($this);
                $this->realMainRequest->setContainer($this->container);

                break;
        }
    }

    private function remapListRules(string $prefix): array
    {
        $list_rules = Arr::except((new ListRequest)->rules(), ['count', 'group_by.*']);

        return $this->remapRules($list_rules, $prefix);
    }

    private function remapRules(array $rules, string $prefix): array
    {
        $remapped = [];

        foreach ($rules as $name => $validations) {
            $remapped[sprintf('%s.%s', $prefix, $name)] = $validations;
        }

        return $remapped;
    }
}
