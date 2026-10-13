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

        /* The form of a user: labels at the left, and what the employee directory found under its search. */
        #userModal .col-form-label {
            font-weight: 500;
        }

        #userModal .user-directory-note {
            font-size: .75rem;
        }

        #userModal .user-directory-id {
            font-size: .7rem;
        }

        /* The list of users: rows with room, text to the left, the actions at the right edge. */
        #userRegisterdSearch {
            width: 290px;
            max-width: 100%;
        }

        #userTable th,
        #userTable td {
            padding: .8rem 1rem;
        }

        #userTable th:first-child,
        #userTable td:first-child {
            padding-left: 1.25rem;
        }

        #userTable .sort-icon svg {
            width: 14px;
            height: 14px;
        }

        #userTable .user-position {
            display: block;
            font-size: .75rem;
            color: #667382;
        }

        #userTable th {
            font-weight: 600;
        }

        #userTable td {
            font-size: .8125rem;
        }

        #userTable .user-name {
            font-weight: 600;
        }

        #userTable .user-muted {
            color: #667382;
        }

        #userTable .user-actions {
            text-align: right;
            white-space: nowrap;
        }

        #userTable .user-actions .btn {
            width: 26px;
            height: 26px;
            padding: 0 !important;
            margin-left: 2px;
        }

        #userTable tr.user-off .user-name,
        #userTable tr.user-off .user-position,
        #userTable tr.user-off .user-muted {
            opacity: .55;
        }
    </style>
@endpush

@section('page-title', 'Users')

@section('content')


    <h2 class="mb-3">Users</h2>

    <div class="row g-2">
        {{-- Filter --}}
        <div class="col-12">
            @include('UserManagement::user-registered.filter')
        </div>
        {{-- Table --}}
        <div class="col-12">
            <div class="card">
                <div class="card-body p-3 p-md-4">
                    <div class="row g-3">
                        <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <input type="text" name="search" id="userRegisterdSearch" value=""
                                    class="form-control rounded-2" autocomplete="off" placeholder="Name or email"
                                    aria-label="Search by name or email" />
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
                            <button type="button" class="btn btn-primary px-3" id="userAddButton"
                                onclick="toggleUserModal('userModal','add', null)">
                                <i class="ti ti-plus me-1"></i> Add User
                            </button>
                        </div>

                        <div class="col-12">

                            <div class="card overflow-hidden mdka-border-gray-200">
                                <div class="card-body table-responsive p-0">
                                    <table class="table table-vcenter mdka-border-gray-100 text-nowrap mb-0" id="userTable">
                                        <thead>
                                            <tr class="mdka-bg-gray-50 ">
                                                <th scope="col" data-key="name" class="text-capitalize fs-5">
                                                    Name
                                                </th>
                                                <th scope="col" data-key="email" class="text-capitalize fs-5">
                                                    Email
                                                </th>
                                                <th scope="col" class="text-capitalize fs-5">Role
                                                </th>
                                                <th scope="col" data-key="active" class="text-capitalize fs-5">
                                                    Status
                                                </th>
                                                <th scope="col" class="text-capitalize fs-5">Sign-In
                                                </th>
                                                <th scope="col" data-key="last_login_at" class="text-capitalize fs-5">
                                                    Last Sign-In
                                                </th>
                                                <th scope="col" class="fs-5"><span class="visually-hidden">Action</span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        </tbody>

                                    </table>

                                </div>

                            </div>
                            <div class="pagination-container mt-3">
                                <!-- Pagination will be rendered here -->
                            </div>

                        </div>



                    </div>
                </div>
            </div>
        </div>
    </div>

    <p class="text-secondary mt-3 mb-0" style="font-size: .75rem">
        Deactivating ends the account's session on its next request and keeps everything it did. Deleting takes the
        account off this list. Prefer deactivation for someone who has left.
    </p>

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
        // The columns of the last sign-in come with a migration: until it has run they are not sorted by.
        const userSignInReady = @json($signInReady);
    </script>

    {{-- render Table --}}
    <script>
        let currentPage = 1;
        let limit = 10;
        let search = '';
        let orderBy = 'name';
        let orderDir = 'asc';

        // What comes from the data goes into the page as text, never as markup.
        const userEsc = (value) => $('<div>').text(value == null ? '' : String(value)).html();
        const userBadge = (text, color, title = '') =>
            `<span class="badge bg-${color}-lt"${title ? ` title="${userEsc(title).replace(/"/g, '&quot;')}"` : ''}>${userEsc(text)}</span>`;
        // How an account gets in (User::signInMethod()). No good/bad scale: only "Pending" is tinted to be seen,
        // because it is the one where somebody may be locked out without knowing it.
        const userSignInColors = {
            LDAP: 'secondary',
            Microsoft: 'blue',
            Both: 'purple',
            Pending: 'yellow',
        };
        const userSignInMeanings = @json($signInMeanings);

        const columns = [{
                key: 'name',
                sortable: true,
                // The position goes under the name: it is said of the person, and the table keeps its width.
                render: (item) => `<span class="user-name">${userEsc(item.name)}</span>` +
                    (item.is_self ? ` <span class="badge bg-blue-lt ms-1">You</span>` : '') +
                    (item.position && item.position !== '-' ? `<span class="user-position">${userEsc(item.position)}</span>` : '')
            },
            {
                key: 'email',
                sortable: true,
                render: (item) => `<span class="user-muted">${userEsc(item.email)}</span>`
            },
            {
                key: 'role',
                sortable: false,
                render: (item) => userEsc(item.role || '-')
            },
            {
                key: 'active',
                sortable: true,
                render: (item) => item.active ? userBadge('Active', 'green') : userBadge('Inactive', 'red')
            },
            {
                key: 'sign_in',
                sortable: false,
                render: (item) => {
                    const method = item.sign_in || 'Pending';
                    return userBadge(method, userSignInColors[method] || 'secondary', userSignInMeanings[method] || '');
                }
            },
            {
                key: 'last_login_at',
                sortable: userSignInReady,
                render: (item) => `<span class="user-muted">${userEsc(item.last_sign_in || 'Never')}</span>`
            },
            {
                key: 'actions',
                render: (item) => {
                    const id = Number(item.id);
                    const edit = `
                        <x-global.btn-detail label="" color="orange" size="sm" data-id="${id}" onclick="toggleUserModal('userModal','edit', ${id})" data-bs-toggle="tooltip" data-bs-title="Edit" aria-label="Edit">
                            <x-slot:icon>
                                <i class="ti ti-edit"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>`;

                    // Your own row: the edit only. (The server refuses the rest as well.)
                    if (item.is_self) {
                        return edit;
                    }

                    const status = item.active ? `
                        <x-global.btn-detail label="" color="gray" size="sm" data-id="${id}" onclick="userStatus(${id}, false)" data-bs-toggle="tooltip" data-bs-title="Deactivate" aria-label="Deactivate">
                            <x-slot:icon>
                                <i class="ti ti-user-off"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>` : `
                        <x-global.btn-detail label="" color="green" size="sm" data-id="${id}" onclick="userStatus(${id}, true)" data-bs-toggle="tooltip" data-bs-title="Activate" aria-label="Activate">
                            <x-slot:icon>
                                <i class="ti ti-user-check"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>`;

                    // An account that signs in with its directory password has none to set here.
                    const password = item.directory ? '' : `
                        <x-global.btn-detail label="" color="blue" size="sm" data-id="${id}" onclick="toggleUserPasswordModal(${id})" data-bs-toggle="tooltip" data-bs-title="Set password" aria-label="Set password">
                            <x-slot:icon>
                                <i class="ti ti-key"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>`;

                    return edit + status + password + `
                        <x-global.btn-detail label="" color="red" size="sm" data-id="${id}" onclick="userDelete(${id})" data-bs-toggle="tooltip" data-bs-title="Delete" aria-label="Delete">
                            <x-slot:icon>
                                <i class="ti ti-trash"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>
                    `;
                },
                className: 'user-actions'
            }

        ];

        // The rows and the footer as this page words them: who is inactive is dimmed, the count says what is counted,
        // and the page links only show when there is more than one page.
        function userDressTable(response) {
            (response.data || []).forEach((item, index) => {
                $('#userTable tbody tr').eq(index).toggleClass('user-off', !item.active);
            });

            const pager = $('.pagination-container');
            const meta = response.meta || {};
            pager.find('.justify-content-start').toggleClass('invisible', (meta.last_page || 1) <= 1);
            pager.find('.justify-content-end .text-nowrap').first()
                .text(`Showing ${meta.from || 0} - ${meta.to || 0} of ${meta.total || 0} users`);
            pager.find('.pagination-limit').addClass('w-auto').find('option').each(function() {
                $(this).text($(this).val() + ' Rows');
            });
        }

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
                    userDressTable(response);

                    $('.tooltip').remove();
                    $('#userTable [data-bs-toggle="tooltip"]').tooltip();
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
                orderBy = 'name';
                orderDir = 'asc';
                filterList = {
                    position: null,
                    role: null,
                    status: null,
                };
                userRegisteredTable();

                $("#userRegisterdSearch").val('');
                $("#userFilterCard").slideUp(300, function() {
                    // Reset filter inputs here if needed
                    $('#userFilterCard #positionSelectFilter').val(null).trigger('change');
                    $('#userFilterCard #roleSelectFilter').val(null).trigger('change');
                    $('#userFilterCard #statusSelectFilter').val('');
                });
            });
        });
    </script>
    {{-- search: as it is typed (after a pause), and on Enter --}}
    <script>
        $(document).ready(function() {
            const userSearch = () => {
                const typed = $("#userRegisterdSearch").val().trim();
                if (typed === search) {
                    return;
                }
                search = typed;
                currentPage = 1;
                userRegisteredTable();
            };

            $("#userRegisterdSearch").on("input", debounce(userSearch, 400)).on("keydown", function(event) {
                if (event.key === "Enter") {
                    event.preventDefault();
                    userSearch();
                }
            });
        });
    </script>
@endpush
