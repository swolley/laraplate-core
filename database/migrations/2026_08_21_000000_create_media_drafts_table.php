<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = CoreTables::MediaDrafts->value;

        if (Schema::hasTable($table_name)) {
            return;
        }

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('user_id')->nullable(false)->constrained(CoreTables::Users->value, 'id', "{$table_name}_user_id_FK")->cascadeOnDelete()->comment('The user who owns the pending media bucket');
            $table->uuid('token')->nullable(false)->unique("{$table_name}_token_UN")->comment('The client-generated token that keys the pending bucket');
            $table->string('target_module')->nullable(false)->comment('The module of the owner entity the media will be claimed onto');
            $table->string('target_entity')->nullable(false)->comment('The owner entity the media will be claimed onto');

            $table->index(['user_id', 'token'], "{$table_name}_user_token_IDX");

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: false,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CoreTables::MediaDrafts->value);
    }
};
