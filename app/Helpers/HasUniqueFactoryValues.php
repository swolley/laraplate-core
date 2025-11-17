<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasUniqueFactoryValues
{
    /**
     * Generate a unique value using faker with database fallback.
     *
     * @param  callable  $fakerCall  The faker callable (e.g., fn() => fake()->unique()->email())
     * @param  class-string<Model>|null  $modelClass  The model class to check uniqueness against
     * @param  string|null  $column  The column to check for uniqueness
     * @param  int  $maxAttempts  Maximum attempts before using fallback
     * @return string
     */
    protected function uniqueValue(
        callable $fakerCall,
        ?string $modelClass = null,
        ?string $column = null,
        int $maxAttempts = 10
    ): string {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            try {
                $value = $fakerCall();

                // If model and column are provided, verify database uniqueness
                if ($modelClass && $column && $modelClass::query()->where($column, $value)->exists()) {
                    $attempts++;
                    continue;
                }

                return $value;
            } catch (Exception $e) {
                $attempts++;

                if ($attempts >= $maxAttempts) {
                    break;
                }
            }
        }

        // Fallback: generate with timestamp and random suffix
        return $this->generateFallbackValue($fakerCall);
    }

    /**
     * Generate a unique slug from a name.
     *
     * @param  string  $name  The base name
     * @param  class-string<Model>|null  $modelClass  The model class to check uniqueness
     * @param  string  $column  The slug column name
     * @return string
     */
    protected function uniqueSlug(
        string $name,
        ?string $modelClass = null,
        string $column = 'slug'
    ): string {
        $base_slug = Str::slug($name);
        $slug = $base_slug;
        $counter = 1;

        if ($modelClass) {
            while ($modelClass::query()->where($column, $slug)->exists()) {
                $slug = $base_slug . '-' . $counter;
                $counter++;

                // Safety limit
                if ($counter > 10000) {
                    $slug = $base_slug . '-' . uniqid() . '-' . time();
                    break;
                }
            }
        }

        return $slug;
    }

    /**
     * Generate a unique email.
     *
     * @param  class-string<Model>|null  $modelClass  The model class to check uniqueness
     * @param  callable|null  $fakerCall  The faker callable (e.g., fn() => fake()->unique()->email())
     * @param  string|null  $column  The column to check for uniqueness
     * @return string
     */
    protected function uniqueEmail(?string $modelClass = null, ?callable $fakerCall = null, ?string $column = 'email'): string
    {
        return $this->uniqueValue(
            $fakerCall ?? fn () => fake()->unique()->email(),
            $modelClass,
            $column
        );
    }

    /**
     * Generate a unique phone number.
     *
     * @param  class-string<Model>|null  $modelClass  The model class to check uniqueness
     * @return string
     */
    protected function uniquePhoneNumber(?string $modelClass = null): string
    {
        return $this->uniqueValue(
            fn () => fake()->unique()->phoneNumber(),
            $modelClass,
            'phone'
        );
    }

    /**
     * Generate a unique URL.
     *
     * @param  class-string<Model>|null  $modelClass  The model class to check uniqueness
     * @return string
     */
    protected function uniqueUrl(?string $modelClass = null): string
    {
        return $this->uniqueValue(
            fn () => fake()->unique()->url(),
            $modelClass,
            'url'
        );
    }

    /**
     * Generate a fallback value when unique() is exhausted.
     *
     * @param  callable  $fakerCall  The original faker callable
     * @return string
     */
    private function generateFallbackValue(callable $fakerCall): string
    {
        $timestamp = now()->timestamp;
        $random = fake()->numberBetween(1000, 9999);
        $unique_id = uniqid('', true);

        // Try to get a base value from faker without unique
        try {
            // Remove unique() from the callable if possible
            $base_value = $fakerCall();
            // If it's an email-like value, extract the base
            if (str_contains($base_value, '@')) {
                [$local, $domain] = explode('@', $base_value, 2);
                return "{$local}_{$unique_id}@{$domain}";
            }

            return "{$base_value}_{$unique_id}";
        } catch (Exception $e) {
            // Ultimate fallback
            return "generated_{$timestamp}_{$random}_{$unique_id}";
        }
    }
}