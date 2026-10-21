<?php

namespace Modules\RadioManagement\Services;

use Illuminate\Support\Facades\DB;
use Modules\RadioManagement\Enums\Condition;
use Modules\RadioManagement\Enums\Placement;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioCompany;
use Modules\RadioManagement\Models\RadioType;

/**
 * The figures of the radio dashboard (active radios unless stated otherwise): the Dashboard page and the API.
 */
class RadioSummary
{
    /** The group of the radios without a company or a type, as the API names it (the page names it in its language). */
    public const NO_NAME = '(kosong)';

    public function __construct(private RadioCheckService $checks, private RadioIdService $ids)
    {
    }

    public function figures(): array
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
            ->mapWithKeys(fn ($count, $id) => [$companyNames[$id] ?? self::NO_NAME => $count])
            ->sortDesc();

        $typeNames = RadioType::pluck('name', 'id');
        $byType = $countBy('radio_type_id')
            ->mapWithKeys(fn ($count, $id) => [str_replace('MotoTrbo XiR ', '', $typeNames[$id] ?? self::NO_NAME) => $count])
            ->sortDesc();

        $issueCounts = $this->checks->summary();

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
            'nextIds' => $this->ids->nextFree(6),
        ];
    }
}
