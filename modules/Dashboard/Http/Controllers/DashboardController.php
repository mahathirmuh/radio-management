<?php

namespace Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee\Employee;
use App\Models\Organization\Company;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\RadioManagement\Enums\Condition;
use Modules\RadioManagement\Enums\Placement;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioCompany;
use Modules\RadioManagement\Models\RadioHistory;
use Modules\RadioManagement\Models\RadioType;
use Modules\RadioManagement\Services\RadioCheckService;
use Modules\RadioManagement\Services\RadioIdService;

class DashboardController extends Controller
{
    protected $pageTitle;
    protected $menuItems;
    private $menuId;

    public function __construct()
    {
        $this->middleware('auth');
        $this->menuId = 1; //Dashboard Menu
        $this->pageTitle = "Dashboard";
        $this->menuItems = [
            ['url' => '/', 'label' => 'Home', 'active' => false],
            ['url' => '/dashboard', 'label' => 'Dashboard', 'active' => true],

        ];
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        // if (!request()->user()->guardRole($this->menuId)) {
        //     abort(403);
        // }

        try {
            return view('Dashboard::index', [
                'pageTitle' => $this->pageTitle,
                'menuItems' => $this->menuItems,
            ] + $this->radioSummary());
        } catch (Exception $e) {
            throw $e;
        }
    }

    /** Figures for the radio dashboard (active radios unless stated otherwise). */
    private function radioSummary(): array
    {
        $countBy = fn (string $column) => Radio::query()->active()
            ->selectRaw(DB::getQueryGrammar()->wrap($column) . ' as k, COUNT(*) as c')
            ->groupBy($column)
            ->pluck('c', 'k')
            ->map(fn ($count) => (int) $count);

        $placements = $countBy('placement');
        $conditions = $countBy('condition');

        $companyNames = RadioCompany::pluck('code', 'id');
        $byCompany = $countBy('company_id')
            ->mapWithKeys(fn ($count, $id) => [$companyNames[$id] ?? '(kosong)' => $count])
            ->sortDesc();

        $typeNames = RadioType::pluck('name', 'id');
        $byType = $countBy('radio_type_id')
            ->mapWithKeys(fn ($count, $id) => [str_replace('MotoTrbo XiR ', '', $typeNames[$id] ?? '(kosong)') => $count])
            ->sortDesc();

        $issueCounts = app(RadioCheckService::class)->summary();

        return [
            'totalActive' => $placements->sum(),
            'totalArchived' => Radio::query()->archived()->count(),
            'placements' => collect(Placement::cases())->map(fn (Placement $p) => [
                'value' => $p->value,
                'label' => $p->label(),
                'count' => $placements[$p->value] ?? 0,
            ]),
            'conditions' => collect(Condition::cases())->map(fn (Condition $c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'count' => $conditions[$c->value] ?? 0,
            ]),
            'byCompany' => $byCompany,
            'byType' => $byType,
            'issues' => collect(RadioCheckService::ISSUES)
                ->map(fn (array $issue, string $code) => $issue + ['code' => $code, 'count' => $issueCounts[$code] ?? 0])
                ->filter(fn (array $issue) => $issue['count'] > 0)
                ->values(),
            'nextIds' => app(RadioIdService::class)->nextFree(6),
            'recentHistory' => RadioHistory::query()->with(['radio', 'user'])
                ->where('action', '!=', 'Impor')
                ->orderByDesc('event_date')->orderByDesc('id')
                ->limit(8)->get(),
        ];
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
