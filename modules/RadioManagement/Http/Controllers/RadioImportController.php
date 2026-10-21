<?php

namespace Modules\RadioManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Services\ImportProblem;
use Modules\RadioManagement\Services\RadioImports;

class RadioImportController extends Controller
{
    /** @deprecated kept for callers from before RadioImports: the same key. */
    public const REPORT_CACHE_KEY = RadioImports::REPORT_CACHE_KEY;

    public function __construct(private RadioImports $imports)
    {
    }

    public function index()
    {
        return view('RadioManagement::import.index', [
            'pageTitle' => 'Import Excel',
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => '/radio-management/import', 'label' => 'Import Excel', 'active' => true],
            ],
            'existing' => Radio::withTrashed()->count(),
            'lastReport' => $this->imports->lastReport(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->boolean('fresh') && ! $this->imports->mayReplace($request->user())) {
            return response()->json(['message' => __('RadioManagement::import.messages.not_allowed'), 'errors' => [RadioImports::replaceRefused()]], 403);
        }

        $validator = Validator::make($request->all(), RadioImports::rules(), RadioImports::messages());
        if ($validator->fails()) {
            return response()->json(['message' => __('RadioManagement::register.messages.invalid'), 'errors' => $validator->errors()->all()], 422);
        }

        try {
            $report = $this->imports->run($request->file('file'), $request->boolean('fresh'), $request->boolean('konawe', true), $request->user());
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => __('RadioManagement::register.messages.busy'),
                'errors' => [__('RadioManagement::import.messages.busy_text')],
            ], 409);
        } catch (ImportProblem $e) {
            return response()->json(['message' => $e->title, 'errors' => [$e->getMessage()]], $e->status);
        }

        return response()->json(['message' => __('RadioManagement::import.messages.done'), 'report' => $report]);
    }
}
