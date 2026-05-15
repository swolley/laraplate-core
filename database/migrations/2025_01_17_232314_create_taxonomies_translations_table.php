<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $taxonomies_translations_table = CoreTables::TaxonomiesTranslations->value;
        Schema::create($taxonomies_translations_table, static function (Blueprint $table) use ($taxonomies_translations_table): void {
            $table->id();
            $table->foreignId('taxonomy_id')->nullable(false)->constrained(CoreTables::Taxonomies->value, 'id', "{$taxonomies_translations_table}_taxonomy_id_FK")->cascadeOnDelete()->comment('The category that the translation belongs to');
            $table->string('locale', 10)->nullable(false)->index("{$taxonomies_translations_table}_locale_IDX")->comment('The locale of the translation');
            $table->string('name')->nullable(false)->comment('The translated name of the taxonomy');
            $table->string('slug')->nullable(false)->index("{$taxonomies_translations_table}_slug_IDX")->comment('The translated slug of the taxonomy');
            $table->json('components')->nullable(false)->comment('The translated taxonomy components');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->unique(['taxonomy_id', 'locale'], "{$taxonomies_translations_table}_taxonomy_locale_UN");
            $table->index(['locale', 'slug'], "{$taxonomies_translations_table}_locale_slug_IDX");
        });

        // Add fulltext indexes for databases that support them
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE {$taxonomies_translations_table} ADD FULLTEXT {$taxonomies_translations_table}_name_IDX (name)");
        } elseif (DB::getDriverName() === 'pgsql') {
            // PostgreSQL fulltext search indexes
            // TODO: This is temporary fixed to english for now
            DB::statement("CREATE INDEX {$taxonomies_translations_table}_name_fts_idx ON {$taxonomies_translations_table} USING gin(to_tsvector('english', name))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CoreTables::TaxonomiesTranslations->value);
    }
};
