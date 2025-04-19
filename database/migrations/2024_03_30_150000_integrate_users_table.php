<?php

declare(strict_types=1);

use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Core\Helpers\CommonMigrationFunctions;

return new class() extends Migration
{
    /**
     * Run the migrations.
     *
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->unique('users_username_UN')->nullable(false)->comment('The username of the user');
            $table->string('lang')->nullable(true)->comment('The language of the user');
            $table->datetime('last_login_at')->nullable(true)->comment('The last login date of the user');
            $table->uuid('license_id')->nullable(true)->comment('The license id of the user');
            $table->text('two_factor_secret')->after('password')->nullable()->comment('The two factor secret of the user');
            $table->text('two_factor_recovery_codes')->nullable()->comment('The two factor recovery codes of the user');
            if (Fortify::confirmsTwoFactorAuthentication()) {
                $table->timestamp('two_factor_confirmed_at')->after('two_factor_recovery_codes')->nullable()->comment('The two factor confirmed date of the user');
            }
            CommonMigrationFunctions::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
                hasLocks: true
            );

            $table->foreign('license_id', 'FK_users_licenses')->references('id')->on('licenses')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('username');
            $table->dropColumn('lang');
            $table->dropColumn('last_login_at');
            CommonMigrationFunctions::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true
            );
        });
    }
};
