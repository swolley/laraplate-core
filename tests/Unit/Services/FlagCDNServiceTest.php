<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\Core\Services\FlagCDNService;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    $this->service = new FlagCDNService;
});

it('getFlagsDirectory returns public flags path', function (): void {
    expect($this->service->getFlagsDirectory())->toBe(public_path('flags'));
});

it('getUrl returns local url when flag file already exists', function (): void {
    $flags_dir = public_path('flags');
    if (! is_dir($flags_dir)) {
        mkdir($flags_dir, 0755, true);
    }
    $flag_file = "{$flags_dir}/it_40x30.png";
    file_put_contents($flag_file, 'x');

    try {
        $url = $this->service->getUrl('it', 40, 30, 'png');
        expect($url)->toBe('/flags/it_40x30.png');
    } finally {
        @unlink($flag_file);
    }
});

it('getUrl downloads and returns local url when file missing and download succeeds', function (): void {
    Http::fake([
        'https://flagcdn.com/40x30/it.png' => Http::response('binary', 200),
    ]);

    $url = $this->service->getUrl('it', 40, 30, 'png');

    expect($url)->toBe('/flags/it_40x30.png');
    $flag_file = public_path('flags/it_40x30.png');
    expect(file_exists($flag_file))->toBeTrue();
    @unlink($flag_file);
});

it('getUrl returns flagcdn url when download fails', function (): void {
    Http::fake([
        'https://flagcdn.com/40x30/it.png' => Http::response(null, 404),
    ]);

    $url = $this->service->getUrl('it', 40, 30, 'png');

    expect($url)->toBe('https://flagcdn.com/40x30/it.png');
});

it('download returns false when file already exists', function (): void {
    $flags_dir = public_path('flags');
    if (! is_dir($flags_dir)) {
        mkdir($flags_dir, 0755, true);
    }
    $flag_file = "{$flags_dir}/en_40x30.png";
    file_put_contents($flag_file, 'x');

    try {
        $result = $this->service->download('en', 40, 30, 'png');
        expect($result)->toBeFalse();
    } finally {
        @unlink($flag_file);
    }
});

it('download returns true when download succeeds', function (): void {
    Http::fake([
        'https://flagcdn.com/40x30/en.png' => Http::response('binary', 200),
    ]);

    $result = $this->service->download('en', 40, 30, 'png');

    expect($result)->toBeTrue();
    $flag_file = public_path('flags/en_40x30.png');
    expect(file_exists($flag_file))->toBeTrue();
    @unlink($flag_file);
});

it('download returns false when download fails', function (): void {
    Http::fake([
        'https://flagcdn.com/40x30/en.png' => Http::response(null, 500),
    ]);

    $result = $this->service->download('en', 40, 30, 'png');

    expect($result)->toBeFalse();
});
