<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Core\Helpers\MigrateUtils;
use Modules\Core\Inspector\Types\DoctrineTypeEnum;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $types = [
                DoctrineTypeEnum::BOOLEAN->value,
                DoctrineTypeEnum::INTEGER->value,
                DoctrineTypeEnum::FLOAT->value,
                DoctrineTypeEnum::STRING->value,
                DoctrineTypeEnum::JSON->value,
                DoctrineTypeEnum::DATE->value,
            ];

            $table->id();
            $table->string('name', 50)->nullable(false)->comment('The name of the setting');
            $table->json('value')->nullable(false)->comment('The value of the setting');
            $table->boolean('encrypted')->nullable(false)->comment('Is the value encrypted');
            $table->json('choices')->nullable(true)->comment('Constrained available values');
            $table->addColumn('enum', 'type', ['allowed' => $types, 'length' => 20])->comment('The type of the setting');
            $table->string('group_name', 50)->nullable(false)->comment('The group name of the setting');
            $table->string('description')->nullable(false)->comment('The description of the setting');
            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
