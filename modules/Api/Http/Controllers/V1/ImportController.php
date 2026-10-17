<?php

namespace Modules\Api\Http\Controllers\V1;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Services\ImportProblem;
use Modules\RadioManagement\Services\RadioImports;

/**
 * The page "Import Excel": the old register workbook (sheet "Form", and "to Konawe") into the register.
 *
 * @tags Import
 */
class ImportController extends ApiController
{
    public function __construct(private RadioImports $imports)
    {
    }

    /**
     * The last import
     *
     * Its report (rows read, radios imported, rows skipped and why, data flagged "Perlu dicek"), and how many radios
     * the register holds now (deleted ones included). `last_import` is null when there has been none.
     */
    public function show(): JsonResponse
    {
        return response()->json(['data' => [
            'radios_in_register' => Radio::withTrashed()->count(),
            'last_import' => $this->imports->lastReport(),
        ]]);
    }

    /**
     * Import an Excel register
     *
     * Send the .xlsx file as multipart: `file` (up to 20 MB). An import fills an EMPTY register: while the register
     * holds radios it is refused (422) unless `fresh=1`, which deletes the register and its history first and also
     * needs Delete on "Register Radio". `konawe=0` leaves out the sheet "to Konawe" (radios handed over to Konawe,
     * imported as archived). The answer is the report of the import. 10 a minute per token; an import runs one at a
     * time (409 while another runs or a radio is being saved).
     */
    public function store(Request $request): JsonResponse
    {
        // The rules of the page's form.
        $data = $request->validate(RadioImports::rules(), RadioImports::messages());

        if ($request->boolean('fresh') && ! $this->imports->mayReplace($request->user())) {
            return response()->json(['message' => RadioImports::REPLACE_REFUSED], 403);
        }

        try {
            $report = $this->imports->run($data['file'], $request->boolean('fresh'), $request->boolean('konawe', true), $request->user());
        } catch (LockTimeoutException) {
            return response()->json(['message' => self::BUSY], 409);
        } catch (ImportProblem $e) {
            return response()->json(['message' => $e->title . ': ' . $e->getMessage()] + ($e->status === 422 ? ['errors' => ['file' => [$e->getMessage()]]] : []), $e->status);
        }

        return response()->json(['message' => 'Impor selesai.', 'data' => $report]);
    }
}
