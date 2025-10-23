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

    public function rules()
    {
        /** @phpstan-ignore method.notFound */
        $url = $this->url();

        if (Str::contains($url, '/' . GridAction::FUNNELS->value)) {
            $grid_rules = $this->remapListRules('funnels.*');
        } elseif (Str::contains($url, '/' . GridAction::OPTIONS->value)) {
            $grid_rules = $this->remapListRules('options.*');
        } elseif (Str::contains($url, '/' . GridAction::SELECT->value)) {
            $grid_rules = [
                'options' => ['sometimes'],
                'funnels' => ['sometimes'],
                ...$this->remapListRules('funnels.*'),
                ...$this->remapListRules('options.*'),
            ];
            // TODO: serve anche l'entità o parto da quella della griglia e poi guardo che colonne vengono chieste?
        } elseif (Str::contains($url, [
            '/' . GridAction::INSERT->value,
            '/' . GridAction::UPDATE->value,
            '/' . GridAction::DELETE->value,
            '/' . GridAction::FORCE_DELETE->value,
            '/' . GridAction::APPROVE->value,
            '/' . GridAction::LOCK->value,
            '/' . GridAction::CHECK->value,
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
    public function validated($key = null, $default = null)
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
            case GridAction::DATA:
            case GridAction::SELECT:
            case GridAction::EXPORT:
            case GridAction::FUNNELS:
            case GridAction::OPTIONS:
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
            case GridAction::INSERT:
            case GridAction::UPDATE:
                /** @phpstan-ignore staticMethod.notFound */
                // $this->realMainRequest = ModifyRequest::createFrom($this);
            case GridAction::CHECK:
            case GridAction::FORCE_DELETE:
            case GridAction::DELETE:
                // case GridAction::RESTORE:
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
