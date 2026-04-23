<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('taxonomies_translations', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('taxonomy_id')->nullable(false)->constrained('taxonomies', 'id', 'taxonomies_translations_taxonomy_id_FK')->cascadeOnDelete()->comment('The category that the translation belongs to');
            $table->string('locale', 10)->nullable(false)->index('taxonomies_translations_locale_IDX')->comment('The locale of the translation');
            $table->string('name')->nullable(false)->comment('The translated name of the taxonomy');
            $table->string('slug')->nullable(false)->index('taxonomies_translations_slug_IDX')->comment('The translated slug of the taxonomy');
            $table->json('components')->nullable(false)->comment('The translated taxonomy components');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->unique(['taxonomy_id', 'locale'], 'taxonomies_translations_taxonomy_locale_UN');
            $table->index(['locale', 'slug'], 'taxonomies_translations_locale_slug_IDX');
        });

        // Add fulltext indexes for databases that support them
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE taxonomies_translations ADD FULLTEXT taxonomies_translations_name_IDX (name)');
        } elseif (DB::getDriverName() === 'pgsql') {
            // PostgreSQL fulltext search indexes
            // TODO: This is temporary fixed to english for now
            DB::statement('CREATE INDEX taxonomies_translations_name_fts_idx ON taxonomies_translations USING gin(to_tsvector(\'english\', name))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxonomies_translations');
    }
};
