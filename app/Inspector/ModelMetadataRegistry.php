<?php

declare(strict_types=1);

namespace Modules\Core\Inspector;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton registry for per-class Model metadata.
 * Avoids repeated ReflectionClass, class_uses_recursive and model instantiation.
 */
final class ModelMetadataRegistry
{
    private static ?self $instance = null;

    /**
     * @var array<class-string<Model>, ModelMetadata>
     */
    private array $metadata = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * @param  class-string<Model>  $class
     */
    public function get(string $class): ModelMetadata
    {
        return $this->metadata[$class] ??= ModelMetadata::fromClass($class);
    }

    /**
     * @param  class-string<Model>  $class
     */
    public function has(string $class): bool
    {
        return isset($this->metadata[$class]);
    }

    /**
     * @param  class-string<Model>  $class
     */
    public function forget(string $class): void
    {
        unset($this->metadata[$class]);
    }

    public function clearAll(): void
    {
        $this->metadata = [];
    }
}
