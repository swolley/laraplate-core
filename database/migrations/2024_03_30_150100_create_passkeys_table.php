<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $passkeys_table = CoreTables::Passkeys->value;
        Schema::create($passkeys_table, function (Blueprint $table) use ($passkeys_table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('The user who registered the passkey');
            $table->string('name')->comment('The name the user gave to the passkey');
            $table->string('credential_id')->unique()->comment('The WebAuthn credential identifier');
            $table->json('credential')->comment('The public key credential');
            $table->timestamp('last_used_at')->nullable()->comment('The last login made with the passkey');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
            );

            $table->foreign('user_id')->references('id')->on(CoreTables::Users->value)->cascadeOnDelete();
            $table->index('user_id', "{$passkeys_table}_user_IDX");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CoreTables::Passkeys->value);
    }
};
