<?php

declare(strict_types=1);

return [
    // User foreign key name of versions table.
    'user_foreign_key' => 'user_id',

    // The model class for store versions.
    'version_model' => Modules\Core\Models\Version::class,

    /**
     * Use uuid for version id.
     */
    'uuid' => false,
];
