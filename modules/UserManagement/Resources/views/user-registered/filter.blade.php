<div class="card" id="userFilterCard" style="display: none;">
    <div class="card-header p-2">
        <h4 class="mb-0">Filter</h4>
    </div>
    <div class="card-body p-2">

        <div class="row g-2 ">
            <div class="col-md-12">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label for="companySelectFilter" class=" form-label">Company </label>
                        <select id="companySelectFilter" name="company[]" class="form-select" multiple="multiple">
                            @foreach ($companies as $company)
                                <option value="{{ $company->company_orangehr_code }}">
                                    {{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="roleSelectFilter" class=" form-label">Role </label>
                        <select id="roleSelectFilter" name="role[]" class="form-select" multiple="multiple">
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}">
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="col-12 mt-3 d-flex  justify-content-between gap-2">
                <div class="d-flex justify-content-start gap-2">
                    <div class=" d-flex gap-2">

                        <x-global.btn-detail label="" color="red" size="sm" outline="true"
                            data-bs-toggle="tooltip" onclick="resetFilter()" data-bs-title="Cancel">
                            <x-slot:icon>
                                <i class="ti ti-x"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>

                        <x-global.btn-detail label="" color="green" size="sm" outline="true"
                            data-bs-toggle="tooltip" onclick="applyFilter()" data-bs-title="Apply Filter">
                            <x-slot:icon>
                                <i class="ti ti-check"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>
                    </div>

                </div>
                <div class="d-flex justify-content-end gap-2">
                    {{-- Right Content like Export or Import --}}
                </div>

            </div>
        </div>

    </div>
</div>

@push('javascript')
    {{-- select2 declaration --}}
    <script>
        $(function() {
            $('#userFilterCard #companySelectFilter').select2({
                theme: "bootstrap-5",
                placeholder: 'Select Company',
                allowClear: true,
                multiple: true,
                dropdownParent: $('#userFilterCard'),
                width: '100%',
                closeOnSelect: false
            });
            $('#userFilterCard #roleSelectFilter').select2({
                theme: "bootstrap-5",
                placeholder: 'Select Role',
                allowClear: true,
                multiple: true,
                dropdownParent: $('#userFilterCard'),
                width: '100%',
                closeOnSelect: false
            });
        });
    </script>

    {{-- Filter --}}
    <script>
        let filterList = {
            company: null,
            role: null,
        };

        const applyFilter = () => {
            filterList = {
                company: $('#userFilterCard #companySelectFilter').val(),
                role: $('#userFilterCard #roleSelectFilter').val(),
            };
            currentPage = 1; // biar reset ke halaman pertama
            userRegisteredTable();
        };

        const resetFilter = () => {
            $('#userFilterCard #companySelectFilter').val(null).trigger('change');
            $('#userFilterCard #roleSelectFilter').val(null).trigger('change');
            applyFilter();
        };
    </script>
@endpush
