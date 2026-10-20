@extends('main.index')
@push('style')
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/select2.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/select2-bootstrap.css') }}" />

    <style>
        .role-toolbar .rm-search {
            width: 320px;
        }

        #rolePermissionTable td.role-no {
            width: 70px;
            color: var(--rm-gray-500);
            text-align: center;
        }

        #rolePermissionTable td.role-name {
            white-space: normal;
            overflow-wrap: anywhere;
        }

        #rolePermissionTable td.role-count,
        #rolePermissionTable td.role-actions {
            width: 180px;
            text-align: center;
        }
    </style>
@endpush

@php
    // What the buttons of a row may do (the server checks again).
    $roleMay = [
        'update' => \Illuminate\Support\Facades\Blade::check('permission', 'user-management.role-permission', 'Update'),
        'delete' => \Illuminate\Support\Facades\Blade::check('permission', 'user-management.role-permission', 'Delete'),
    ];
@endphp

@section('page-title', 'Role & Permission')

@section('content')
    <div class="rm-head">
        <div>
            <h1 class="rm-title">Roles and permissions</h1>
            <p class="rm-sub">Roles decide which menus a user sees and what they can do in each. Super Admin always has full
                access.</p>
        </div>
    </div>

    <div class="rm-card">
        <div class="rm-toolbar role-toolbar">
            <label class="rm-search" for="userRegisterdSearch">
                <i class="ti ti-search" aria-hidden="true"></i>
                <input type="search" name="search" id="userRegisterdSearch" value="" autocomplete="off" placeholder="Search…"
                    aria-label="Search roles" />
            </label>
            <button type="button" class="btn-rm secondary" id="toggleResetButton"><i class="ti ti-refresh" aria-hidden="true"></i>
                Reset</button>
            @permission('user-management.role-permission', 'Create')
                <button type="button" class="btn-rm primary ms-auto" id="roleAddButton" onclick="formRoleModal('formRoleModal','Add')"><i
                        class="ti ti-plus" aria-hidden="true"></i> Add Role</button>
            @endpermission
        </div>

        <div class="rm-box">
            <div class="table-responsive">
                <table class="rm-table" id="rolePermissionTable">
                    <thead>
                        <tr>
                            <th scope="col" class="text-center">No</th>
                            <th scope="col" data-key="name">Name</th>
                            <th scope="col" class="text-center">Total user</th>
                            <th scope="col" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="pagination-container"></div>
        </div>
    </div>

    @include('UserManagement::role-permission.modal')
@endsection

@push('javascript')
    <script src="{{ asset('assets/libs/select2/select2.min.js') }}"></script>
    <script src="{{ asset('assets/libs/select2/select2.init.js') }}"></script>

    {{-- URL list --}}
    <script>
        const urlRolePermissionList = "{{ route('user-management.role-permission.list') }}";

        const urlRolePermissionAdd = "{{ route('user-management.role-permission.store') }}"
        const urlRolePermissionEdit = "{{ route('user-management.role-permission.update', ':id') }}"
        const urlRolePermissionDelete = "{{ route('user-management.role-permission.destroy', ':id') }}"
        const urlRolePermissionView = "{{ route('user-management.role-permission.show', ':id') }}"
        const roleMay = @json($roleMay);
    </script>

    {{-- render Table --}}
    <script>
        let currentPage = 1;
        let limit = 10;
        let search = '';
        let orderBy = 'id';
        let orderDir = 'asc';

        const roleEsc = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

        const columns = [{
                key: 'no',
                className: 'role-no',
                render: (data, index) => String((currentPage - 1) * limit + index + 1)
            },
            {
                key: 'name',
                className: 'role-name rm-strong',
                sortable: true,
                render: data => roleEsc(data.name)
            },
            {
                key: 'user_count',
                className: 'role-count',
                sortable: false,
                render: function(data) {
                    const count = Number(data.user_count) || 0;
                    return `<span class="badge bg-${count > 0 ? 'blue' : 'secondary'}-lt">${count}</span>`;
                }
            },
            {
                key: 'actions',
                className: 'role-actions',
                // View for every role; Edit and Delete where the role may be changed and the menu allows. Never on Super Admin
                // (id 1): it has full access whatever its boxes say, and without it nobody manages the rest.
                render: function(data) {
                    const id = Number(data.id);
                    const act = (icon, title, call, danger = false) =>
                        `<button type="button" class="rm-act${danger ? ' is-danger' : ''}" data-id="${id}" onclick="${call}" data-bs-toggle="tooltip" data-bs-title="${title}" aria-label="${title}"><i class="ti ti-${icon}"></i></button>`;
                    let buttons = act('eye', 'View', `formRoleModal('formRoleModal','Preview', ${id})`);
                    if (data.action && data.action.is_active && id !== 1) {
                        if (roleMay.update) buttons += act('edit', 'Edit', `formRoleModal('formRoleModal','Edit', ${id})`);
                        if (roleMay.delete) buttons += act('trash', 'Delete', `formRoleDelete(${id})`, true);
                    }
                    return `<span class="rm-acts">${buttons}</span>`;
                }
            }
        ];

        // Load after the deferred theme script has registered $.fn.tooltip.
        $(function() {
            dataTable();
        });

        function dataTable() {
            renderTableWithFeatures({
                container: '#rolePermissionTable',
                columns,
                currentPage,
                limit,
                loading: true
            });

            const params = {
                page: currentPage,
                paginated: limit,
                search,
                orderBy,
                orderDir,
            };

            $.ajax({
                url: urlRolePermissionList,
                type: 'GET',
                data: params,
                dataType: 'json',
                success: response => {
                    $('.tooltip').remove();
                    renderTableWithFeatures({
                        container: '#rolePermissionTable',
                        data: response.data,
                        columns,
                        currentPage,
                        limit,
                        meta: response.meta,
                        paginationLabels: { noun: 'roles' },
                        onPageChange: page => {
                            currentPage = page;
                            dataTable();
                        },
                        onLimitChange: newLimit => {
                            limit = newLimit;
                            currentPage = 1;
                            dataTable();
                        },
                        onSortChange: (key, dir) => {
                            orderBy = key;
                            orderDir = dir;
                            dataTable();
                        },
                        currentSortKey: orderBy,
                        currentSortDir: orderDir
                    });

                    $('#rolePermissionTable [data-bs-toggle="tooltip"]').tooltip({ trigger: 'hover' });
                },
                error: () => {
                    renderTableWithFeatures({
                        container: '#rolePermissionTable',
                        columns,
                        currentPage,
                        limit,
                        emptyText: 'Error loading data. Please try again.'
                    });
                }
            });
        }

        const formRoleDelete = (dataId) => {
            $('[data-bs-toggle="tooltip"]').tooltip('hide');
            rmDialog.confirm({
                tone: 'red',
                icon: 'trash',
                title: 'Delete this role?',
                text: 'It is taken off the list of roles. Users who hold it lose what it gives them.',
                confirmText: '<i class="ti ti-trash"></i> Delete',
                cancelText: 'Cancel',
                confirmKind: 'destructive',
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }
                $.ajax({
                    type: 'Delete',
                    url: urlRolePermissionDelete.replace(':id', dataId),
                    success: function(response) {
                        rmFeedback.toast(response.message);
                        $('.tooltip').remove();
                        // Step back when the deleted row was the only one on this page.
                        if (currentPage > 1 && $('#rolePermissionTable tbody tr').length <= 1) {
                            currentPage -= 1;
                        }
                        dataTable();
                    },
                    error: function(xhr) {
                        let errorMessage = '';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                            errorMessage = [].concat(xhr.responseJSON.errors).join(' ');
                        } else {
                            errorMessage = xhr.statusText;
                        }
                        Swal.fire({
                            title: 'Oops...',
                            text: errorMessage,
                            icon: 'error',
                            showConfirmButton: true,
                        });
                    }
                });
            });
        }
    </script>

    {{-- The search: as it is typed (after a pause), and on Enter; Reset starts over --}}
    <script>
        $(function() {
            const roleSearch = () => {
                const typed = String($('#userRegisterdSearch').val() || '').trim();
                if (typed === search) {
                    return;
                }
                search = typed;
                currentPage = 1;
                dataTable();
            };
            $('#userRegisterdSearch').on('input', debounce(roleSearch, 400)).on('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    roleSearch();
                }
            });
            $('#toggleResetButton').on('click', function() {
                currentPage = 1;
                limit = 10;
                search = '';
                orderBy = 'id';
                orderDir = 'asc';
                $('#userRegisterdSearch').val('');
                dataTable();
            });
        });
    </script>
@endpush
