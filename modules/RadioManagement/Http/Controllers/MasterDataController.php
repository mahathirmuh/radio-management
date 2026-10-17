<?php

namespace Modules\RadioManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use DateTimeInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\RadioManagement\Models\ChannelTemplate;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioCompany;
use Modules\RadioManagement\Models\RadioDepartment;
use Modules\RadioManagement\Models\RadioType;
use Modules\RadioManagement\Services\Like;
use Modules\RadioManagement\Services\RegisterLock;

/**
 * One controller for the four small master tables. Each route group passes its
 * `master` key as a route default (see Routes/web/radio-management.php).
 */
class MasterDataController extends Controller
{
    public const KEYS = ['radio-type', 'company', 'department', 'channel-template'];

    public function index(Request $request)
    {
        $master = $this->master($request);

        return view('RadioManagement::master.index', [
            'pageTitle' => $master['title'],
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => '#', 'label' => 'Master Data Radio', 'active' => false],
                ['url' => $request->url(), 'label' => $master['title'], 'active' => true],
            ],
            'masterKey' => $master['key'],
            'master' => $master,
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $master = $this->master($request);
        $limit = min(max((int) $request->input('paginated', 10), 1), 100);
        $orderBy = in_array($request->input('orderBy'), $master['sortable'], true) ? $request->input('orderBy') : $master['order'];
        $orderDir = $request->input('orderDir') === 'desc' ? 'desc' : 'asc';

        $data = $master['model']::query()
            ->when(trim((string) $request->input('search')), function ($query, $search) use ($master) {
                $query->where(fn ($q) => Like::any($q, $master['search'], $search));
            })
            ->orderBy($orderBy, $orderDir)
            ->paginate($limit);
        $usage = $this->usage($master, $data->getCollection()->modelKeys());

        return response()->json([
            'data' => $data->getCollection()->map(fn (Model $item) => $this->row($master, $item, $usage[$item->id] ?? 0))->values(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'from' => $data->firstItem(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'to' => $data->lastItem(),
                'total' => $data->total(),
            ],
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $master = $this->master($request);
        $item = $master['model']::findOrFail($id);

        return response()->json(['data' => $this->row($master, $item, $this->usage($master, [$item->id])[$item->id])]);
    }

    public function store(Request $request): JsonResponse
    {
        $master = $this->master($request);
        $validator = $this->validator($master, $request);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }
        $item = $master['model']::create($this->payload($master, $validator->validated()));
        app(AuditLogger::class)->log(AuditLogger::MASTER_CREATED, $item, new: $this->facts($master, $item), label: $this->label($master, $item));

        return response()->json(['message' => "{$master['title']} berhasil ditambahkan."]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $master = $this->master($request);
        $item = $master['model']::findOrFail($id);
        $validator = $this->validator($master, $request, $item->id);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }
        $before = $this->facts($master, $item);
        $item->update($this->payload($master, $validator->validated()));
        app(AuditLogger::class)->logChanges(AuditLogger::MASTER_UPDATED, $item, $before, $this->facts($master, $item), $this->label($master, $item));

        return response()->json(['message' => "{$master['title']} berhasil disimpan."]);
    }

    public function destroy(Request $request, RegisterLock $lock, $id): JsonResponse
    {
        $master = $this->master($request);
        $item = $master['model']::findOrFail($id);

        try {
            // Under the register lock, so no radio can pick this row between the count and the delete.
            $inUse = $lock->run(function () use ($master, $item) {
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
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => 'Register sedang sibuk',
                'errors' => ['Data radio sedang disimpan, atau impor Excel sedang berjalan. Coba lagi beberapa saat lagi.'],
            ], 409);
        }

        if ($inUse > 0) {
            return $this->invalid([
                "Masih dipakai {$inUse} radio. Nonaktifkan saja (tidak bisa dipilih lagi untuk radio lain), "
                . 'atau pindahkan radio ke data lain dulu.',
            ]);
        }
        app(AuditLogger::class)->log(AuditLogger::MASTER_DELETED, $item, old: $this->facts($master, $item), label: $this->label($master, $item));

        return response()->json(['message' => "{$master['title']} dihapus."]);
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

    private function master(Request $request): array
    {
        $key = $request->route('master');
        abort_unless(in_array($key, self::KEYS, true), 404);

        return ['key' => $key] + $this->definitions()[$key];
    }

    /**
     * Field definitions drive the generic list table, the modal form and validation.
     * `usage` lists the columns of `radios` that point at the master table.
     */
    private function definitions(): array
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

    private function validator(array $master, Request $request, ?int $ignoreId = null)
    {
        $input = $request->all();
        foreach ($master['fields'] as $name => $field) {
            if (($field['upper'] ?? false) && isset($input[$name])) {
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
     * How many radios in the register (active or archived, not deleted ones) use each row.
     * A radio that names the same company as user and as owner counts once.
     *
     * @param int[] $ids
     * @return array<int, int> master id => number of radios
     */
    private function usage(array $master, array $ids): array
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

    private function row(array $master, Model $item, int $usage): array
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

    private function invalid(array $errors): JsonResponse
    {
        return response()->json(['message' => 'Input tidak valid', 'errors' => $errors], 422);
    }
}
