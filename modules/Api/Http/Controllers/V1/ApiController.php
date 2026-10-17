<?php

namespace Modules\Api\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Api\Models\ApiToken;
use Modules\RadioManagement\Services\StaleRadio;
use Modules\SharePointDocs\Services\GraphException;
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

    /**
     * What SharePoint said, as the pages say it (what Microsoft said goes to the log only): the words, the reason (a key such as
     * "unavailable", "forbidden", "not_found") and its status: 404, 413, 415, 502, 503 or 504.
     */
    protected function sharePointError(GraphException $e, bool $workbook = false): JsonResponse
    {
        return response()->json(['message' => $workbook ? $e->workbookMessage() : $e->userMessage(), 'reason' => $e->reason], $e->httpStatus());
    }

    /** Whether the token of the request may write (a read-only token is refused at a change anyway, by ApiAccess). */
    protected function tokenWrites(Request $request): bool
    {
        $token = $request->user()?->currentAccessToken();

        return $token instanceof ApiToken && $token->writes();
    }

    /** A moment kept as a Unix time, in ISO 8601 in the time zone of the application. */
    protected static function iso(int|float|null $timestamp): ?string
    {
        return $timestamp === null ? null : Carbon::createFromTimestamp($timestamp, config('app.timezone'))->toIso8601String();
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
