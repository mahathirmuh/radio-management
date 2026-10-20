@extends('main.index')

@include('RadioManagement::partials.scripts')

@section('page-title', $master['title'])

@section('content')
    @php
        $fields = collect($master['fields'])->map(fn ($field) => collect($field)->except('rules')->all());
        $menuKey = "radio-management.{$masterKey}";
        $may = [
            'create' => \Illuminate\Support\Facades\Blade::check('permission', $menuKey, 'Create'),
            'update' => \Illuminate\Support\Facades\Blade::check('permission', $menuKey, 'Update'),
            'delete' => \Illuminate\Support\Facades\Blade::check('permission', $menuKey, 'Delete'),
        ];
        // The four lists of master data are one page each (each its own menu and permissions): the tabs lead to them.
        $tabs = collect([
            'radio-type' => 'Tipe Radio',
            'company' => 'Perusahaan',
            'department' => 'Department',
            'channel-template' => 'Template Channel',
        ])->filter(fn ($title, $key) => \Illuminate\Support\Facades\Blade::check('permission', "radio-management.{$key}", 'Read'));
        $subtitles = [
            'radio-type' => 'Model radio yang bisa dipilih di register.',
            'company' => 'Company pengguna dan pemilik aset radio.',
            'department' => 'Department pengguna radio.',
            'channel-template' => 'Template channel yang diprogram ke radio; tanggal berlakunya menandai radio yang perlu program ulang.',
        ];
        $editable = $may['create'] || $may['update'];
    @endphp

    <div class="rm-head">
        <div>
            <h1 class="rm-title">{{ $master['title'] }}</h1>
            <p class="rm-sub">{{ $subtitles[$masterKey] ?? 'Master data radio.' }}</p>
        </div>
    </div>

    <div class="rm-card">
        <div class="rm-tabs-row">
            <nav class="rm-tabs" aria-label="Master data">
                @foreach ($tabs as $key => $title)
                    <a href="{{ route("radio-management.{$key}.index") }}" class="{{ $key === $masterKey ? 'is-on' : '' }}"
                        @if ($key === $masterKey) aria-current="page" @endif>{{ $title }}</a>
                @endforeach
            </nav>
            @if ($may['create'])
                <button type="button" class="btn-rm primary" id="masterAddButton" onclick="masterForm.open()">
                    <i class="ti ti-plus" aria-hidden="true"></i> Tambah {{ $master['title'] }}
                </button>
            @endif
        </div>

        <div class="rm-master-grid {{ $editable ? '' : 'is-readonly' }}">
            <div class="d-flex flex-column gap-3 min-w-0">
                <label class="rm-search" for="masterSearch">
                    <i class="ti ti-search" aria-hidden="true"></i>
                    <input type="search" id="masterSearch" autocomplete="off" placeholder="Cari {{ strtolower($master['title']) }}…"
                        aria-label="Cari" />
                </label>
                <div class="rm-box">
                    <div class="table-responsive">
                        <table class="rm-table" id="masterTable">
                            <thead>
                                <tr>
                                    <th class="text-center" width="30">No</th>
                                    @foreach ($fields as $name => $field)
                                        <th @if (in_array($name, $master['sortable'], true)) data-key="{{ $name }}" @endif>
                                            {{ $field['label'] }}</th>
                                    @endforeach
                                    <th class="text-end">Dipakai</th>
                                    <th class="text-end"><span class="visually-hidden">Aksi</span></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="pagination-container"></div>
                </div>
            </div>

            @if ($editable)
                <section class="rm-cardbox rm-master-form" aria-labelledby="masterFormTitle">
                    <header class="rm-cardbox-head">
                        <div>
                            <h2 class="rm-cardbox-title" id="masterFormTitle">Tambah {{ $master['title'] }}</h2>
                            <div class="rm-cardbox-sub" id="masterFormSub">Isi lalu simpan; data baru langsung bisa dipilih di
                                register.</div>
                        </div>
                    </header>
                    <form id="masterForm" autocomplete="off" class="rm-cardbox-body d-flex flex-column gap-3"
                        onsubmit="masterForm.submit(); return false;">
                        @foreach ($fields as $name => $field)
                            <div class="rm-field">
                                @if ($field['type'] === 'boolean')
                                    <label class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="field_{{ $name }}" checked>
                                        <span class="form-check-label">{{ $field['label'] }}</span>
                                    </label>
                                @else
                                    <label for="field_{{ $name }}" class="form-label">{{ $field['label'] }}</label>
                                    @if ($field['type'] === 'select')
                                        <select id="field_{{ $name }}" class="form-select">
                                            @foreach ($field['options'] as $value => $text)
                                                <option value="{{ $value }}">{{ $text }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($field['type'] === 'textarea')
                                        <textarea id="field_{{ $name }}" class="form-control" rows="3"
                                            placeholder="{{ $field['placeholder'] ?? '' }}"></textarea>
                                    @else
                                        <input type="{{ $field['type'] }}" id="field_{{ $name }}" class="form-control"
                                            placeholder="{{ $field['placeholder'] ?? '' }}">
                                    @endif
                                    @if (!empty($field['help']))
                                        <div class="rm-hint">{{ $field['help'] }}</div>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn-rm secondary d-none" id="masterCancelButton"
                                onclick="masterForm.open()">Batal</button>
                            <button type="submit" class="btn-rm primary" id="masterSaveButton">Simpan</button>
                        </div>
                    </form>
                </section>
            @endif
        </div>
    </div>
@endsection

@push('javascript')
    <script>
        const masterFields = @json($fields);
        const masterMay = @json($may);
        const masterTitle = @json($master['title']);
        const masterUrls = {
            list: "{{ route("radio-management.{$masterKey}.list") }}",
            show: "{{ route("radio-management.{$masterKey}.show", ':id') }}",
            store: "{{ route("radio-management.{$masterKey}.store") }}",
            update: "{{ route("radio-management.{$masterKey}.update", ':id') }}",
            destroy: "{{ route("radio-management.{$masterKey}.destroy", ':id') }}",
        };

        const masterTable = {
            page: 1,
            limit: 25,
            search: '',
            orderBy: @json($master['order']),
            orderDir: 'asc',
            columns: [
                'index',
                ...Object.entries(masterFields).map(([name, field], i) => ({
                    key: name,
                    sortable: true,
                    className: field.type === 'textarea' ? 'master-text' : '',
                    render: row => {
                        const value = row[name];
                        if (field.type === 'boolean') {
                            return value ? RadioUi.badge('Aktif', 'green') : RadioUi.badge('Nonaktif', 'secondary');
                        }
                        // A category: HT blue, Rig purple (the design's badges); another choice grey.
                        if (field.type === 'select') {
                            const tone = name === 'category' ? (String(value).toUpperCase() === 'RIG' ? 'purple' : 'blue') : 'secondary';
                            return RadioUi.badge(field.options[value] ?? value ?? '-', tone);
                        }
                        if (field.type === 'date' && value) return RadioUi.esc(value.split('-').reverse().join('-'));
                        if (field.type === 'textarea' && value) return `<span class="rm-line2">${RadioUi.esc(value)}</span>`;
                        // The first field is the name of the row: in bold.
                        return i === 0 ? `<span class="rm-strong">${RadioUi.dash(value)}</span>` : RadioUi.dash(value);
                    },
                })),
                {
                    key: 'usage',
                    className: 'text-end',
                    render: row => `<span class="rm-code">${Number(row.usage) || 0}</span>`
                },
                {
                    key: 'actions',
                    className: 'text-end',
                    render: row => `<div class="rm-acts">
                        ${masterMay.update ? `<button type="button" class="rm-act" data-action="edit" data-id="${row.id}" data-bs-toggle="tooltip" data-bs-title="Edit" aria-label="Edit"><i class="ti ti-edit"></i></button>` : ''}
                        ${masterMay.delete ? `<button type="button" class="rm-act is-danger" data-action="delete" data-id="${row.id}" data-bs-toggle="tooltip" data-bs-title="Hapus" aria-label="Hapus"><i class="ti ti-trash"></i></button>` : ''}
                    </div>`
                },
            ],

            render(extra = {}) {
                renderTableWithFeatures({
                    container: '#masterTable',
                    columns: masterTable.columns,
                    currentPage: masterTable.page,
                    limit: masterTable.limit,
                    emptyText: 'Belum ada data',
                    currentSortKey: masterTable.orderBy,
                    currentSortDir: masterTable.orderDir,
                    paginationLabels: { showing: 'Menampilkan', of: 'dari', rows: 'Baris per halaman', previous: 'Halaman sebelumnya', next: 'Halaman berikutnya' },
                    onPageChange: page => {
                        masterTable.page = page;
                        masterTable.load();
                    },
                    onLimitChange: limit => {
                        masterTable.limit = limit;
                        masterTable.page = 1;
                        masterTable.load();
                    },
                    onSortChange: (key, dir) => {
                        masterTable.orderBy = key;
                        masterTable.orderDir = dir;
                        masterTable.load();
                    },
                    ...extra,
                });
            },

            load() {
                $('[data-bs-toggle="tooltip"]').tooltip('hide');
                masterTable.render({ loading: true });
                $.getJSON(masterUrls.list, {
                    page: masterTable.page,
                    paginated: masterTable.limit,
                    search: masterTable.search,
                    orderBy: masterTable.orderBy,
                    orderDir: masterTable.orderDir,
                }).done(response => {
                    const page = RadioUi.pageAfter(response, masterTable.page);
                    if (page) {
                        masterTable.page = page;
                        return masterTable.load();
                    }
                    masterTable.render({ data: response.data, meta: response.meta });
                    RadioUi.tooltips('#masterTable');
                }).fail(() => masterTable.render({ emptyText: 'Gagal memuat data.' }));
            },
        };

        /** The card beside the table: a new row, or the row being edited (Batal goes back to a new one). */
        const masterForm = {
            id: null,
            open(row = null) {
                masterForm.id = row ? row.id : null;
                $('#masterFormTitle').text((row ? 'Edit ' : 'Tambah ') + masterTitle);
                $('#masterFormSub').text(row ? 'Ubah lalu simpan; radio yang memakainya ikut berubah.' : 'Isi lalu simpan; data baru langsung bisa dipilih di register.');
                $('#masterCancelButton').toggleClass('d-none', !row);
                Object.entries(masterFields).forEach(([name, field]) => {
                    const input = $('#field_' + name);
                    if (field.type === 'boolean') {
                        input.prop('checked', row ? !!row[name] : true);
                    } else if (field.type === 'select') {
                        input.val(row ? row[name] : input.find('option:first').val());
                    } else {
                        input.val(row ? (row[name] ?? '') : '');
                    }
                });
                if (row) $(`#field_${Object.keys(masterFields)[0]}`).trigger('focus');
            },
            submit() {
                if (!masterForm.id && !masterMay.create) return;
                const data = {};
                Object.entries(masterFields).forEach(([name, field]) => {
                    const input = $('#field_' + name);
                    data[name] = field.type === 'boolean' ? (input.is(':checked') ? 1 : 0) : input.val();
                });
                if (masterForm.id) data._method = 'PUT';
                RadioUi.send('#masterSaveButton', {
                    url: masterForm.id ? masterUrls.update.replace(':id', masterForm.id) : masterUrls.store,
                    type: 'POST',
                    dataType: 'json',
                    data,
                })?.done(response => {
                    rmFeedback.toast(response.message);
                    masterForm.open();
                    masterTable.load();
                }).fail(RadioUi.fail);
            },
            remove(id) {
                rmDialog.confirm({
                    tone: 'red',
                    icon: 'trash',
                    title: `Hapus ${masterTitle.toLowerCase()} ini?`,
                    text: 'Data yang masih dipakai radio tidak bisa dihapus.',
                    confirmText: 'Hapus',
                    confirmKind: 'destructive',
                }).then(result => {
                    if (!result.isConfirmed) return;
                    $.ajax({
                        url: masterUrls.destroy.replace(':id', id),
                        type: 'POST',
                        dataType: 'json',
                        data: { _method: 'DELETE' },
                    }).done(response => {
                        rmFeedback.toast(response.message);
                        if (String(masterForm.id) === String(id)) masterForm.open();
                        masterTable.load();
                    }).fail(RadioUi.fail);
                });
            },
        };

        $(function() {
            masterTable.load();
            // The list follows what is typed (a moment after the last key), and Enter at once.
            const search = () => {
                const value = String($('#masterSearch').val() || '').trim();
                if (value === masterTable.search) return;
                masterTable.search = value;
                masterTable.page = 1;
                masterTable.load();
            };
            $('#masterSearch').on('input', debounce(search, 350));
            $('#masterSearch').on('keydown', e => {
                if (e.key === 'Enter') search();
            });
            $('#masterTable').on('click', 'button[data-action]', function() {
                const id = $(this).data('id');
                $(this).tooltip('hide');
                if ($(this).data('action') === 'delete') {
                    masterForm.remove(id);
                    return;
                }
                $.getJSON(masterUrls.show.replace(':id', id))
                    .done(({ data }) => masterForm.open(data))
                    .fail(RadioUi.fail);
            });
        });
    </script>
@endpush
