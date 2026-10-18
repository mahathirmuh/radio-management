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
            padding: .8rem .75rem;
        }

        /* A long name wraps rather than pushing the buttons out of a laptop's screen. */
        #userTable .user-name-cell {
            white-space: normal;
            min-width: 130px;
            max-width: 200px;
        }

        #userTable th:first-child,
        #userTable td:first-child {
            padding-left: 1.25rem;
        }

        #userTable .sort-icon svg {
            width: 14px;
            height: 14px;
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
        #userTable tr.user-off .user-muted {
            opacity: .55;
        }

        /* The box of a row (for the bar of the users ticked) and the number of the row: narrow, so the buttons at the
           right edge stay in view; a long role name wraps rather than widening the table. */
        #userTable .user-pick-cell {
            width: 34px;
            padding-right: .25rem;
        }

        #userTable .user-no {
            width: 40px;
            padding-left: .25rem;
            padding-right: .5rem;
            text-align: center;
        }

        #userTable .user-role {
            white-space: normal;
            min-width: 110px;
            max-width: 170px;
        }

        .user-bulk-bar {
            background: rgba(32, 107, 196, .07);
            border: 1px solid rgba(32, 107, 196, .25);
        }
    </style>
@endpush

@php
    // What the bar of the users ticked offers: Update (activate, deactivate) and Delete on the menu (the server checks again).
    $userBulkMay = [
        'update' => \Illuminate\Support\Facades\Blade::check('permission', 'user-management.user-registered', 'Update'),
        'delete' => \Illuminate\Support\Facades\Blade::check('permission', 'user-management.user-registered', 'Delete'),
    ];
@endphp

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

                        {{-- The users ticked (script: userBulk), and what may be done to them. --}}
                        <div class="col-12 d-none" id="userBulkBar">
                            <div class="user-bulk-bar d-flex flex-wrap align-items-center gap-2 px-3 py-2 rounded-3">
                                <i class="ti ti-checkbox text-primary fs-3"></i>
                                <span class="fw-bold" id="userBulkCount"></span>
                                <a href="#" class="small text-secondary" id="userBulkClear">Clear selection</a>
                                <span class="ms-md-auto d-flex flex-wrap gap-2">
                                    @if ($userBulkMay['update'])
                                        <button type="button" class="btn btn-sm btn-outline-success" id="userBulkActivate">
                                            <i class="ti ti-user-check me-1"></i> Activate
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="userBulkDeactivate">
                                            <i class="ti ti-user-off me-1"></i> Deactivate
                                        </button>
                                    @endif
                                    @if ($userBulkMay['delete'])
                                        <button type="button" class="btn btn-sm btn-outline-danger" id="userBulkDelete">
                                            <i class="ti ti-trash me-1"></i> Delete
                                        </button>
                                    @endif
                                </span>
                            </div>
                        </div>

                        <div class="col-12">

                            <div class="card overflow-hidden mdka-border-gray-200">
                                <div class="card-body table-responsive p-0">
                                    <table class="table table-vcenter mdka-border-gray-100 text-nowrap mb-0" id="userTable">
                                        <thead>
                                            <tr class="mdka-bg-gray-50 ">
                                                @if ($userBulkMay['update'] || $userBulkMay['delete'])
                                                    <th scope="col" class="user-pick-cell"><span class="visually-hidden">Select</span><input
                                                            type="checkbox" class="form-check-input m-0" id="userPickPage"
                                                            aria-label="Select every user on this page"></th>
                                                @endif
                                                <th scope="col" class="text-capitalize fs-5 user-no">No</th>
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

    {{-- The users ticked, and the bar that acts on them (before the table: its first column asks for it). --}}
    <script>
        const userBulk = {
            /** Whether the role may do anything to the users ticked: without it there are no boxes. */
            enabled: @json($userBulkMay['update'] || $userBulkMay['delete']),
            urls: {
                status: "{{ route('user-management.user-registered.bulk-status') }}",
                destroy: "{{ route('user-management.user-registered.bulk-destroy') }}",
            },
            max: {{ \Modules\UserManagement\Http\Controllers\UserRegisteredController::BULK_MAX }},
            /** The users ticked, kept while the page or the filter changes: id => email. */
            picked: new Map(),
            /** The rows on screen that may be ticked: [{ id, email }]. */
            rows: [],

            esc(value) {
                return String(value == null ? '' : value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            },
            noun(count) {
                return count === 1 ? 'user' : 'users';
            },
            /** The box of a row: none to tick on one's own row, or on an account the role does not manage. */
            cell(item) {
                const may = !!item.manageable;
                const on = may && userBulk.picked.has(Number(item.id));
                const why = item.is_self ? 'Your own account' : 'You are not allowed to manage this user';
                return `<input type="checkbox" class="form-check-input m-0 user-pick" aria-label="Select ${userBulk.esc(item.email)}" data-id="${Number(item.id)}" data-email="${userBulk.esc(item.email)}"${on ? ' checked' : ''}${may ? '' : ` disabled title="${why}"`}>`;
            },
            shown(data) {
                userBulk.rows = (data || []).filter(item => item.manageable).map(item => ({ id: Number(item.id), email: String(item.email || '') }));
                userBulk.refresh();
            },
            pick(id, email, on) {
                id = Number(id);
                if (!id) return;
                if (on) {
                    userBulk.picked.set(id, String(email || ''));
                } else {
                    userBulk.picked.delete(id);
                }
                userBulk.refresh();
            },
            pickPage(on) {
                userBulk.rows.forEach(row => (on ? userBulk.picked.set(row.id, row.email) : userBulk.picked.delete(row.id)));
                $('#userTable tbody input.user-pick:not(:disabled)').prop('checked', on);
                userBulk.refresh();
            },
            clear() {
                userBulk.picked.clear();
                $('#userTable tbody input.user-pick').prop('checked', false);
                userBulk.refresh();
            },
            refresh() {
                const count = userBulk.picked.size;
                $('#userBulkBar').toggleClass('d-none', count === 0);
                $('#userBulkCount').text(`${count} ${userBulk.noun(count)} selected`);
                const onPage = userBulk.rows.filter(row => userBulk.picked.has(row.id)).length;
                const whole = userBulk.rows.length > 0 && onPage === userBulk.rows.length;
                $('#userPickPage').prop('checked', whole).prop('indeterminate', !whole && onPage > 0).prop('disabled', userBulk.rows.length === 0);
                const fits = count <= userBulk.max;
                ['#userBulkActivate', '#userBulkDeactivate', '#userBulkDelete'].forEach(button => {
                    $(button).prop('disabled', !fits).attr('title', fits ? '' : `At most ${userBulk.max} users at a time.`);
                });
            },
            ids() {
                return [...userBulk.picked.keys()];
            },

            status(active) {
                const count = userBulk.picked.size;
                Swal.fire({
                    title: `${active ? 'Activate' : 'Deactivate'} ${count} ${userBulk.noun(count)}?`,
                    text: active ? 'They will be able to sign in again.' :
                        'They cannot sign in any more, and a session they have open ends. Everything they did is kept.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: active ? 'Activate' : 'Deactivate',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                }).then(result => {
                    if (!result.isConfirmed) return;
                    $.ajax({
                        url: userBulk.urls.status,
                        type: 'POST',
                        dataType: 'json',
                        data: { _method: 'PUT', ids: userBulk.ids(), active: active ? 1 : 0 },
                    }).done(userBulk.finished).fail(userBulk.failed);
                });
            },
            /** Deleting takes the accounts off the list: the person types how many, as a last look. */
            destroy() {
                const count = userBulk.picked.size;
                Swal.fire({
                    title: `Delete ${count} ${userBulk.noun(count)}?`,
                    html: 'Deleting takes the accounts off this list. Prefer deactivation for someone who has left.<br><br>' +
                        `Type <b>${count}</b> to delete.`,
                    icon: 'warning',
                    input: 'text',
                    inputAttributes: { autocomplete: 'off', inputmode: 'numeric' },
                    showCancelButton: true,
                    confirmButtonColor: '#d63939',
                    confirmButtonText: '<i class="ti ti-trash"></i> Delete',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                }).then(result => {
                    if (!result.isConfirmed) return;
                    if (String(result.value || '').trim() !== String(count)) {
                        Swal.fire('Not deleted', `What was typed is not ${count}.`, 'info');
                        return;
                    }
                    $.ajax({
                        url: userBulk.urls.destroy,
                        type: 'POST',
                        dataType: 'json',
                        data: { _method: 'DELETE', ids: userBulk.ids(), confirm: count },
                    }).done(userBulk.finished).fail(userBulk.failed);
                });
            },
            /** What was done, and to whom not and why (as text); then a fresh list without a choice. */
            finished(response) {
                const skipped = Array.isArray(response.skipped) ? response.skipped : [];
                const items = skipped.slice(0, 10).map(s => `<li>${userBulk.esc(s.user)}: ${userBulk.esc(s.reason)}</li>`).join('');
                const more = skipped.length > 10 ? `<div class="small">and ${skipped.length - 10} more</div>` : '';
                Swal.fire({
                    title: skipped.length ? 'Some were skipped' : 'Done',
                    html: userBulk.esc(response.message) + (items ? `<ul class="text-start small mt-2 mb-0">${items}</ul>` : '') + more,
                    icon: skipped.length ? 'warning' : 'success',
                });
                userBulk.picked.clear();
                userBulk.refresh();
                userRegisteredTable();
            },
            failed(xhr) {
                const r = (xhr && xhr.responseJSON) || {};
                Swal.fire({ icon: 'error', title: 'Error!', text: [].concat(r.errors || r.message || 'The users could not be changed.').join(' ') });
            },
        };

        $(function() {
            $('#userTable').on('change', 'input.user-pick', function() {
                userBulk.pick($(this).data('id'), $(this).data('email'), $(this).is(':checked'));
            });
            $('#userPickPage').on('change', function() {
                userBulk.pickPage($(this).is(':checked'));
            });
            $('#userBulkClear').on('click', e => {
                e.preventDefault();
                userBulk.clear();
            });
            $('#userBulkActivate').on('click', () => userBulk.status(true));
            $('#userBulkDeactivate').on('click', () => userBulk.status(false));
            $('#userBulkDelete').on('click', () => userBulk.destroy());
        });
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

        const columns = [
            // The box that ticks the user for the bar (only when the role may do something to the users ticked).
            ...(userBulk.enabled ? [{ key: 'pick', className: 'user-pick-cell', render: (item) => userBulk.cell(item) }] : []),
            // The number of the row, counted across the pages.
            { key: 'no', className: 'user-no', render: (item, index) => String((currentPage - 1) * limit + index + 1) },
            {
                key: 'name',
                sortable: true,
                className: 'user-name-cell',
                render: (item) => `<span class="user-name">${userEsc(item.name)}</span>` +
                    (item.is_self ? ` <span class="badge bg-blue-lt ms-1">You</span>` : '')
            },
            {
                key: 'email',
                sortable: true,
                render: (item) => `<span class="user-muted">${userEsc(item.email)}</span>`
            },
            {
                key: 'role',
                sortable: false,
                className: 'user-role',
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
                    userBulk.shown(response.data);

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
                    role: null,
                    status: null,
                };
                userRegisteredTable();

                $("#userRegisterdSearch").val('');
                $("#userFilterCard").slideUp(300, function() {
                    // Reset filter inputs here if needed
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
