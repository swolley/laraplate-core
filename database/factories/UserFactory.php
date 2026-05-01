<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Core\Helpers\HasUniqueFactoryValues;
use Illuminate\Contracts\Auth\Authenticatable;
use Override;

/**
 * @extends Factory<\Modules\Core\Models\User>
 */
final class UserFactory extends Factory
{
    use HasUniqueFactoryValues;

    #[Override]
    public function modelName(): string
    {
        /** @var class-string<Authenticatable>|null $factory_model */
        $factory_model = $this->model;

        return $factory_model ?? parent::modelName();
    }

    /**
     * Define the model's default state.
     */
    #[Override]
    public function definition(): array
    {
        $name = fake()->boolean() ? fake()->name() : fake()->userName();
        $username = Str::slug($name) . (microtime(true) * 10000);

        $email_prefix = fake()->boolean() ? Str::slug(str_replace(' ', '.', $name)) : Str::slug($username);
        $email_suffix = fake()->boolean() ? mb_substr('0' . fake()->numberBetween(1, 99), -2) : '';
        $email = sprintf('%s%s@%s', $email_prefix, $email_suffix, fake()->domainName());

        $model_class = $this->modelName();

        return [
            'name' => $name,
            'username' => $this->uniqueValue(fn () => $username, $model_class, 'username'),
            'email' => $this->uniqueEmail($model_class, fn () => $email),
            'email_verified_at' => fake()->boolean() ? now() : null,
            'password' => Str::random(16),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(static fn () => [
            'email_verified_at' => null,
        ]);
    }
}
