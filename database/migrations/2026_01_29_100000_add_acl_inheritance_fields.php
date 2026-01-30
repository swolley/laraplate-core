<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add fields to support ACL inheritance and override logic.
 *
 * The ACL system works with the following rules:
 * 1. If a role has an ACL for a permission → use it (overrides parent)
 * 2. If a role has NO ACL → inherit from parent role
 * 3. If unrestricted=true → ACL is "transparent", doesn't contribute filters
 * 4. Multiple non-hierarchical roles → combine contributing ACLs with OR (union)
 * 5. If NO ACLs contribute filters → user sees everything
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('acls', function (Blueprint $table): void {
            $table->boolean('unrestricted')
                ->default(false)
                ->after('sort')
                ->comment('If true, this ACL is transparent and does not contribute filters to the query');

            $table->unsignedSmallInteger('priority')
                ->default(0)
                ->after('unrestricted')
                ->comment('Higher priority ACLs are evaluated first (for conflict resolution)');

            $table->boolean('enabled')
                ->default(true)
                ->after('priority')
                ->comment('If false, this ACL is ignored (allows temporary disable)');

            $table->index(['permission_id', 'enabled', 'deleted_at'], 'acls_active_IDX');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acls', function (Blueprint $table): void {
            $table->dropIndex('acls_active_IDX');
            $table->dropColumn(['unrestricted', 'priority', 'enabled']);
        });
    }
};
