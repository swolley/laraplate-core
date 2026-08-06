<?php

declare(strict_types=1);

function write_profile_fixture(): string
{
    $path = tempnam(sys_get_temp_dir(), 'cgcmd_');
    file_put_contents($path, <<<'CG'
        fl=(1) /app/Foo.php
        fn=(1) Modules\Core\Hot::spot
        1 900
        fl=(1)
        fn=(2) Illuminate\Cheap::call
        1 100
        CG);

    return $path;
}

it('summarizes an existing cachegrind file via --file', function (): void {
    $path = write_profile_fixture();

    $this->artisan('perf:profile', ['--file' => $path, '--limit' => 10])
        ->assertSuccessful()
        ->expectsOutputToContain('Modules\Core\Hot::spot');

    unlink($path);
});

it('fails when neither an endpoint nor --file is provided', function (): void {
    $this->artisan('perf:profile')->assertFailed();
});
