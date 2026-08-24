<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The framework-standard `notifications` table (unprefixed, like `users` and
     * `jobs`) so Laravel's Notifiable and Filament's native database-notifications
     * bell both find it. `module_name` is the one addition: a queryable copy of the
     * `data->scope` key, populated at write time by {@see Modules\Core\Models\Notification},
     * so a module-scoped tray can filter without digging into the JSON payload.
     */
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->string('module_name')->nullable()->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
