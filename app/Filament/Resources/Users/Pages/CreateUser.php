<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Users\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\Users\UserResource;
use Override;

final class CreateUser extends CreateRecord
{
    #[Override]
    protected static string $resource = UserResource::class;
}
