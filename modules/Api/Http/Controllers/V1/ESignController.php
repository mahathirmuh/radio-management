<?php

namespace Modules\Api\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\RadioManagement\Http\Controllers\ESignController as ESignPage;
use Modules\RadioManagement\Models\ItSignature;
use Modules\RadioManagement\Models\ItSignatureUse;
use Modules\RadioManagement\Services\ESigns;

/**
 * "E-Sign Master": the signatures of the people at ICT that go on the checkout form. Through the API they can be read and
 * switched off; never their picture. A signature is registered, replaced, switched on again or removed on the page.
 *
 * @tags E-Sign
 */
class ESignController extends ApiController
{
    public function __construct(private readonly ESigns $signatures)
    {
    }

    /**
     * List the signatures
     *
     * Whose each is, the name printed under it, whether it is in use, and how often it was put on a form.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless(ItSignature::ready(), 503, ESignPage::notReady());
        $request->validate([
            /** In the printed name, and the name and e-mail of the account. */
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $page = $this->signatures->search((string) $request->input('search', ''))->paginate($this->perPage($request));
        $uses = $this->signatures->useCounts($page->getCollection()->modelKeys());

        return response()->json([
            'data' => $page->getCollection()->map(fn (ItSignature $one) => $this->present($one, $uses[$one->id] ?? null))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'from' => $page->firstItem(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'to' => $page->lastItem(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Where a signature was put
     *
     * The checkout forms it was put on (opened by its account), the latest first: the last 100, and how many in all.
     */
    public function uses(int $signature): JsonResponse
    {
        $one = $this->find($signature);

        return response()->json([
            'data' => ItSignatureUse::query()->where('signature_id', $one->id)->orderByDesc('id')->limit(100)->get()
                ->map(fn (ItSignatureUse $use) => [
                    'at' => $use->created_at?->toIso8601String(),
                    /** The account that opened the form. */
                    'user' => $use->user_name,
                    /** The radio of the form: "ID 1010 · SN 867TBF3759 · Budi". */
                    'radio' => $use->radio_label,
                    'radio_id' => $use->radio_id === null ? null : (int) $use->radio_id,
                ])->values(),
            'total' => ItSignatureUse::query()->where('signature_id', $one->id)->count(),
        ]);
    }

    /**
     * Switch a signature off
     *
     * Forms opened by its account get an empty box again, to be signed on paper. Switching it back on is done on the page.
     * A signature that is off already stays so.
     */
    public function deactivate(Request $request, int $signature): JsonResponse
    {
        $one = $this->find($signature);
        $changed = $this->signatures->deactivate($one, $request->user());

        return response()->json([
            'message' => __($changed ? 'RadioManagement::esign.messages.deactivated' : 'RadioManagement::esign.messages.inactive_already', ['name' => $one->display_name]),
            'data' => $this->present($one->fresh('user'), $this->signatures->useCounts([$one->id])[$one->id] ?? null),
        ]);
    }

    private function find(int $id): ItSignature
    {
        abort_unless(ItSignature::ready(), 503, ESignPage::notReady());
        $one = ItSignature::query()->with('user:id,name,email,deleted_at')->find($id);
        abort_if($one === null, 404);

        return $one;
    }

    /** @return array<string, mixed> */
    private function present(ItSignature $one, ?object $uses): array
    {
        return [
            'id' => $one->id,
            'account' => [
                'id' => $one->user_id,
                'name' => $one->user?->name,
                'email' => $one->user?->email,
                /** The account was removed: the signature stays off for good. */
                'gone' => $one->user === null || $one->user->trashed(),
            ],
            /** The name printed under the signature. */
            'display_name' => $one->display_name,
            'active' => (bool) $one->active,
            /** How many forms it was put on. */
            'uses' => (int) ($uses->total ?? 0),
            'last_used_at' => isset($uses->last_at) ? Carbon::parse($uses->last_at)->toIso8601String() : null,
            'updated_at' => $one->updated_at?->toIso8601String(),
        ];
    }
}
