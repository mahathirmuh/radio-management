{{-- The filters of the list, in its toolbar: roles (several may be chosen) and status. Each applies as it is changed. --}}
<div class="user-role-pick">
    <label for="roleSelectFilter" class="visually-hidden">{{ __('UserManagement::users.filter.role') }}</label>
    <select id="roleSelectFilter" name="role[]" multiple="multiple">
        @foreach ($roles as $role)
            <option value="{{ $role->id }}">{{ $role->name }}</option>
        @endforeach
    </select>
</div>
<div class="user-status-pick rm-field">
    <label for="statusSelectFilter" class="visually-hidden">{{ __('UserManagement::users.filter.status') }}</label>
    <select id="statusSelectFilter" name="status" class="form-select">
        <option value="">{{ __('UserManagement::users.filter.all_statuses') }}</option>
        <option value="active">{{ __('UserManagement::users.list.active') }}</option>
        <option value="inactive">{{ __('UserManagement::users.list.inactive') }}</option>
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
            // "Select all" and "Clear" are the pickers' own (ui.pickers).
            rmPickers.multi('#roleSelectFilter', { all: @json(__('UserManagement::users.filter.all_roles')) });
        });
    </script>
@endpush
