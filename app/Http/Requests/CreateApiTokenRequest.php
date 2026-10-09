<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Modules\Core\Rules\NetworkAddress;
use Modules\Core\Services\ApiTokenService;

/**
 * Shape of a personal API token to create. What the values may be (abilities among the api permissions the
 * caller holds, expiry within the configured life) is decided by {@see ApiTokenService}, which answers 422.
 *
 * @property string $name
 * @property list<string> $abilities
 * @property string|null $expires_at
 * @property list<string>|null $allowed_cidrs
 */
final class CreateApiTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function tokenName(): string
    {
        return $this->string('name')->toString();
    }

    /**
     * @return list<string>
     */
    public function abilityList(): array
    {
        return $this->stringList('abilities') ?? [];
    }

    public function expiry(): ?CarbonInterface
    {
        $value = $this->input('expires_at');

        return is_string($value) && $value !== '' ? Date::parse($value) : null;
    }

    /**
     * @return list<string>|null
     */
    public function networkList(): ?array
    {
        return $this->stringList('allowed_cidrs');
    }

    /**
     * @return array<string, array<int, NetworkAddress|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', 'distinct'],
            'expires_at' => ['nullable', 'date'],
            'allowed_cidrs' => ['nullable', 'array'],
            'allowed_cidrs.*' => ['string', new NetworkAddress],
        ];
    }

    /**
     * @return list<string>|null
     */
    private function stringList(string $key): ?array
    {
        $value = $this->input($key);

        return is_array($value) ? array_values(array_filter($value, is_string(...))) : null;
    }
}
