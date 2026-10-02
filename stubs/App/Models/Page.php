<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EntityType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Contracts\IDynamicEntityTypable;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\Core\Models\Concerns\HasDynamicContents;
use Modules\Core\Overrides\Model;
use Override;

/**
 * Test-only App content with untranslated dynamic contents: every value lives in its own
 * `components` / `shared_components` columns. Tests create its table with {@see self::createTable()}.
 *
 * @phpstan-use HasDynamicContents<Page>
 */
final class Page extends Model
{
    // region Traits
    use HasDynamicContents {
        HasDynamicContents::getRules as private getRulesDynamicContents;
        HasDynamicContents::casts as private dynamicContentsCasts;
    }
    // endregion

    public const string TABLE = 'app_pages';

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
        return EntityType::Pages;
    }

    /**
     * @return class-string<Entity>
     */
    public static function getEntityModelClass(): string
    {
        return Entity::class;
    }

    /**
     * Create the table with the columns HasDynamicContents needs.
     */
    public static function createTable(): void
    {
        Schema::create(self::TABLE, static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->constrained(CoreTables::Entities->value)->cascadeOnDelete();
            $table->foreignId('presettable_id')->constrained(CoreTables::Presettables->value)->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->json('components')->nullable();
            $table->json('shared_components')->nullable();

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public static function dropTable(): void
    {
        Schema::dropIfExists(self::TABLE);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules[Model::DEFAULT_RULE] = array_merge($rules[Model::DEFAULT_RULE], $this->getRulesDynamicContents());

        return $rules;
    }

    #[Override]
    protected function casts(): array
    {
        return array_merge($this->dynamicContentsCasts(), [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ]);
    }
}
