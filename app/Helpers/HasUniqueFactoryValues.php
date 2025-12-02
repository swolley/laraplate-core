<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasUniqueFactoryValues
{
    protected int $maxAttempts = 10;

    /**
     * Generate a unique value using faker with database fallback.
     *
     * @param  callable  $fakerCall  The faker callable(e.g., fn() => fake()->unique()->email())
     * @param  class-string<Model>|null  $modelClass  The model class to check uniqueness against
     * @param  string|null  $column  The column to check for uniqueness
     * @param  int  $maxAttempts  Maximum attempts before using fallback
     */
    protected function uniqueValue(
        callable $fakerCall,
        ?string $modelClass = null,
        ?string $column = null,
        ?int $maxAttempts = null,
    ): string {
        $maxAttempts = $maxAttempts ?? $this->maxAttempts;

        $attempting_values = [];
        for ($i = 0; $i < $maxAttempts; $i++) {
            $attempting_values[] = $fakerCall();
        }


        $attempting_values = array_unique($attempting_values);

        $attempts = count($attempting_values);

        while($attempts <= $maxAttempts) {
            $available = $maxAttempts - $attempts;
            array_push($attempting_values, ...$this->generateFallbackValues($available, $fakerCall));
            $attempts += $available;
            
            // If model and column are provided, verify database uniqueness
            if ($modelClass && $column) {
                $already_existing_values = $modelClass::query()->whereIn($column, $attempting_values)->select($column)->pluck($column)->unique()->toArray();
                $attempting_values = array_diff($attempting_values, $already_existing_values);


                if (count($attempting_values) > 0) {
                    break;
                }

                if ($attempts >= $maxAttempts) {
                    throw new Exception('Failed to generate a unique value after ' . $maxAttempts . ' attempts');
                }
            }
        }

        return head($attempting_values);
    }

    private function generateFallbackValues(int $total, callable $fakerCall): array
    {
        $fallback_values = [];
        for ($i = 0; $i < $total; $i++) {
            $fallback_values[] = $this->generateFallbackValue($fakerCall);
        }
        return $fallback_values;
    }

    /**
     * Generate a unique slug from a name.
     *
     * @param  string  $name  The base name
     * @param  class-string<Model>|null  $modelClass  The model class to check uniqueness
     * @param  string  $column  The slug column name
     */
    protected function uniqueSlug(
        string $name,
        ?string $modelClass = null,
        string $column = 'slug',
        ?int $maxAttempts = null,
    ): string {
        return $this->uniqueValue(
            fn () => Str::slug($name),
            $modelClass,
            $column,
            $maxAttempts,
        );
    }

    /**
     * Generate a unique email.
     *
     * @param  class-string<Model>|null  $modelClass  The model class to check uniqueness
     * @param  callable|null  $fakerCall  The faker callable(e.g., fn() => fake()->unique()->email())
     * @param  string|null  $column  The column to check for uniqueness
     */
    protected function uniqueEmail(?string $modelClass = null, ?callable $fakerCall = null, ?string $column = 'email', ?int $maxAttempts = null): string
    {
        return $this->uniqueValue(
            $fakerCall ?? fn () => fake()->unique()->email(),
            $modelClass,
            $column,
            $maxAttempts,
        );
    }

    /**
     * Generate a unique phone number.
     *
     * @param  class-string<Model>|null  $modelClass  The model class to check uniqueness
     */
    protected function uniquePhoneNumber(?string $modelClass = null, ?int $maxAttempts = null): string
    {
        return $this->uniqueValue(
            fn () => fake()->unique()->phoneNumber(),
            $modelClass,
            'phone',
            $maxAttempts,
        );
    }

    /**
     * Generate a unique URL.
     *
     * @param  class-string<Model>|null  $modelClass  The model class to check uniqueness
     */
    protected function uniqueUrl(?string $modelClass = null, ?int $maxAttempts = null): string
    {
        return $this->uniqueValue(
            fn () => fake()->unique()->url(),
            $modelClass,
            'url',
            $maxAttempts,
        );
    }

    /**
     * Generate a fallback value when unique() is exhausted.
     *
     * @param  callable  $fakerCall  The original faker callable
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
            if (str_contains((string) $base_value, '@')) {
                [$local, $domain] = explode('@', (string) $base_value, 2);

                return "{$local}_{$unique_id}@{$domain}";
            }

            return "{$base_value}_{$unique_id}";
        } catch (Exception) {
            // Ultimate fallback
            return "generated_{$timestamp}_{$random}_{$unique_id}";
        }
    }
}
