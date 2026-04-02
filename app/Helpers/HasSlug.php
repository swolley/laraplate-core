<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::saving(function (Model $model): void {
            // Laravel always dispatches saving with the model instance matching the class that registered the listener.
            // @codeCoverageIgnoreStart
            if (! $model instanceof static) {
                return;
            }
            // @codeCoverageIgnoreEnd

            if (! isset($model->attributes['slug']) && ! $model->isDirty('slug')) {
                $model->slug = $model->generateSlug();
            }
        });
    }

    /**
     * Get the placeholders for the slug.
     *
     * The placeholders can be a string or a callable that returns a value.
     * The callable will be called with the model instance.
     * The string wrapped in curly braces will be replaced with the corresponding model value. This dynamic placeholders can contain a format string after a colon.
     * The static string will be used as is
     *
     * @return array<string|callable(Model):mixed>
     */
    public static function slugPlaceholders(): array
    {
        return ['{name}'];
    }

    // public function initializeHasSlug(): void
    // {
    //     // Slug is now in translations table, don't add to fillable
    // }

    public function generateSlug(): string
    {
        $slugger = config('cms.slugger', Str::class . '::slug');
        $slug = array_reduce($this->slugValues(), static fn (string $slug, mixed $value): string => $slug . '-' . ($value ? mb_trim((string) $value) : ''), '');

        return call_user_func($slugger, mb_ltrim($slug, '-'));
    }

    protected function slugValues(): array
    {
        return array_map(function (callable|string $placeholder): string {
            if (is_callable($placeholder)) {
                return static::slugifySlug($placeholder($this));
            }

            if (str_contains($placeholder, '{')) {
                $name = str_replace(['{', '}'], '', $placeholder);
                $format = null;

                if (str_contains($name, ':')) {
                    [$name, $format] = explode(':', $name);
                }

                $value = $this->getSlugValue($name, $format);

                if ($value !== null && $value !== '') {
                    return static::slugifySlug($value);
                }
            }

            return $placeholder;
        }, static::slugPlaceholders());
    }

    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->attributes['slug'] ?? null,
        );
    }

    private static function slugifySlug(string $slug): string
    {
        return Str::slug($slug, dictionary: ['.' => '']);
    }

    private static function formatSlugValue(mixed $value, ?string $format = null): ?string
    {
        if (is_iterable($value)) {
            $value = is_array($value) ? array_first($value) : (method_exists($value, 'first') ? $value->first() : null);
        }

        if (! $value) {
            return null;
        }

        $additional_format_parameters = [];

        if (is_string($format)) {
            $exploded = explode(',', $format);
            $format = array_shift($exploded);
            $additional_format_parameters = array_map(function (string $param): int|string {
                $trimmed = mb_trim($param);

                return is_numeric($trimmed) ? (int) $trimmed : $trimmed;
            }, $exploded);
            unset($exploded);
        }

        if (is_string($format) && function_exists($format)) {
            $value = $format($value, ...$additional_format_parameters);
            $format = null;
        } elseif ($value instanceof DateTimeInterface) {
            $value = $value->format($format ?? 'Y-m-d');
            $format = null;
        }

        return sprintf($format ?? '%s', $value);
    }

    /**
     * Get the value for the slug.
     *
     * @param  string  $name  The name of the attribute.
     * @param  string  $format  The format string.
     */
    private function getSlugValue(string $name, ?string $format = null): ?string
    {
        $value = data_get($this, $name);

        return static::formatSlugValue($value, $format);
    }
}
