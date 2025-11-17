<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Core\Helpers\HasUniqueFactoryValues;
use Modules\Core\Models\User;
use Override;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Core\Models\User>
 */
final class UserFactory extends Factory
{
    use HasUniqueFactoryValues;

    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<User>
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<Carbon|string>
     *
     * @psalm-return array{name: string, email: string, email_verified_at: Carbon, password: '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', remember_token: string}
     */
    #[Override]
    public function definition(): array
    {
        $name = fake()->boolean() ? fake()->name() : fake()->userName();
        $username = Str::slug($name) . (fake()->boolean() ? fake()->numberBetween(0, 999999) : '');
        
        $email_prefix = fake()->boolean() ? Str::slug(str_replace(' ', '.', $name)) : Str::slug($username);
        $email_suffix = fake()->boolean() ? mb_substr('0' . fake()->numberBetween(1, 99), -2) : '';
        $email = sprintf('%s%s@%s', $email_prefix, $email_suffix, fake()->domainName());
        
        return [
            'name' => $name,
            'username' => $this->uniqueValue(fn () => $username, $this->model, 'username'),
            'email' => $this->uniqueEmail($this->model, fn () => $email),
            'email_verified_at' => fake()->boolean() ? now() : null,
            'password' => Str::random(16),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }
}
