<?php

namespace Modules\RadioManagement\Services;

use App\Services\Audit\AuditLogger;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as RowValidator;
use Modules\RadioManagement\Models\ChannelTemplate;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioCompany;
use Modules\RadioManagement\Models\RadioDepartment;
use Modules\RadioManagement\Models\RadioType;

/**
 * The four small master tables (Tipe Radio, Perusahaan, Department, Template Channel): what they hold, how a row is
 * checked, listed, saved and deleted, and what the audit trail says about it. The pages of "Master Data Radio" and the
 * API both go through here, so they keep one set of rules.
 */
class MasterData
{
    public const KEYS = ['radio-type', 'company', 'department', 'channel-template'];

    public function __construct(private RegisterLock $lock)
    {
    }

    /** One table, by its key, with the key in it; null for a key that is none of them. */
    public function find(?string $key): ?array
    {
        return in_array($key, self::KEYS, true) ? ['key' => $key] + $this->definitions()[$key] : null;
    }

    /**
     * Field definitions drive the generic list table, the modal form and validation.
     * `usage` lists the columns of `radios` that point at the master table.
     */
    public function definitions(): array
    {
        $categories = config('radio.categories');

        return [
            'radio-type' => [
                'title' => 'Tipe Radio',
                'model' => RadioType::class,
                'unique' => 'name',
                'search' => ['name', 'power'],
                'sortable' => ['name', 'category', 'power'],
                'order' => 'name',
                'usage' => ['radio_type_id'],
                'fields' => [
                    'name' => ['label' => 'Nama Tipe', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'placeholder' => 'MotoTrbo XiR P6620i'],
                    'category' => ['label' => 'Kategori', 'type' => 'select', 'options' => $categories, 'rules' => ['required', Rule::in(array_keys($categories))]],
                    'power' => ['label' => 'Daya', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:30'], 'placeholder' => '5 W'],
                    'is_active' => ['label' => 'Aktif', 'type' => 'boolean', 'rules' => ['boolean']],
                ],
            ],
            'company' => [
                'title' => 'Perusahaan',
                'model' => RadioCompany::class,
                'unique' => 'code',
                'search' => ['code', 'name'],
                'sortable' => ['code', 'name'],
                'order' => 'code',
                'usage' => ['company_id', 'owner_company_id'],
                'fields' => [
                    'code' => ['label' => 'Kode', 'type' => 'text', 'rules' => ['required', 'string', 'max:50'], 'placeholder' => 'MTI', 'upper' => true],
                    'name' => ['label' => 'Nama Perusahaan', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:150'], 'placeholder' => 'PT Merdeka Tsingshan Indonesia'],
                    'is_active' => ['label' => 'Aktif', 'type' => 'boolean', 'rules' => ['boolean']],
                ],
            ],
            'department' => [
                'title' => 'Department',
                'model' => RadioDepartment::class,
                'unique' => 'name',
                'search' => ['name'],
                'sortable' => ['name'],
                'order' => 'name',
                'usage' => ['department_id'],
                'fields' => [
                    'name' => ['label' => 'Nama Department', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'placeholder' => 'CHLORIDE PLANT', 'upper' => true],
                    'is_active' => ['label' => 'Aktif', 'type' => 'boolean', 'rules' => ['boolean']],
                ],
            ],
            'channel-template' => [
                'title' => 'Template Channel',
                'model' => ChannelTemplate::class,
                'unique' => 'name',
                'search' => ['name', 'channels'],
                'sortable' => ['name', 'effective_date'],
                'order' => 'name',
                'usage' => ['channel_template_id'],
                'fields' => [
                    'name' => ['label' => 'Nama Template', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'placeholder' => 'General'],
                    'channels' => ['label' => 'Isi Channel', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000'], 'placeholder' => 'Emergency, Security, Safety, ...'],
                    'effective_date' => [
                        'label' => 'Berlaku Sejak',
                        'type' => 'date',
                        'rules' => ['nullable', 'date'],
                        'help' => 'Radio dengan template ini yang diprogram sebelum tanggal ini ditandai "Perlu program ulang".',
                    ],
                    'is_active' => ['label' => 'Aktif', 'type' => 'boolean', 'rules' => ['boolean']],
                ],
            ],
        ];
    }

    /** A page of the rows: $search in the table's text columns, sorted by one of its sortable columns (else its own order). */
    public function paginate(array $master, string $search, ?string $orderBy, ?string $orderDir, int $perPage): LengthAwarePaginator
    {
        $orderBy = in_array($orderBy, $master['sortable'], true) ? $orderBy : $master['order'];

        return $master['model']::query()
            ->when($search !== '', function ($query) use ($master, $search) {
                $query->where(fn ($q) => Like::any($q, $master['search'], $search));
            })
            ->orderBy($orderBy, $orderDir === 'desc' ? 'desc' : 'asc')
            ->paginate($perPage);
    }

    /** The check of what is sent for a row ($ignoreId: the row being changed). Codes and names in capitals are made so first. */
    public function validator(array $master, array $input, ?int $ignoreId = null): RowValidator
    {
        foreach ($master['fields'] as $name => $field) {
            if (($field['upper'] ?? false) && isset($input[$name]) && is_string($input[$name])) {
                $input[$name] = mb_strtoupper(trim($input[$name]));
            }
        }

        $rules = [];
        $labels = [];
        foreach ($master['fields'] as $name => $field) {
            $rules[$name] = $field['rules'];
            $labels[$name] = $field['label'];
        }
        $rules[$master['unique']][] = Rule::unique((new $master['model'])->getTable(), $master['unique'])->ignore($ignoreId);

        return Validator::make($input, $rules, [
            'required' => ':attribute wajib diisi.',
            'unique' => ':attribute sudah ada.',
        ], $labels);
    }

    /** @param array<string, mixed> $validated what validator() let through */
    public function create(array $master, array $validated): Model
    {
        $item = $master['model']::create($this->payload($master, $validated));
        app(AuditLogger::class)->log(AuditLogger::MASTER_CREATED, $item, new: $this->facts($master, $item), label: $this->label($master, $item));

        return $item;
    }

    /** @param array<string, mixed> $validated what validator() let through */
    public function update(array $master, Model $item, array $validated): Model
    {
        $before = $this->facts($master, $item);
        $item->update($this->payload($master, $validated));
        app(AuditLogger::class)->logChanges(AuditLogger::MASTER_UPDATED, $item, $before, $this->facts($master, $item), $this->label($master, $item));

        return $item;
    }

    /**
     * Deletes a row that no radio of the register uses. Returns how many radios use it: 0 when it was deleted.
     *
     * @throws \Illuminate\Contracts\Cache\LockTimeoutException while another save or an import holds the register
     */
    public function delete(array $master, Model $item): int
    {
        // Under the register lock, so no radio can pick this row between the count and the delete.
        $inUse = $this->lock->run(function () use ($master, $item) {
            $inUse = $this->usage($master, [$item->id])[$item->id];
            if ($inUse > 0) {
                return $inUse;
            }
            // Radios deleted from the register still point at the row (the foreign keys are
            // NO ACTION) and would block the delete for ever: detach them first.
            foreach ($master['usage'] as $column) {
                Radio::onlyTrashed()->where($column, $item->id)->update([$column => null]);
            }
            $item->delete();

            return 0;
        });

        if ($inUse === 0) {
            app(AuditLogger::class)->log(AuditLogger::MASTER_DELETED, $item, old: $this->facts($master, $item), label: $this->label($master, $item));
        }

        return $inUse;
    }

    /** What is said when a row cannot go because radios use it. */
    public static function inUseMessage(int $inUse): string
    {
        return "Masih dipakai {$inUse} radio. Nonaktifkan saja (tidak bisa dipilih lagi untuk radio lain), "
            . 'atau pindahkan radio ke data lain dulu.';
    }

    /**
     * How many radios in the register (active or archived, not deleted ones) use each row.
     * A radio that names the same company as user and as owner counts once.
     *
     * @param int[] $ids
     * @return array<int, int> master id => number of radios
     */
    public function usage(array $master, array $ids): array
    {
        $counts = array_fill_keys($ids, 0);
        if (! $ids) {
            return $counts;
        }
        $columns = $master['usage'];

        Radio::query()
            ->where(function ($query) use ($columns, $ids) {
                foreach ($columns as $column) {
                    $query->orWhere(fn ($q) => $q->whereIntegerInRaw($column, $ids));
                }
            })
            ->toBase()
            ->get($columns)
            ->each(function ($radio) use (&$counts, $columns) {
                foreach (array_unique(array_filter(array_map(fn ($column) => $radio->{$column}, $columns))) as $id) {
                    if (isset($counts[$id])) {
                        $counts[$id]++;
                    }
                }
            });

        return $counts;
    }

    /** A row as the list and the API show it: its id, its fields, and how many radios use it. */
    public function row(array $master, Model $item, int $usage): array
    {
        $row = ['id' => $item->id];
        foreach ($master['fields'] as $name => $field) {
            $value = $item->{$name};
            $row[$name] = match ($field['type']) {
                'date' => $value?->toDateString(),
                'boolean' => (bool) $value,
                default => $value,
            };
        }
        $row['usage'] = $usage;

        return $row;
    }

    private function payload(array $master, array $validated): array
    {
        foreach ($master['fields'] as $name => $field) {
            if ($field['type'] === 'boolean') {
                $validated[$name] = (bool) ($validated[$name] ?? false);
            }
        }

        return $validated;
    }

    /**
     * A row of a master table as the audit trail shows it: the fields of its form, by their labels.
     *
     * @return array<string, mixed>
     */
    private function facts(array $master, Model $item): array
    {
        $facts = [];
        foreach ($master['fields'] as $name => $field) {
            $value = $item->getAttribute($name);
            $facts[$field['label']] = match (true) {
                $field['type'] === 'boolean' => $value ? 'Ya' : 'Tidak',
                $value instanceof DateTimeInterface => $value->format('d-m-Y'),
                $field['type'] === 'select' && is_scalar($value) => $field['options'][$value] ?? $value,
                default => $value,
            };
        }

        return $facts;
    }

    /** "Tipe Radio: MotoTrbo XiR P6620i": which table, and which row of it. */
    private function label(array $master, Model $item): string
    {
        return $master['title'] . ': ' . ($item->getAttribute($master['unique']) ?? '#' . $item->getKey());
    }
}
