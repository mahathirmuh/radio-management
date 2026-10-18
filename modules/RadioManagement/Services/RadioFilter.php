<?php

namespace Modules\RadioManagement\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioCompany;

/**
 * Builds the register query from the list/export request parameters.
 *
 * Sorting runs on the narrow id column first and the full rows are fetched
 * afterwards: SQL Server is very slow at sorting rows that carry nvarchar(max)
 * columns (notes, source_data), even for a thousand rows.
 */
class RadioFilter
{
    public const SORTABLE = ['dmr_id', 'serial_number', 'user_name', 'last_programmed_at', 'last_verified_at', 'updated_at'];

    /** At most this many radios are asked for by their ids at once (the radios ticked in the list). */
    public const MAX_IDS = 1000;

    private const RELATIONS = ['radioType', 'ownerCompany', 'company', 'department', 'channelTemplate'];

    public function __construct(private RadioCheckService $checks)
    {
    }

    /** Filtered + ordered query over radio ids only. */
    public function query(Request $request): Builder
    {
        $query = Radio::query()->select('radios.id');

        // Radios ticked one by one ("Export terpilih"): those, whatever the other filters say.
        $ids = self::ids($request);
        if ($ids !== []) {
            $query->whereIntegerInRaw('radios.id', array_slice($ids, 0, self::MAX_IDS));

            return $this->ordered($query, $request);
        }

        match ($request->input('scope', 'active')) {
            'archived' => $query->archived(),
            'all' => $query,
            default => $query->active(),
        };

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function (Builder $q) use ($search) {
                Like::any($q, ['radios.serial_number', 'radios.user_name', 'radios.document_no', 'radios.notes'], $search);
                if (ctype_digit($search) && strlen($search) <= 9) {
                    $q->orWhere('dmr_id', (int) $search)->orWhere('previous_dmr_id', (int) $search);
                }
                // Searching "3E9" finds the radio whose MDC code is 3E9; "100" finds Radio ID 100
                // as well as the radio whose MDC code is 100 (Radio ID 256).
                if (preg_match('/^[0-9A-Fa-f]{1,6}$/', $search)) {
                    $q->orWhere('dmr_id', hexdec($search));
                }
            });
        }

        foreach ([
            'company' => 'company_id',
            'owner' => 'owner_company_id',
            'department' => 'department_id',
            'type' => 'radio_type_id',
            'template' => 'channel_template_id',
        ] as $param => $column) {
            $ids = array_filter((array) $request->input($param, []), 'is_numeric');
            if ($ids) {
                $query->whereIntegerInRaw($column, array_map('intval', $ids));
            }
        }

        // "Owner": MTI, or anybody else. A radio whose owner is not filled in is neither.
        $group = $request->input('owner_group');
        if (in_array($group, ['mti', 'non_mti'], true)) {
            $mti = RadioCompany::query()->whereRaw('upper(ltrim(rtrim(code))) = ?', [mb_strtoupper(trim((string) config('radio.owner_mti_code', 'MTI')))])->pluck('id')->all();
            $group === 'mti'
                ? $query->whereIntegerInRaw('owner_company_id', $mti)
                : $query->whereNotNull('owner_company_id')->whereIntegerNotInRaw('owner_company_id', $mti);
        }

        foreach (['placement', 'condition'] as $column) {
            $values = array_filter((array) $request->input($column, []));
            if ($values) {
                $query->whereIn($column, $values);
            }
        }

        if ($issue = $request->input('issue')) {
            $this->checks->applyIssueFilter($query, $issue);
        }

        return $this->ordered($query, $request);
    }

    /** @return int[] the radios asked for by their ids ("ids[]"): each once, numbers only. */
    public static function ids(Request $request): array
    {
        $ids = array_filter((array) $request->input('ids', []), fn ($id) => is_numeric($id) && (int) $id > 0);

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function ordered(Builder $query, Request $request): Builder
    {
        $orderBy = in_array($request->input('orderBy'), self::SORTABLE, true) ? $request->input('orderBy') : 'dmr_id';
        $orderDir = $request->input('orderDir') === 'desc' ? 'desc' : 'asc';

        // Empty values last in both directions (SQL Server has no NULLS LAST).
        return $query->orderByRaw("CASE WHEN {$orderBy} IS NULL THEN 1 ELSE 0 END")
            ->orderBy($orderBy, $orderDir)
            ->orderBy('id');
    }

    public function paginate(Request $request, int $perPage): LengthAwarePaginator
    {
        $page = $this->query($request)->paginate($perPage);
        $page->setCollection($this->load($page->getCollection()->pluck('id')->all()));

        return $page;
    }

    /** Every matching radio, in list order (used by the Excel export). */
    public function all(Request $request): Collection
    {
        return $this->load($this->query($request)->pluck('id')->all());
    }

    /** Full models with relations for the given ids, kept in the given order. */
    private function load(array $ids): Collection
    {
        $position = array_flip($ids);
        $radios = new Collection();
        // Chunked to stay below SQL Server's 2100-parameter limit (ids are inlined, but keep queries small).
        foreach (array_chunk($ids, 500) as $chunk) {
            $radios = $radios->merge(Radio::query()->with(self::RELATIONS)->whereIntegerInRaw('id', $chunk)->get());
        }

        return $radios->sortBy(fn (Radio $radio) => $position[$radio->id])->values();
    }
}
