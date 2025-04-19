<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Core\Helpers\CommonMigrationFunctions;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('acls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade')->comment('The permission id of the acl');
            $table->json('filters')->nullable()->comment('The filters of the acl');
            $table->json('sort')->nullable()->comment('The sort of the acl');
            $table->string('description')->nullable()->comment('The description of the acl');
            CommonMigrationFunctions::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true
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
