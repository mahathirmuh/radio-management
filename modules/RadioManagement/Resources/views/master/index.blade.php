@extends('main.index')

@include('RadioManagement::partials.scripts')

@section('page-title', $master['title'])

@section('content')
    @php
        $fields = collect($master['fields'])->map(fn ($field) => collect($field)->except('rules')->all());
    @endphp
    <div class="row g-2">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="row g-2">
                        <div class="col-12 d-flex justify-content-between gap-2">
                            <div class="d-flex gap-2">
                                <input type="text" id="masterSearch" class="form-control form-control-lg pb-1 rounded-3"
                                    autocomplete="off" placeholder="Cari…" />
                                <x-global.btn-detail label="" color="blue" size="sm" outline="true" id="masterSearchButton"
                                    data-bs-toggle="tooltip" data-bs-title="Cari">
                                    <x-slot:icon>
                                        <i class="ti ti-search"></i>
                                    </x-slot:icon>
                                </x-global.btn-detail>
                            </div>
                            <x-global.btn-detail label="Tambah {{ $master['title'] }}" color="blue" size="sm"
                                onclick="masterForm.open()">
                                <x-slot:icon>
                                    <i class="ti ti-plus"></i>
                                </x-slot:icon>
                            </x-global.btn-detail>
                        </div>

                        <div class="col-12">
                            <div class="card overflow-hidden mdka-border-gray-200">
                                <div class="card-body table-responsive p-0">
                                    <table class="table table-vcenter mdka-border-gray-100 mb-0" id="masterTable">
                                        <thead>
                                            <tr class="mdka-bg-gray-50">
                                                <th class="text-capitalize fs-5 py-3 text-center" width="30">No</th>
                                                @foreach ($fields as $name => $field)
                                                    <th class="text-capitalize fs-5 py-3"
                                                        @if (in_array($name, $master['sortable'], true)) data-key="{{ $name }}" @endif>
                                                        {{ $field['label'] }}</th>
                                                @endforeach
                                                <th class="text-capitalize fs-5 py-3 text-center">Dipakai</th>
                                                <th class="text-capitalize fs-5 py-3 text-center">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="pagination-container mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" data-bs-backdrop="static" id="masterModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-3">
                <button type="button" class="btn-close btn-pinned" data-bs-dismiss="modal" aria-label="Tutup"></button>
                <div class="modal-body p-0">
                    <h3 class="modal-title mb-3">{{ $master['title'] }}</h3>
                    <form id="masterForm" autocomplete="off">
                        <div class="row g-2">
                            @foreach ($fields as $name => $field)
                                <div class="col-12">
                                    @if ($field['type'] === 'boolean')
                                        <label class="form-check form-switch mt-1">
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
                                            <div class="form-hint">{{ $field['help'] }}</div>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                            <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                                <x-global.btn-detail label="Batal" color="secondary" size="sm" outline="true"
                                    data-bs-dismiss="modal">
                                    <x-slot:icon>
                                        <i class="ti ti-x"></i>
                                    </x-slot:icon>
                                </x-global.btn-detail>
                                <x-global.btn-detail label="Simpan" color="blue" size="sm" id="masterSaveButton"
                                    onclick="masterForm.submit()">
                                    <x-slot:icon>
                                        <i class="ti ti-device-floppy"></i>
                                    </x-slot:icon>
                                </x-global.btn-detail>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('javascript')
    <script>
        const masterFields = @json($fields);
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
                ...Object.entries(masterFields).map(([name, field]) => ({
                    key: name,
                    sortable: true,
                    render: row => {
                        const value = row[name];
                        if (field.type === 'boolean') {
                            return value ? RadioUi.badge('Aktif', 'green') : RadioUi.badge('Nonaktif', 'secondary');
                        }
                        if (field.type === 'select') return RadioUi.dash(field.options[value] ?? value);
                        if (field.type === 'date' && value) return RadioUi.esc(value.split('-').reverse().join('-'));
                        if (field.type === 'textarea' && value) {
                            return `<div class="small text-wrap" style="max-width: 420px">${RadioUi.esc(value)}</div>`;
                        }
                        return RadioUi.dash(value);
                    },
                })),
                {
                    key: 'usage',
                    className: 'text-center',
                    render: row => `${row.usage}`
                },
                {
                    key: 'actions',
                    className: 'text-center text-nowrap',
                    render: row => `
                        <button type="button" class="btn btn-sm px-2 py-1 rounded-2 mdka-btn-orange-500 text-white border-0" data-action="edit" data-id="${row.id}" data-bs-toggle="tooltip" data-bs-title="Edit"><i class="ti ti-edit"></i></button>
                        <button type="button" class="btn btn-sm px-2 py-1 rounded-2 btn-outline-red" data-action="delete" data-id="${row.id}" data-bs-toggle="tooltip" data-bs-title="Hapus"><i class="ti ti-trash"></i></button>`
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

        const masterForm = {
            id: null,
            open(row = null) {
                masterForm.id = row ? row.id : null;
                $('#masterModal .modal-title').text((row ? 'Edit ' : 'Tambah ') + @json($master['title']));
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
                $('#masterModal').modal('show');
            },
            submit() {
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
                    $('#masterModal').modal('hide');
                    Swal.fire('Berhasil', response.message, 'success');
                    masterTable.load();
                }).fail(RadioUi.fail);
            },
            remove(id) {
                Swal.fire({
                    title: 'Hapus data ini?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '<i class="ti ti-trash"></i> Hapus',
                    cancelButtonText: '<i class="ti ti-x"></i> Batal',
                    reverseButtons: true,
                }).then(result => {
                    if (!result.isConfirmed) return;
                    $.ajax({
                        url: masterUrls.destroy.replace(':id', id),
                        type: 'POST',
                        dataType: 'json',
                        data: { _method: 'DELETE' },
                    }).done(response => {
                        Swal.fire('Terhapus', response.message, 'success');
                        masterTable.load();
                    }).fail(RadioUi.fail);
                });
            },
        };

        $(function() {
            masterTable.load();
            $('#masterSearchButton').on('click', () => {
                masterTable.search = $('#masterSearch').val().trim();
                masterTable.page = 1;
                masterTable.load();
            });
            $('#masterSearch').on('keydown', e => {
                if (e.key === 'Enter') $('#masterSearchButton').trigger('click');
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
