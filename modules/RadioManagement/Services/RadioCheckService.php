<?php

namespace Modules\RadioManagement\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\RadioManagement\Enums\Placement;
use Modules\RadioManagement\Models\Radio;

/**
 * The register's "Cek" column: per-radio warnings plus the matching SQL filters,
 * so the list can be narrowed to one kind of problem and the dashboard can count them.
 */
class RadioCheckService
{
    public const ISSUES = [
        'needs_review' => ['label' => 'Perlu dicek', 'color' => 'red'],
        'sn_duplicate' => ['label' => 'SN dobel', 'color' => 'red'],
        'id_duplicate' => ['label' => 'Radio ID dobel', 'color' => 'red'],
        'id_hex_f' => ['label' => 'Radio ID mengandung F', 'color' => 'red'],
        'sn_empty' => ['label' => 'SN kosong', 'color' => 'orange'],
        'sn_format' => ['label' => 'Format SN tidak standar', 'color' => 'orange'],
        'id_empty' => ['label' => 'Radio ID kosong', 'color' => 'orange'],
        'type_empty' => ['label' => 'Tipe radio kosong', 'color' => 'orange'],
        'lost_no_ba' => ['label' => 'Hilang tanpa No. BA', 'color' => 'orange'],
        'reprogram' => ['label' => 'Perlu program ulang', 'color' => 'yellow'],
        'verify_empty' => ['label' => 'Tgl verifikasi kosong', 'color' => 'secondary'],
        'verify_stale' => ['label' => 'Verifikasi lewat 12 bulan', 'color' => 'secondary'],
    ];

    private ?array $duplicateSerials = null;

    private ?array $duplicateIds = null;

    /** @return array<string, true> upper-cased SN => true, across all non-deleted radios */
    public function duplicateSerials(): array
    {
        return $this->duplicateSerials ??= Radio::query()
            ->whereNotNull('serial_number')
            ->where('serial_number', '!=', '')
            ->groupBy('serial_number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('serial_number')
            ->mapWithKeys(fn ($sn) => [mb_strtoupper(trim($sn)) => true])
            ->all();
    }

    /** @return array<int, true> Radio ID => true, across active radios */
    public function duplicateIds(): array
    {
        return $this->duplicateIds ??= Radio::query()
            ->active()
            ->whereNotNull('dmr_id')
            ->groupBy('dmr_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('dmr_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * @return array<int, array{code: string, label: string, color: string, hint: ?string}>
     */
    public function issuesFor(Radio $radio): array
    {
        $codes = [];
        $sn = $radio->serial_number ? mb_strtoupper(trim($radio->serial_number)) : null;

        if ($radio->needs_review) {
            $codes['needs_review'] = $radio->review_notes;
        }
        if (! $sn) {
            $codes['sn_empty'] = null;
        } else {
            if (isset($this->duplicateSerials()[$sn])) {
                $codes['sn_duplicate'] = null;
            }
            if (! preg_match(config('radio.sn_pattern'), $sn)) {
                $codes['sn_format'] = 'Format umum: 3 angka + 3 huruf + 4 karakter, mis. 867TZB5309';
            }
        }

        // Archived radios are out of service: only identity problems still matter.
        if (! $radio->isArchived()) {
            if (! $radio->dmr_id) {
                $codes['id_empty'] = $radio->previous_dmr_id ? 'ID terakhir diketahui: ' . $radio->previous_dmr_id : null;
            } else {
                if (isset($this->duplicateIds()[$radio->dmr_id])) {
                    $codes['id_duplicate'] = null;
                }
                if (RadioIdService::hasHexF($radio->dmr_id)) {
                    $codes['id_hex_f'] = 'MDC ' . $radio->mdc_code . ' mengandung huruf F (wildcard MDC). Program ulang radio ke ID lain';
                }
            }
            if (! $radio->radio_type_id) {
                $codes['type_empty'] = null;
            }
            if ($radio->placement === Placement::Hilang && blank($radio->document_no)) {
                $codes['lost_no_ba'] = null;
            }
            $template = $radio->channelTemplate;
            if ($template?->effective_date
                && (! $radio->last_programmed_at || $radio->last_programmed_at->lt($template->effective_date))) {
                $codes['reprogram'] = 'Template berlaku sejak ' . $template->effective_date->format('d-m-Y');
            }
            if ($radio->placement !== Placement::Hilang) {
                if (! $radio->last_verified_at) {
                    $codes['verify_empty'] = null;
                } elseif ($radio->last_verified_at->lt($this->staleBefore())) {
                    $codes['verify_stale'] = 'Terakhir ' . $radio->last_verified_at->format('d-m-Y');
                }
            }
        }

        $issues = [];
        foreach ($codes as $code => $hint) {
            $issues[] = self::ISSUES[$code] + ['code' => $code, 'hint' => $hint];
        }

        return $issues;
    }

    /** Narrow a radio query to the rows that have one specific issue. */
    public function applyIssueFilter(Builder $query, string $code): Builder
    {
        return match ($code) {
            'needs_review' => $query->where('needs_review', true),
            // strval: PHP turns an all-digit SN used as array key into an int, and SQL Server
            // then tries to convert every serial number to a number.
            'sn_duplicate' => $query->whereIn('serial_number', array_map('strval', array_keys($this->duplicateSerials())) ?: ['']),
            'sn_empty' => $query->where(fn ($q) => $q->whereNull('serial_number')->orWhere('serial_number', '')),
            'sn_format' => $query->whereIntegerInRaw('id', $this->badSerialRadioIds() ?: [0]),
            'id_duplicate' => $query->active()->whereIntegerInRaw('dmr_id', array_keys($this->duplicateIds()) ?: [0]),
            'id_hex_f' => $query->active()->whereIntegerInRaw('dmr_id', $this->hexFIdsInUse() ?: [0]),
            'id_empty' => $query->active()->whereNull('dmr_id'),
            'type_empty' => $query->active()->whereNull('radio_type_id'),
            'lost_no_ba' => $query->active()
                ->where('placement', Placement::Hilang->value)
                ->where(fn ($q) => $q->whereNull('document_no')->orWhere('document_no', '')),
            'reprogram' => $query->active()->whereHas('channelTemplate', function ($q) {
                $q->whereNotNull('effective_date')->where(function ($q) {
                    $q->whereNull('radios.last_programmed_at')
                        ->orWhereColumn('radios.last_programmed_at', '<', 'radio_channel_templates.effective_date');
                });
            }),
            'verify_empty' => $query->active()
                ->where('placement', '!=', Placement::Hilang->value)
                ->whereNull('last_verified_at'),
            'verify_stale' => $query->active()
                ->where('placement', '!=', Placement::Hilang->value)
                ->where('last_verified_at', '<', $this->staleBefore()->toDateString()),
            default => $query,
        };
    }

    /** @return array<string, int> issue code => number of radios */
    public function summary(): array
    {
        $counts = [];
        foreach (array_keys(self::ISSUES) as $code) {
            $counts[$code] = $this->applyIssueFilter(Radio::query(), $code)->count();
        }

        return $counts;
    }

    private function staleBefore()
    {
        return now()->startOfDay()->subMonths((int) config('radio.verify_max_months'));
    }

    private function badSerialRadioIds(): array
    {
        $pattern = config('radio.sn_pattern');

        return Radio::query()
            ->whereNotNull('serial_number')
            ->where('serial_number', '!=', '')
            ->get(['id', 'serial_number'])
            ->reject(fn (Radio $radio) => preg_match($pattern, mb_strtoupper(trim($radio->serial_number))))
            ->pluck('id')
            ->all();
    }

    private function hexFIdsInUse(): array
    {
        return Radio::query()
            ->active()
            ->whereNotNull('dmr_id')
            ->distinct()
            ->pluck('dmr_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => RadioIdService::hasHexF($id))
            ->values()
            ->all();
    }
}
