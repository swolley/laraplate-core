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
        Schema::create('places', function (Blueprint $table): void {
            $table->id();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('country')->nullable();
            $table->string('postcode')->nullable();
            $table->string('zone')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $driver = DB::connection()->getDriverName();

            if ($driver === 'pgsql') {
                // Create PostGIS extension first
                DB::unprepared('CREATE EXTENSION IF NOT EXISTS postgis;');
                $table->geometry('geolocation', 'point', 4326)->nullable()->spatialIndex()->comment('The geolocation of the location');
            } elseif (in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
                // Campo opzionale, nessun spatial index per evitare NOT NULL forzato
                $table->geometry('geolocation')->nullable()->comment('The geolocation of the location');
            } elseif ($driver === 'oracle') {
                // Campo opzionale; registriamo metadata SDO e creiamo indice spaziale
                $table->geometry('geolocation')->nullable()->comment('The geolocation of the location');

                DB::unprepared("
                    DECLARE
                        tbl VARCHAR2(128) := 'LOCATIONS';
                        col VARCHAR2(128) := 'GEOLOCATION';
                        srid NUMBER := 4326;
                    BEGIN
                        BEGIN
                            DELETE FROM user_sdo_geom_metadata WHERE table_name = tbl AND column_name = col;
                        EXCEPTION
                            WHEN NO_DATA_FOUND THEN NULL;
                        END;

                        INSERT INTO user_sdo_geom_metadata (table_name, column_name, diminfo, srid)
                        VALUES (
                            tbl,
                            col,
                            MDSYS.SDO_DIM_ARRAY(
                                MDSYS.SDO_DIM_ELEMENT('LONG', -180, 180, 0.005),
                                MDSYS.SDO_DIM_ELEMENT('LAT', -90, 90, 0.005)
                            ),
                            srid
                        );
                    END;
                ");

                DB::unprepared('CREATE INDEX locations_geolocation_spx ON locations(geolocation) INDEXTYPE IS MDSYS.SPATIAL_INDEX');
            }

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->index('city', 'places_city_IDX');
            $table->index('province', 'places_province_IDX');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
