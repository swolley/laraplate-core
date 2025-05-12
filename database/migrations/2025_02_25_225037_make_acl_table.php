<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('acls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade')->comment('The permission id of the acl');
            $table->json('filters')->nullable()->comment('The filters of the acl');
            $table->json('sort')->nullable()->comment('The sort of the acl');
            $table->string('description')->nullable()->comment('The description of the acl');
            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->index(['permission_id', 'deleted_at'], 'acls_permissions_IDX');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acls');
    }
};
