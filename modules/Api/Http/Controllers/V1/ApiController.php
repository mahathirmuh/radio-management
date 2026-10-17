<?php

namespace Modules\Api\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\RadioManagement\Services\StaleRadio;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the controllers of the API share: how a change of the register is answered when it cannot be made now, and
 * how many rows a page has.
 */
abstract class ApiController extends Controller
{
    public const BUSY = 'Register sedang sibuk: data radio lain sedang disimpan, atau impor Excel sedang berjalan. Coba lagi beberapa saat lagi.';

    /**
     * Runs a change. A change based on an older state of the radio (somebody saved it in between), and a register that
     * is busy, are answered with 409: read it again, or try again in a moment. Every other refusal stays 422.
     */
    protected function write(Closure $change): Response|JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        try {
            return $change();
        } catch (StaleRadio $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 409);
        } catch (LockTimeoutException) {
            return response()->json(['message' => self::BUSY], 409);
        }
    }

    /** Rows per page: ?per_page=, 1 to 100, 25 when not given. */
    protected function perPage(Request $request): int
    {
        return min(max((int) $request->input('per_page', 25), 1), 100);
    }

    /**
     * A filter that takes several values may come as a list (?type[]=1&type[]=2) or comma separated (?type=1,2).
     *
     * @param string[] $keys
     */
    protected function lists(Request $request, array $keys): void
    {
        foreach ($keys as $key) {
            $value = $request->query($key);
            if (is_string($value)) {
                $request->query->set($key, array_values(array_filter(array_map('trim', explode(',', $value)), fn ($part) => $part !== '')));
            }
        }
    }
}
