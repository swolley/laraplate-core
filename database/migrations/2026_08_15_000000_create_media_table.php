<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = CoreTables::Media->value;

        if (Schema::hasTable($table_name)) {
            return;
        }

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();

            $table->morphs('model', "{$table_name}_morph_idx");
            $table->uuid()->nullable()->unique("{$table_name}_uuid_UN")->comment('The UUID of the media');
            $table->string('collection_name')->nullable(false)->comment('The collection name of the media');
            $table->string('name')->nullable(false)->comment('The name of the media');
            $table->string('file_name')->nullable(false)->comment('The file name of the media');
            $table->string('mime_type')->nullable()->comment('The mime type of the media');
            $table->string('disk')->nullable(false)->comment('The disk of the media');
            $table->string('conversions_disk')->nullable()->comment('The conversions disk of the media');
            $table->unsignedBigInteger('size')->nullable(false)->comment('The size of the media');
            $table->json('manipulations')->nullable(false)->comment('The manipulations of the media');
            $table->json('custom_properties')->nullable(false)->comment('The custom properties of the media');
            $table->json('generated_conversions')->nullable(false)->comment('The generated conversions of the media');
            $table->json('responsive_images')->nullable(false)->comment('The responsive images of the media');
            $table->integer('order_column')->nullable(false)->default(0)->index("{$table_name}_order_column_IDX")->comment('The order column of the media');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CoreTables::Media->value);
    }
};
