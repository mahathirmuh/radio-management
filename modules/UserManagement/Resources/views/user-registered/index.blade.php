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

@section('page-title', 'User Registered')

@section('content')


    <div class="row g-2">
        {{-- Filter --}}
        <div class="col-12">
            @include('UserManagement::user-registered.filter')
        </div>
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
                                    <x-global.btn-detail label="" color="purple" size="sm" outline="true"
                                        id="toggleFilterButton" data-bs-toggle="tooltip" data-bs-title="Filter">
                                        <x-slot:icon>
                                            <i class="ti ti-filter"></i>
                                        </x-slot:icon>
                                    </x-global.btn-detail>

                                    <x-global.btn-detail label="" color="red" size="sm" outline="true"
                                        id="toggleResetButton" data-bs-toggle="tooltip" data-bs-title="Reset">
                                        <x-slot:icon>
                                            <i class="ti ti-restore"></i>
                                        </x-slot:icon>
                                    </x-global.btn-detail>


                                </div>

                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <div class=" d-flex gap-2">
                                    <x-global.btn-detail label="Add User" color="blue" size="sm" data-id=""
                                        onclick="toggleUserModal('userModal','add', null)" data-bs-toggle="tooltip"
                                        data-bs-title="Add">
                                        <x-slot:icon>
                                            <i class="ti ti-plus"></i>
                                        </x-slot:icon>
                                    </x-global.btn-detail>
                                </div>
                            </div>

                        </div>


                        <div class="col-12">

                            <div class="card overflow-hidden mdka-border-gray-200">
                                <div class="card-body table-responsive p-0">
                                    <table class="table table-vcenter mdka-border-gray-100 text-nowrap" id="userTable">
                                        <thead>
                                            <tr class="mdka-bg-gray-50 ">
                                                <th scope="col" width="30"
                                                    class="text-capitalize fs-5 py-3 text-center">No</th>
                                                <th scope="col" data-key="name" class="text-capitalize fs-5 py-3">
                                                    Name
                                                </th>
                                                <th scope="col" data-key="email" class="text-capitalize fs-5 py-3 ">
                                                    Email
                                                </th>
                                                <th scope="col" class="text-capitalize fs-5 py-3 text-center">Role
                                                </th>
                                                <th scope="col" class="text-capitalize fs-5 py-3 text-center">Position
                                                </th>
                                                <th scope="col" class="text-capitalize fs-5 py-3 text-center">
                                                    Action</th>
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

    @include('UserManagement::user-registered.modal')

@endsection
@push('javascript')
    <script src="{{ asset('assets/libs/select2/select2.min.js') }}"></script>
    <script src="{{ asset('assets/libs/select2/select2.init.js') }}"></script>
    <script src="{{ asset('assets/libs/datatables/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/libs/datatables/datatable-bootstrap5.js') }}"></script>


    {{-- URL list --}}
    <script>
        const urlUserRegisteredList = "{{ route('user-management.user-registered.list') }}";
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
                key: 'email',
                headerClassName: 'text-center',
                className: 'text-center',
                sortable: true
            },
            {
                key: 'role',
                className: 'text-center',
                sortable: false
            },
            {
                key: 'position',
                className: 'text-center',
                sortable: false
            },
            {
                key: 'actions',
                render: (item) => {
                    const id = item.id;

                    return `
                        <x-global.btn-detail label="" color="orange" size="sm" data-id="${id}" onclick="toggleUserModal('userModal','edit', ${id})" data-bs-toggle="tooltip" data-bs-title="Edit">
                            <x-slot:icon>
                                <i class="ti ti-edit"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>

                        <x-global.btn-detail label="" color="red" size="sm" data-id="${id}" onclick="userDelete(${id})" data-bs-toggle="tooltip" data-bs-title="Delete">
                            <x-slot:icon>
                                <i class="ti ti-trash"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>
                    `;
                },
                className: 'text-center'
            }

        ];

        // Load after the deferred theme script has registered $.fn.tooltip; a fast
        // response could otherwise reach the success handler before it exists.
        $(function() {
            userRegisteredTable();
        });

        function userRegisteredTable() {
            renderTableWithFeatures({
                container: '#userTable',
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
                search,
                ...filterList
            };

            $.ajax({
                url: urlUserRegisteredList,
                type: 'GET',
                data: params,
                dataType: 'json',
                success: response => {
                    renderTableWithFeatures({
                        container: '#userTable',
                        data: response.data,
                        columns,
                        currentPage,
                        limit,
                        meta: response.meta,
                        onPageChange: page => {
                            currentPage = page;
                            userRegisteredTable();
                        },
                        onLimitChange: newLimit => {
                            limit = newLimit;
                            currentPage = 1;
                            userRegisteredTable();
                        },
                        onSortChange: (key, dir) => {
                            orderBy = key;
                            orderDir = dir;
                            userRegisteredTable();
                        },
                        currentSortKey: orderBy,
                        currentSortDir: orderDir
                    });

                    $('[data-bs-toggle="tooltip"]').tooltip();
                },
                error: () => {
                    renderTableWithFeatures({
                        container: '#userTable',
                        columns,
                        currentPage,
                        limit,
                        emptyText: 'Error loading data. Please try again.'
                    });
                }
            });
        }
    </script>

    {{-- toggle filter button hide show --}}
    <script>
        $(document).ready(function() {
            $("#toggleFilterButton").click(function() {
                $("#userFilterCard").slideToggle(300, function() {

                });
            });
        });
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
                filterList = {
                    position: null,
                    role: null,
                };
                userRegisteredTable();

                $("#userRegisterdSearch").val('');
                $("#userFilterCard").slideUp(300, function() {
                    // Reset filter inputs here if needed
                    $('#userFilterCard #positionSelectFilter').val(null).trigger('change');
                    $('#userFilterCard #roleSelectFilter').val(null).trigger('change');
                });
            });
        });
    </script>
    {{-- toggle refresh button hide show --}}
    <script>
        $(document).ready(function() {
            $("#toggleSearchButton").click(function() {
                search = $("#userRegisterdSearch").val();
                userRegisteredTable();

            });
        });
    </script>
@endpush
