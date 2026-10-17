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
use Modules\RadioManagement\Models\RadioHistory;
use Modules\RadioManagement\Services\RadioSummary;

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

    /** Figures for the radio dashboard (active radios unless stated otherwise; RadioSummary, which the API shows too). */
    private function radioSummary(): array
    {
        return app(RadioSummary::class)->figures() + [
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
