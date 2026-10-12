@extends('main.index')
@push('style')
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/select2.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/select2-bootstrap.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/libs/datatables/datatables.min.css') }}" />

    <style>
        .select2-container.select2-container-disabled .select2-choice {
            background-color: #ddd;
            border-color: #a8a8a8;
        }
    </style>
@endpush

@section('page-title', 'Role & Permission')

@section('content')
    <div class="row g-2">
        {{-- Table --}}
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="row g-2">
                        <div class="col-12 d-flex  justify-content-between gap-2">
                            <div class="d-flex justify-content-start gap-2">
                                <div class=" d-flex gap-2">
                                    <div class="d-flex gap-2">
                                        <input type="text" name="search" id="userRegisterdSearch" value=""
                                            class="form-control form-control-lg pb-1 rounded-3" autocomplete="off"
                                            placeholder="Search…" />
                                        <x-global.btn-detail label="" color="blue" size="sm" outline="true"
                                            id="toggleSearchButton" data-bs-toggle="tooltip" data-bs-title="Search">
                                            <x-slot:icon>
                                                <i class="ti ti-search"></i>
                                            </x-slot:icon>
                                        </x-global.btn-detail>
                                    </div>
                                    <x-global.btn-detail label="" color="red" size="sm" outline="true"
                                        id="toggleResetButton" data-bs-toggle="tooltip" data-bs-title="Reset">
                                        <x-slot:icon>
                                            <i class="ti ti-restore"></i>
                                        </x-slot:icon>
                                    </x-global.btn-detail>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <div class="d-flex gap-2">
                                    @permission('user-management.role-permission', 'Create')
                                        <x-global.btn-detail label="Add Role" color="blue" size="sm" data-id=""
                                                             onclick="formRoleModal('formRoleModal','Add')" data-bs-toggle="tooltip"
                                                             data-bs-title="Add">
                                            <x-slot:icon>
                                                <i class="ti ti-plus"></i>
                                            </x-slot:icon>
                                        </x-global.btn-detail>
                                    @endpermission
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card overflow-hidden mdka-border-gray-200">
                                <div class="card-body table-responsive p-0">
                                    <table class="table table-vcenter mdka-border-gray-100 text-nowrap" id="rolePermissionTable">
                                        <thead>
                                            <tr class="mdka-bg-gray-50 ">
                                                <th scope="col" width="30"
                                                    class="text-capitalize fs-5 py-3 text-center">No</th>
                                                <th scope="col" data-key="name" class="text-capitalize fs-5 py-3">
                                                    Name
                                                </th>
                                                <th scope="col" class="text-capitalize fs-5 py-3 text-center">
                                                    Total User
                                                </th>
                                                <th scope="col" class="text-capitalize fs-5 py-3 text-center">
                                                    Action
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="pagination-container mt-2">
                                <!-- Pagination will be rendered here -->
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('UserManagement::role-permission.modal')

@endsection
@push('javascript')
    <script src="{{ asset('assets/libs/select2/select2.min.js') }}"></script>
    <script src="{{ asset('assets/libs/select2/select2.init.js') }}"></script>
    <script src="{{ asset('assets/libs/datatables/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/libs/datatables/datatable-bootstrap5.js') }}"></script>


    {{-- URL list --}}
    <script>
        const urlRolePermissionList = "{{ route('user-management.role-permission.list') }}";

        const urlRolePermissionAdd = "{{ route('user-management.role-permission.store') }}"
        const urlRolePermissionEdit = "{{ route('user-management.role-permission.update', ':id') }}"
        const urlRolePermissionDelete = "{{ route('user-management.role-permission.destroy', ':id') }}"
        const urlRolePermissionView = "{{ route('user-management.role-permission.show', ':id') }}"
    </script>

    {{-- render Table --}}
    <script>
        let currentPage = 1;
        let limit = 10;
        let search = '';
        let orderBy = 'id';
        let orderDir = 'asc';


        const columns = [
            'index',
            {
                key: 'name',
                headerClassName: 'text-center',
                className: 'text-center',
                sortable: true
            },
            {
                key: 'user_count',
                className: 'text-center',
                sortable: false,
                render: function(data) {
                    const count = Number(data.user_count) || 0;
                    const cls = count > 0 ? 'mdka-bg-blue-500 text-white' : 'bg-light text-muted border';
                    return `<span class="badge ${cls} px-2 py-1 rounded-pill">${count}</span>`;
                }
            },
            {
                key: 'actions',
                render: function(data, type, row) {
                    var actionButtons = `
                            <x-global.btn-detail label="" color="blue" size="sm" data-id="${data.id}" onclick="formRoleModal('formRoleModal','Preview', ${data.id})" data-bs-toggle="tooltip" data-bs-title="View">
                                <x-slot:icon>
                                    <i class="ti ti-eye"></i>
                                </x-slot:icon>
                            </x-global.btn-detail>
                        `;

                    if (data.action.is_active) {
                        actionButtons += `
                            <x-global.btn-detail label="" color="orange" size="sm" data-id="${data.id}" onclick="formRoleModal('formRoleModal','Edit', ${data.id})" data-bs-toggle="tooltip" data-bs-title="Edit">
                                <x-slot:icon>
                                    <i class="ti ti-edit"></i>
                                </x-slot:icon>
                            </x-global.btn-detail>

                            <x-global.btn-detail label="" color="red" size="sm" data-id="${data.id}" onclick="formRoleDelete(${data.id})" data-bs-toggle="tooltip" data-bs-title="Delete">
                                <x-slot:icon>
                                    <i class="ti ti-trash"></i>
                                </x-slot:icon>
                            </x-global.btn-detail>
                        `;
                    }
                    // Conditionally add Edit and Delete buttons based on row.submited

                    return actionButtons;
                },
                className: 'text-center'
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
                    renderTableWithFeatures({
                        container: '#rolePermissionTable',
                        data: response.data,
                        columns,
                        currentPage,
                        limit,
                        meta: response.meta,
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

                    $('[data-bs-toggle="tooltip"]').tooltip();
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
            Swal.fire({
                title: 'Delete Data?',
                text: 'Data will be Deleted!',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="ti ti-check"></i> Delete',
                cancelButtonText: '<i class="ti ti-x"></i> Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Collect data from input, select, and textarea elements within the div

                    $.ajax({
                        type: 'Delete',
                        url: urlRolePermissionDelete.replace(':id', dataId),
                        success: function(response) {
                            Swal.fire({
                                title: 'Deleted',
                                text: response.message,
                                icon: 'success',
                                showConfirmButton: false,
                                timer: 2000,
                                timerProgressBar: true
                            });
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
                                errorMessage = xhr.responseJSON.errors;
                            } else {
                                errorMessage = xhr.statusText;
                            }
                            Swal.fire({
                                title: 'Oops...',
                                html: errorMessage,
                                icon: 'error',
                                showConfirmButton: true,
                            });
                        }
                    });

                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    Swal.fire(
                        'Canceled!',
                        'Data not deleted!',
                        'error'
                    )
                }
            });
        }
    </script>

    {{-- toggle refresh button hide show --}}
    <script>
        $(document).ready(function() {
            $("#toggleResetButton").click(function() {
                currentPage = 1;
                limit = 10;
                search = '';
                orderBy = 'id';
                orderDir = 'asc';
                dataTable();

                $("#userRegisterdSearch").val('');
            });
        });
    </script>
    {{-- toggle refresh button hide show --}}
    <script>
        $(document).ready(function() {
            $("#toggleSearchButton").click(function() {
                search = $("#userRegisterdSearch").val();
                dataTable();
            });
        });
    </script>
@endpush
