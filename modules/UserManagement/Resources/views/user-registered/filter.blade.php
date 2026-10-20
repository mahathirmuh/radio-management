{{-- The filters of the list, in its toolbar: roles (several may be chosen) and status. Each applies as it is changed. --}}
<div class="user-role-pick">
    <label for="roleSelectFilter" class="visually-hidden">Role</label>
    <select id="roleSelectFilter" name="role[]" multiple="multiple">
        @foreach ($roles as $role)
            <option value="{{ $role->id }}">{{ $role->name }}</option>
        @endforeach
    </select>
</div>
<div class="user-status-pick rm-field">
    <label for="statusSelectFilter" class="visually-hidden">Status</label>
    <select id="statusSelectFilter" name="status" class="form-select">
        <option value="">All statuses</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
    </select>
</div>

@push('javascript')
    {{-- Filter --}}
    <script>
        let filterList = {
            role: null,
            status: null,
        };

        const applyFilter = () => {
            const roles = $('#roleSelectFilter').val() || [];
            filterList = {
                role: roles.length ? roles : null,
                status: $('#statusSelectFilter').val() || null,
            };
            currentPage = 1; // biar reset ke halaman pertama
            userRegisteredTable();
        };

        $(function() {
            $('#roleSelectFilter, #statusSelectFilter').on('change', applyFilter);
        });
    </script>

    {{-- The roles as a menu of boxes to tick (the design layer's picker). --}}
    <script>
        $(function() {
            rmPickers.multi('#roleSelectFilter', { all: 'All roles', pickAll: 'Select all', clear: 'Clear' });
        });
    </script>
@endpush
