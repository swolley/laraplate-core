<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Http\Requests\CreateApiTokenRequest;
use Modules\Core\Models\User;
use Modules\Core\Services\ApiTokenService;
use Symfony\Component\HttpFoundation\Response;

/**
 * The caller's own API tokens: list, create, revoke. Session routes (`auth` group): a bearer token never
 * manages tokens. The plain token is in the response of the creation and nowhere else.
 */
final class ApiTokenController extends Controller
{
    public function __construct(private readonly ApiTokenService $tokens)
    {
        parent::__construct();
    }

    public function list(Request $request): JsonResponse
    {
        $rows = $this->owner()->tokens()->latest('id')->get()
            ->map(fn (PersonalAccessToken $token): array => $this->describe($token))
            ->values()
            ->all();

        return response()->json(['data' => $rows]);
    }

    public function create(CreateApiTokenRequest $request): JsonResponse
    {
        $token = $this->tokens->issue(
            $this->owner(),
            $request->tokenName(),
            $request->abilityList(),
            $request->expiry(),
            $request->networkList(),
        );

        return response()
            ->json(['data' => [...$this->describe($token->accessToken), 'token' => $token->plainTextToken]], Response::HTTP_CREATED)
            ->header('Cache-Control', 'no-store');
    }

    public function revoke(int $token): JsonResponse
    {
        $this->owner()->tokens()->whereKey($token)->firstOrFail()->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * The signed-in user as the model the auth provider hands out, whatever class the session resolved.
     */
    private function owner(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $this->tokens->tokenable($user);
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(PersonalAccessToken $token): array
    {
        $cidrs = $token->getRawOriginal('allowed_cidrs');

        return [
            'id' => $token->getKey(),
            'name' => $token->name,
            'abilities' => $token->abilities ?? [],
            'allowed_cidrs' => is_string($cidrs) ? json_decode($cidrs, true) : null,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
        ];
    }
}
