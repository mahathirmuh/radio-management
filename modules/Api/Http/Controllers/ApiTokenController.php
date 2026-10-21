<?php

namespace Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserManagement\Role;
use App\Models\UserManagement\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Api\Models\ApiToken;
use Modules\Api\Services\ApiTokens;

/**
 * The page "API Tokens" (section Settings).
 *
 * Super Admin sees every token and manages the API clients (accounts of applications). Another role that is given the
 * menu sees and makes only tokens of its own, which act as that person, with that person's role.
 *
 * What it answers is in the language of the page (Api::tokens.messages).
 */
class ApiTokenController extends Controller
{
    public function __construct(private ApiTokens $tokens)
    {
    }

    public function index(Request $request)
    {
        $superAdmin = $this->superAdmin($request->user());

        return view('Api::tokens.index', [
            'pageTitle' => 'API Tokens',
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => route('api-tokens.index'), 'label' => 'API Tokens', 'active' => true],
            ],
            'ready' => ApiToken::ready() && User::apiClientReady(),
            'superAdmin' => $superAdmin,
            // An API client never holds Super Admin: a token that leaks must not be able to do everything.
            'roles' => $superAdmin ? Role::query()->where('id', '!=', 1)->orderBy('name')->get(['id', 'name']) : collect(),
            'lifetimes' => ApiToken::LIFETIMES,
            'apiBase' => url('/api/v1'),
            'docsUrl' => url('/docs/api'),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        if ($denied = $this->notReady()) {
            return $denied;
        }
        $me = $request->user();
        $superAdmin = $this->superAdmin($me);

        $tokens = ApiToken::query()
            ->where('tokenable_type', $me->getMorphClass())
            ->when(! $superAdmin, fn ($query) => $query->where('tokenable_id', $me->id))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get();
        $owners = User::withTrashed()->whereIn('id', $tokens->pluck('tokenable_id')->unique())->get()->keyBy('id');

        return response()->json([
            'tokens' => $tokens->map(fn (ApiToken $token) => $this->tokenRow($token, $owners[$token->tokenable_id] ?? null, $me))->values(),
            'clients' => $superAdmin ? $this->clients() : [],
        ]);
    }

    public function storeToken(Request $request): JsonResponse
    {
        if ($denied = $this->notReady()) {
            return $denied;
        }
        $me = $request->user();
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'access' => ['required', Rule::in(['read', 'read_write'])],
            'days' => ['required', 'integer', Rule::in(ApiToken::LIFETIMES)],
            // "me", or the id of an API client (Super Admin).
            'owner' => ['required', 'string', 'max:20'],
        ], [], self::attributes(['name', 'access', 'days', 'owner']));
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }

        $owner = $me;
        if ($request->input('owner') !== 'me') {
            if (! $this->superAdmin($me)) {
                return $this->invalid([__('Api::tokens.messages.only_super_admin_tokens')], 403);
            }
            $owner = User::query()->whereKey((int) $request->input('owner'))->where('api_client', true)->first();
            if ($owner === null) {
                return $this->invalid([__('Api::tokens.messages.no_client')]);
            }
        }
        if (! $owner->isActive()) {
            return $this->invalid([__('Api::tokens.messages.client_inactive')]);
        }

        $made = $this->tokens->createToken($owner, trim((string) $request->input('name')), $request->input('access') === 'read_write', (int) $request->input('days'));

        return response()->json([
            'message' => __('Api::tokens.messages.token_created'),
            // Shown once: only its hash is kept.
            'token' => $made->plainTextToken,
            'data' => $this->tokenRow($made->accessToken, $owner, $me),
        ], 201);
    }

    public function destroyToken(Request $request, int $token): JsonResponse
    {
        if ($denied = $this->notReady()) {
            return $denied;
        }
        $me = $request->user();
        $found = ApiToken::query()->whereKey($token)->where('tokenable_type', $me->getMorphClass())
            ->when(! $this->superAdmin($me), fn ($query) => $query->where('tokenable_id', $me->id))
            ->first();
        if ($found === null) {
            return $this->invalid([__('Api::tokens.messages.token_missing')], 404);
        }
        $this->tokens->revoke($found);

        return response()->json(['message' => __('Api::tokens.messages.token_revoked')]);
    }

    public function storeClient(Request $request): JsonResponse
    {
        if ($denied = $this->notReady() ?? $this->onlySuperAdmin($request)) {
            return $denied;
        }
        $validator = $this->clientValidator($request);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }
        $client = $this->tokens->createClient($request->user(), trim((string) $request->input('name')), Role::findOrFail($request->integer('role_id')));

        return response()->json(['message' => __('Api::tokens.messages.client_created'), 'id' => $client->id], 201);
    }

    public function updateClient(Request $request, int $client): JsonResponse
    {
        if ($denied = $this->notReady() ?? $this->onlySuperAdmin($request)) {
            return $denied;
        }
        $found = User::query()->whereKey($client)->where('api_client', true)->first();
        if ($found === null) {
            return $this->invalid([__('Api::tokens.messages.client_missing')], 404);
        }
        $validator = $this->clientValidator($request, true);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }
        $this->tokens->updateClient($found, trim((string) $request->input('name')), Role::findOrFail($request->integer('role_id')), $request->boolean('active'));

        return response()->json(['message' => __('Api::tokens.messages.client_saved')]);
    }

    public function destroyClient(Request $request, int $client): JsonResponse
    {
        if ($denied = $this->notReady() ?? $this->onlySuperAdmin($request)) {
            return $denied;
        }
        $found = User::query()->whereKey($client)->where('api_client', true)->first();
        if ($found === null) {
            return $this->invalid([__('Api::tokens.messages.client_missing')], 404);
        }
        $this->tokens->deleteClient($found);

        return response()->json(['message' => __('Api::tokens.messages.client_deleted')]);
    }

    /** @return array<int, array<string, mixed>> */
    private function clients(): array
    {
        return User::query()->where('api_client', true)->with('roles:id,name')->withCount('tokens')->orderBy('name')->get()
            ->map(fn (User $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'role' => $client->roles->first() ? ['id' => $client->roles->first()->id, 'name' => $client->roles->first()->name] : null,
                'active' => $client->isActive(),
                'tokens' => (int) $client->tokens_count,
                'created_at' => $client->created_at?->format('d-m-Y'),
            ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function tokenRow(ApiToken $token, ?User $owner, User $me): array
    {
        return [
            'id' => $token->id,
            'name' => $token->name,
            'owner' => $owner === null ? null : [
                'id' => $owner->id,
                'name' => $owner->name,
                'kind' => $owner->isApiClient() ? 'client' : 'person',
                'email' => $owner->isApiClient() ? null : $owner->email,
                'me' => (int) $owner->id === (int) $me->id,
                'deleted' => $owner->trashed(),
                'active' => $owner->isActive(),
            ],
            'access' => $token->accessLabel(),
            'writes' => $token->writes(),
            'created_at' => $token->created_at?->format('d-m-Y H:i'),
            'expires_at' => $token->expires_at?->format('d-m-Y'),
            'expired' => $token->expired(),
            'last_used_at' => $token->last_used_at?->format('d-m-Y H:i'),
            'last_used_ip' => $token->last_used_ip,
        ];
    }

    private function clientValidator(Request $request, bool $changing = false)
    {
        return Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->whereNull('deleted_at'), Rule::notIn([1])],
            'active' => $changing ? ['required', 'boolean'] : ['nullable', 'boolean'],
        ], [
            'role_id.not_in' => __('Api::tokens.messages.no_super_admin'),
        ], self::attributes(['name', 'role_id', 'active']));
    }

    /**
     * The names of these fields in a validation message, in the language of the page.
     *
     * @param list<string> $fields
     * @return array<string, string>
     */
    private static function attributes(array $fields): array
    {
        return collect($fields)->mapWithKeys(fn (string $field) => [$field => __('Api::tokens.attributes.' . $field)])->all();
    }

    private function superAdmin(User $user): bool
    {
        return $user->roles->contains('id', 1);
    }

    private function onlySuperAdmin(Request $request): ?JsonResponse
    {
        return $this->superAdmin($request->user()) ? null : $this->invalid([__('Api::tokens.messages.only_super_admin_clients')], 403);
    }

    /** The answer while the API is not switched on (ApiReady says the same to the API, in English). */
    private function notReady(): ?JsonResponse
    {
        return ApiToken::ready() && User::apiClientReady() ? null : response()->json(['message' => __('Api::tokens.messages.not_ready')], 503);
    }

    private function invalid(array $errors, int $status = 422): JsonResponse
    {
        return response()->json(['message' => __('Api::tokens.messages.invalid'), 'errors' => $errors], $status);
    }
}
