<div class="card" id="radioFilterCard" style="display: none;">
    <div class="card-header p-2">
        <h4 class="mb-0">Filter</h4>
    </div>
    <div class="card-body p-2">
        <div class="row g-2">
            <div class="col-md-3">
                <label for="filterCompany" class="form-label">Company (pengguna)</label>
                <select id="filterCompany" class="form-select" multiple="multiple">
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="filterOwner" class="form-label">Pemilik Aset</label>
                <select id="filterOwner" class="form-select" multiple="multiple">
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="filterDepartment" class="form-label">Department</label>
                <select id="filterDepartment" class="form-select" multiple="multiple">
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="filterType" class="form-label">Tipe Radio</label>
                <select id="filterType" class="form-select" multiple="multiple">
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="filterTemplate" class="form-label">Template Channel</label>
                <select id="filterTemplate" class="form-select" multiple="multiple">
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="filterPlacement" class="form-label">Posisi</label>
                <select id="filterPlacement" class="form-select" multiple="multiple">
                    @foreach ($placements as $placement)
                        <option value="{{ $placement['value'] }}">{{ $placement['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="filterCondition" class="form-label">Kondisi</label>
                <select id="filterCondition" class="form-select" multiple="multiple">
                    @foreach ($conditions as $condition)
                        <option value="{{ $condition['value'] }}">{{ $condition['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="filterIssue" class="form-label">Hasil Cek</label>
                <select id="filterIssue" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($issues as $code => $issue)
                        <option value="{{ $code }}">{{ $issue['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 mt-3 d-flex justify-content-between gap-2">
                <div class="d-flex gap-2">
                    <x-global.btn-detail label="" color="red" size="sm" outline="true" data-bs-toggle="tooltip"
                        onclick="radioFilter.reset()" data-bs-title="Kosongkan Filter">
                        <x-slot:icon>
                            <i class="ti ti-x"></i>
                        </x-slot:icon>
                    </x-global.btn-detail>
                    <x-global.btn-detail label="" color="green" size="sm" outline="true" data-bs-toggle="tooltip"
                        onclick="radioFilter.apply()" data-bs-title="Terapkan Filter">
                        <x-slot:icon>
                            <i class="ti ti-check"></i>
                        </x-slot:icon>
                    </x-global.btn-detail>
                </div>
            </div>
        </div>
    </div>
</div>

@push('javascript')
    <script>
        $(function() {
            [
                ['#filterCompany', 'Pilih company'],
                ['#filterOwner', 'Pilih pemilik aset'],
                ['#filterDepartment', 'Pilih department'],
                ['#filterType', 'Pilih tipe'],
                ['#filterTemplate', 'Pilih template'],
                ['#filterPlacement', 'Pilih posisi'],
                ['#filterCondition', 'Pilih kondisi'],
            ].forEach(([selector, placeholder]) => {
                $(selector).select2({
                    theme: 'bootstrap-5',
                    placeholder,
                    allowClear: true,
                    multiple: true,
                    dropdownParent: $('#radioFilterCard'),
                    width: '100%',
                    closeOnSelect: false
                });
            });
        });

        const radioFilter = {
            values: {},
            read() {
                return {
                    company: $('#filterCompany').val(),
                    owner: $('#filterOwner').val(),
                    department: $('#filterDepartment').val(),
                    type: $('#filterType').val(),
                    template: $('#filterTemplate').val(),
                    placement: $('#filterPlacement').val(),
                    condition: $('#filterCondition').val(),
                    issue: $('#filterIssue').val(),
                };
            },
            apply() {
                radioFilter.values = radioFilter.read();
                radioTable.page = 1;
                radioTable.load();
            },
            reset() {
                $('#radioFilterCard select[multiple]').val(null).trigger('change');
                $('#filterIssue').val('');
                radioFilter.apply();
            },
        };
    </script>
@endpush
