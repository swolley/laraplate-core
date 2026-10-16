<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Modules\Core\Helpers\LocaleContext;
use Modules\Core\Http\Middleware\ApplyBackofficeContentLocale;

/**
 * @param  array<string, mixed>  $session
 */
function backofficeRequest(array $session = []): Request
{
    $request = Request::create('https://example.com/admin');
    $store = new Store('test', new ArraySessionHandler(10));

    foreach ($session as $key => $value) {
        $store->put($key, $value);
    }

    $request->setLaravelSession($store);
    app()->instance('request', $request);

    return $request;
}

beforeEach(function (): void {
    config(['app.available_locales' => ['en', 'it', 'de'], 'app.default_locale' => 'en']);
    LocaleContext::set('de');
});

it('applies the content locale picked in this session', function (): void {
    $request = backofficeRequest([ApplyBackofficeContentLocale::SESSION_KEY => 'it']);

    (new ApplyBackofficeContentLocale)->handle($request, static fn (): Symfony\Component\HttpFoundation\Response => response('ok'));

    expect(LocaleContext::get())->toBe('it')
        ->and(app()->getLocale())->toBe('de');
});

it('starts from the default locale, not the language of the interface', function (): void {
    $request = backofficeRequest();

    (new ApplyBackofficeContentLocale)->handle($request, static fn (): Symfony\Component\HttpFoundation\Response => response('ok'));

    expect(LocaleContext::get())->toBe('en');
});

it('ignores a locale that is not available', function (): void {
    $request = backofficeRequest([ApplyBackofficeContentLocale::SESSION_KEY => 'xx']);

    (new ApplyBackofficeContentLocale)->handle($request, static fn (): Symfony\Component\HttpFoundation\Response => response('ok'));

    expect(LocaleContext::get())->toBe('en');
});
