<?php

declare(strict_types=1);

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\UserController;
use Modules\Core\Rules\PreferencesBag;

Route::controller(UserController::class)->name('auth.')->group(function (): void {
    // Keep the `auth` group session stack (StartSession, cookies, …) but allow
    // guests: SPA login calls this right after Fortify to read the new session.
    // `withoutMiddleware('auth')` would drop the whole group (no session → always anonymous).
    Route::get('/user/profile-information', 'userInfo')
        ->withoutMiddleware([Authenticate::class])
        ->name('userInfo');
    // Self-service profile writes on the caller's own row (auth-gated by the group).
    Route::patch('/user/preferences', 'updatePreferences')->name('updatePreferences');
    Route::delete('/user/preferences', 'deletePreferences')->name('deletePreferences');
    Route::delete('/user/preferences/{namespace}', 'deletePreferencesNamespace')
        ->where('namespace', PreferencesBag::NAMESPACE_REGEX)
        ->name('deletePreferencesNamespace');
    Route::patch('/user/first-login-complete', 'completeFirstLogin')->name('completeFirstLogin');
    Route::post('/impersonate', 'impersonate')->can('impersonate')->name('impersonate');
    Route::post('/leave-impersonate', 'leaveImpersonate')->can('impersonate')->name('leaveImpersonate');
    // Route::patch('/configs', 'updateConfigs')->can('edit')->name('updateConfigs');
    Route::get('/still-here', 'maintainSession')->name('maintainSession');

    if (config('core.auth.social_login.enabled')) {
        $social_services = ['facebook', 'twitter', 'twitter-oauth-2', 'linkedin-openid', 'google', 'github', 'gitlab', 'bitbucket', 'slack', 'slack-openid'];
        Route::get('/{service}/redirect', 'socialLoginRedirect')->whereIn('service', $social_services);
        Route::get('/{service}/callback', 'socialLoginCallback')->whereIn('service', $social_services);
    }
});
