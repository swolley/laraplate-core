<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EntityType;
use App\Models\Translations\AuthorTranslation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Contracts\IDynamicEntityTypable;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\Core\Models\Concerns\HasTranslatedDynamicContents;
use Modules\Core\Overrides\Model;
use Override;

/**
 * Test-only App content with translated dynamic contents: translatable values live in
 * {@see AuthorTranslation}'s `components`, the others in `shared_components`.
 * Tests create its tables with {@see self::createTables()}.
 *
 * @phpstan-use HasTranslatedDynamicContents<Author>
 */
final class Author extends Model
{
    // region Traits
    use HasTranslatedDynamicContents {
        HasTranslatedDynamicContents::getRules as private getRulesTranslatedDynamicContents;
        HasTranslatedDynamicContents::casts as private translatedDynamicContentsCasts;
    }
    // endregion

    public const string TABLE = 'app_authors';

    public const string TRANSLATIONS_TABLE = 'app_authors_translations';

    /**
     * @var string
     */
    #[Override]
    protected $table = self::TABLE;

    #[Override]
    protected $fillable = [
        'name',
    ];

    public static function getEntityType(): IDynamicEntityTypable
    {
        return EntityType::Authors;
    }

    /**
     * @return class-string<Entity>
     */
    public static function getEntityModelClass(): string
    {
        return Entity::class;
    }

    /**
     * Create the base and translations tables with the columns HasTranslatedDynamicContents needs.
     */
    public static function createTables(): void
    {
        Schema::create(self::TABLE, static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->constrained(CoreTables::Entities->value)->cascadeOnDelete();
            $table->foreignId('presettable_id')->constrained(CoreTables::Presettables->value)->cascadeOnDelete();
            $table->json('shared_components')->nullable();
            $table->string('name');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });

        Schema::create(self::TRANSLATIONS_TABLE, static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->constrained(self::TABLE)->cascadeOnDelete();
            $table->string('locale', 10);
            $table->json('components');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->unique(['author_id', 'locale']);
        });
    }

    public static function dropTables(): void
    {
        Schema::dropIfExists(self::TRANSLATIONS_TABLE);
        Schema::dropIfExists(self::TABLE);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules[Model::DEFAULT_RULE] = array_merge($rules[Model::DEFAULT_RULE], $this->getRulesTranslatedDynamicContents());
        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255', 'unique:' . self::TABLE . ',name'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255', 'unique:' . self::TABLE . ',name,' . $this->id],
        ]);

        return $rules;
    }

    #[Override]
    protected function casts(): array
    {
        return array_merge($this->translatedDynamicContentsCasts(), [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ]);
    }
}
