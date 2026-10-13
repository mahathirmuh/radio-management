<div class="card" id="userFilterCard" style="display: none;">
    <div class="card-header p-2">
        <h4 class="mb-0">Filter</h4>
    </div>
    <div class="card-body p-2">

        <div class="row g-2 ">
            <div class="col-md-12">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label for="positionSelectFilter" class=" form-label">Position </label>
                        <select id="positionSelectFilter" name="position[]" class="form-select" multiple="multiple">
                            @foreach ($positions as $position)
                                <option value="{{ $position }}">{{ $position }}</option>
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
                    <div class="col-md-3">
                        <label for="statusSelectFilter" class=" form-label">Status </label>
                        <select id="statusSelectFilter" name="status" class="form-select">
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
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
            $('#userFilterCard #positionSelectFilter').select2({
                theme: "bootstrap-5",
                placeholder: 'Select Position',
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
            position: null,
            role: null,
            status: null,
        };

        const applyFilter = () => {
            filterList = {
                position: $('#userFilterCard #positionSelectFilter').val(),
                role: $('#userFilterCard #roleSelectFilter').val(),
                status: $('#userFilterCard #statusSelectFilter').val() || null,
            };
            currentPage = 1; // biar reset ke halaman pertama
            userRegisteredTable();
        };

        const resetFilter = () => {
            $('#userFilterCard #positionSelectFilter').val(null).trigger('change');
            $('#userFilterCard #roleSelectFilter').val(null).trigger('change');
            $('#userFilterCard #statusSelectFilter').val('');
            applyFilter();
        };
    </script>
@endpush
