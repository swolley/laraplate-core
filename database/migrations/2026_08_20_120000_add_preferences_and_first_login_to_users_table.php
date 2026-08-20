<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Whether the account still needs the first-login onboarding flow.
            $table->boolean('is_first_login')->default(true);
            // Server-persisted UI preferences (theme, density, layout…), synced
            // from the SPA so a user's chrome follows them across devices.
            $table->json('preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_first_login', 'preferences']);
        });
    }
};
