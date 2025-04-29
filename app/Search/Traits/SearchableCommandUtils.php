<?php

namespace Modules\Core\Search\Traits;

use Modules\Core\Helpers\HasCommandModelResolution;

trait SearchableCommandUtils
{
    use HasCommandModelResolution {
        getModelClass as getModelClassFromTrait;
    }

    private function getModelClass(): string|false
    {
        $model = $this->getModelClassFromTrait('model');

        if (!class_uses_trait($model, Searchable::class)) {
            $this->error('Model does not use Searchable trait');
            return false;
        }

        $this->input->setArgument('model', $model);
        return $model;
    }
}
