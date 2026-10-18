<?php

namespace Modules\RadioManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\RadioManagement\Services\RadioBulk;
use Modules\RadioManagement\Services\RadioRules;

/**
 * The bulk actions of the register list (the radios ticked, or every radio of the filter for verifying). The permission
 * of each is the one of its method on "Register Radio": verifying and archiving are an Update (PUT), deleting a Delete.
 * Exporting the radios ticked is the export of the list, with their ids (RadioFilter).
 */
class RadioBulkController extends Controller
{
    /** What a verification says when nothing was typed. */
    public const DEFAULT_NOTE = 'Diverifikasi (stock opname).';

    public function __construct(private RadioBulk $bulk)
    {
    }

    /** "Verifikasi hari ini": a "Verifikasi" line in the history of each radio, its date of verification moved forward. */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [], ['event_date' => 'Tanggal', 'description' => 'Catatan']);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }
        $note = trim((string) $request->input('description')) ?: self::DEFAULT_NOTE;

        return $this->run($request, true, RadioBulk::MAX_ALL, 'diverifikasi',
            fn (Collection $radios) => $this->bulk->verify($radios, (string) $request->input('event_date'), $note, $request->user()?->id));
    }

    /** One reason, date and note for every radio ticked. */
    public function archive(Request $request): JsonResponse
    {
        [$rules, $names] = RadioRules::archive();
        $validator = Validator::make($request->all(), $rules, [], $names);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }

        return $this->run($request, false, RadioBulk::MAX_TICKED, 'diarsipkan',
            fn (Collection $radios) => $this->bulk->archive($radios, (string) $request->input('archive_reason'),
                (string) $request->input('archived_at'), $request->input('note'), $request->user()?->id));
    }

    /** Radios entered by mistake (soft delete, their history kept). The person types how many, as a last look. */
    public function destroy(Request $request): JsonResponse
    {
        return $this->run($request, false, RadioBulk::MAX_TICKED, 'dihapus', function (Collection $radios) use ($request) {
            if ((string) $request->input('confirm') !== (string) $radios->count()) {
                throw ValidationException::withMessages(['confirm' => "Ketik jumlah radio yang akan dihapus ({$radios->count()}) untuk melanjutkan."]);
            }

            return $this->bulk->delete($radios, $request->user()?->id);
        });
    }

    /**
     * @param Closure(Collection): array{done: int, skipped: list<array{radio: string, reason: string}>} $action
     */
    private function run(Request $request, bool $everyFiltered, int $max, string $done, Closure $action): JsonResponse
    {
        try {
            $radios = $this->bulk->chosen($request, $everyFiltered, $max);
            // Each radio is written on its own (its history, the audit trail): a thousand take a while.
            if (function_exists('set_time_limit')) {
                @set_time_limit(max(60, $radios->count()));
            }
            $result = $action($radios);
        } catch (ValidationException $e) {
            return $this->invalid(collect($e->errors())->flatten()->values()->all());
        }

        $message = "{$result['done']} radio {$done}.";
        if ($result['skipped'] !== []) {
            $message .= ' ' . count($result['skipped']) . ' dilewati.';
        }

        return response()->json(['message' => $message] + $result);
    }

    private function invalid(array $errors): JsonResponse
    {
        return response()->json(['message' => 'Input tidak valid', 'errors' => $errors], 422);
    }
}
