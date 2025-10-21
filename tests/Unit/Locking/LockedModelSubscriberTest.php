<?php

declare(strict_types=1);

use Modules\Core\Locking\LockedModelSubscriber;

test('subscriber has correct class structure', function (): void {
    $reflection = new ReflectionClass(LockedModelSubscriber::class);
    
    expect($reflection->getName())->toBe('Modules\Core\Locking\LockedModelSubscriber');
    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('subscribe'))->toBeTrue();
    expect($reflection->hasMethod('saving'))->toBeTrue();
    expect($reflection->hasMethod('deleting'))->toBeTrue();
    expect($reflection->hasMethod('replicating'))->toBeTrue();
    expect($reflection->hasMethod('notificationSending'))->toBeTrue();
});

test('subscriber subscribe method has correct signature', function (): void {
    $reflection = new ReflectionMethod(LockedModelSubscriber::class, 'subscribe');
    
    expect($reflection->getNumberOfParameters())->toBe(0);
    expect($reflection->getReturnType()->getName())->toBe('array');
    expect($reflection->isPublic())->toBeTrue();
});

test('subscriber saving method has correct signature', function (): void {
    $reflection = new ReflectionMethod(LockedModelSubscriber::class, 'saving');
    
    expect($reflection->getNumberOfParameters())->toBe(2);
    expect($reflection->getReturnType()->getName())->toBe('bool');
    expect($reflection->isPublic())->toBeTrue();
});

test('subscriber deleting method has correct signature', function (): void {
    $reflection = new ReflectionMethod(LockedModelSubscriber::class, 'deleting');
    
    expect($reflection->getNumberOfParameters())->toBe(2);
    expect($reflection->getReturnType()->getName())->toBe('bool');
    expect($reflection->isPublic())->toBeTrue();
});

test('subscriber replicating method has correct signature', function (): void {
    $reflection = new ReflectionMethod(LockedModelSubscriber::class, 'replicating');
    
    expect($reflection->getNumberOfParameters())->toBe(2);
    expect($reflection->getReturnType()->getName())->toBe('bool');
    expect($reflection->isPublic())->toBeTrue();
});

test('subscriber notificationSending method has correct signature', function (): void {
    $reflection = new ReflectionMethod(LockedModelSubscriber::class, 'notificationSending');
    
    expect($reflection->getNumberOfParameters())->toBe(1);
    expect($reflection->getReturnType()->getName())->toBe('bool');
    expect($reflection->isPublic())->toBeTrue();
});

test('subscriber uses correct imports', function (): void {
    $reflection = new ReflectionClass(LockedModelSubscriber::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('use Illuminate\Database\Eloquent\Model;');
    expect($source)->toContain('use Illuminate\Notifications\Events\NotificationSending;');
    expect($source)->toContain('use Modules\Core\Locking\Exceptions\LockedModelException;');
});

test('subscriber subscribe method returns correct event mappings', function (): void {
    $reflection = new ReflectionClass(LockedModelSubscriber::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('return [');
    expect($source)->toContain('\'eloquent.saving: *\' => \'saving\'');
    expect($source)->toContain('\'eloquent.deleting: *\' => \'deleting\'');
    expect($source)->toContain('\'eloquent.replicating: *\' => \'replicating\'');
    expect($source)->toContain('NotificationSending::class => \'notificationSending\'');
});

test('subscriber saving method handles locked model logic', function (): void {
    $reflection = new ReflectionClass(LockedModelSubscriber::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('new Locked()->allowsModificationsOnLockedObjects()');
    expect($source)->toContain('new Locked()->doesNotUseHasLocks($model)');
    expect($source)->toContain('$model->wasUnlocked()');
    expect($source)->toContain('$model->wasLocked()');
    expect($source)->toContain('$model->isDirty()');
});

test('subscriber saving method throws exception for locked models', function (): void {
    $reflection = new ReflectionClass(LockedModelSubscriber::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('throw_if($model->wasLocked() && $model->isDirty(), LockedModelException::class');
    expect($source)->toContain('\'This model is locked\'');
});

test('subscriber deleting method handles locked model logic', function (): void {
    $reflection = new ReflectionClass(LockedModelSubscriber::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('$model->wasUnlocked()');
    expect($source)->toContain('throw new LockedModelException(\'This model is locked\')');
});

test('subscriber replicating method handles locked model logic', function (): void {
    $reflection = new ReflectionClass(LockedModelSubscriber::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('$model->isUnlocked()');
    expect($source)->toContain('throw new LockedModelException(\'This model is locked\')');
});

test('subscriber notificationSending method handles locked model logic', function (): void {
    $reflection = new ReflectionClass(LockedModelSubscriber::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('$locked->allowsNotificationsToLockedObjects()');
    expect($source)->toContain('$model->isUnlocked()');
    expect($source)->toContain('throw new LockedModelException(\'This model is locked\')');
});

test('subscriber has private helper method', function (): void {
    $reflection = new ReflectionClass(LockedModelSubscriber::class);
    
    expect($reflection->hasMethod('getModelFromPassedParams'))->toBeTrue();
    
    $helperMethod = new ReflectionMethod(LockedModelSubscriber::class, 'getModelFromPassedParams');
    expect($helperMethod->isPrivate())->toBeTrue();
    expect($helperMethod->getNumberOfParameters())->toBe(1);
});

test('subscriber helper method handles array parameters', function (): void {
    $reflection = new ReflectionClass(LockedModelSubscriber::class);
    $source = file_get_contents($reflection->getFileName());
    
    expect($source)->toContain('if (is_array($params) && $params !== [])');
    expect($source)->toContain('return $params[0];');
    expect($source)->toContain('return null;');
});
