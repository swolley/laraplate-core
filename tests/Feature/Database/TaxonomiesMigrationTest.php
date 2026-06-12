<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('uses php 8 compatible mysql signal syntax in taxonomies migration', function (): void {
    $path = module_path('Core', 'database/migrations/2024_11_28_225853_create_taxonomies_table.php');
    $contents = File::get($path);

    expect($contents)->toContain("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'parent_id cannot reference self'");
    expect($contents)->not->toContain("SIGNAL SQLSTATE \\'45000\\'");
});

it('builds mysql trigger sql without escaped quote artifacts', function (): void {
    $taxonomies_table = 'core_taxonomies';

    $sql = <<<SQL
        CREATE TRIGGER taxonomies_parent_check_insert
        BEFORE INSERT ON {$taxonomies_table}
        FOR EACH ROW
        BEGIN
            IF NEW.parent_id IS NOT NULL AND NEW.parent_id = NEW.id THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'parent_id cannot reference self';
            END IF;
        END;
    SQL;

    expect($sql)->toContain("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'parent_id cannot reference self'");
    expect($sql)->not->toContain('\\\'');
});
