@extends('main.index')
@push('style')
    <style>
        /* The toolbar: the search, the roles and the status at their widths of the design; the main button at the end. */
        .user-toolbar .rm-search {
            width: 360px;
        }

        .user-toolbar .user-role-pick {
            width: 210px;
            max-width: 100%;
        }

        .user-toolbar .user-status-pick {
            width: 170px;
            max-width: 100%;
        }

        /* The list of users: a long name or a list of roles wraps rather than pushing the buttons out of a laptop's screen. */
        #userTable .user-name-cell {
            min-width: 130px;
            max-width: 220px;
            white-space: normal;
        }

        #userTable td.user-role {
            min-width: 110px;
            max-width: 220px;
            white-space: normal;
        }

        #userTable td.user-no {
            width: 44px;
            color: var(--rm-gray-500);
        }

        #userTable .user-muted {
            color: var(--rm-gray-500);
        }

        #userTable td.user-actions {
            text-align: right;
            white-space: nowrap;
        }

        /* An inactive account: its name and address faded (the badge says why). */
        #userTable tr.user-off .rm-strong,
        #userTable tr.user-off .user-muted {
            opacity: .55;
        }

        .user-note {
            margin: 0;
            color: var(--rm-gray-500);
            font-size: 12px;
            line-height: 16px;
        }
    </style>
@endpush

@php
    // What the bar of the users ticked offers: Update (activate, deactivate) and Delete on the menu (the server checks again).
    $userBulkMay = [
        'update' => \Illuminate\Support\Facades\Blade::check('permission', 'user-management.user-registered', 'Update'),
        'delete' => \Illuminate\Support\Facades\Blade::check('permission', 'user-management.user-registered', 'Delete'),
    ];
    $userMay = $userBulkMay + [
        'create' => \Illuminate\Support\Facades\Blade::check('permission', 'user-management.user-registered', 'Create'),
    ];
@endphp

@section('page-title', 'Users')

@section('content')
    <div class="rm-head">
        <div>
            <h1 class="rm-title">Users</h1>
            <p class="rm-sub">People who can sign in to Radio Management.</p>
        </div>
    </div>

    <div class="rm-card">
        <div class="rm-toolbar user-toolbar">
            <label class="rm-search" for="userRegisterdSearch">
                <i class="ti ti-search" aria-hidden="true"></i>
                <input type="search" name="search" id="userRegisterdSearch" value="" autocomplete="off" placeholder="Name or email"
                    aria-label="Search by name or email" />
            </label>
            @include('UserManagement::user-registered.filter')
            @if ($userMay['create'])
                <button type="button" class="btn-rm primary ms-auto" id="userAddButton"
                    onclick="toggleUserModal('userModal','add', null)"><i class="ti ti-user-plus" aria-hidden="true"></i> Add
                    user</button>
            @endif
        </div>

        {{-- The users ticked (script: userBulk), and what may be done to them. --}}
        <div class="rm-bulk d-none" id="userBulkBar">
            <i class="ti ti-circle-check" aria-hidden="true"></i>
            <span class="rm-bulk-count" id="userBulkCount"></span>
            <a href="#" class="rm-bulk-clear" id="userBulkClear">Clear selection</a>
            <span class="rm-bulk-actions">
                @if ($userBulkMay['update'])
                    <button type="button" class="btn-rm sm success" id="userBulkActivate">Activate</button>
                    <button type="button" class="btn-rm sm secondary" id="userBulkDeactivate">Deactivate</button>
                @endif
                @if ($userBulkMay['delete'])
                    <button type="button" class="btn-rm sm destructive" id="userBulkDelete">Delete</button>
                @endif
            </span>
        </div>

        <div class="rm-box">
            <div class="table-responsive">
                <table class="rm-table" id="userTable">
                    <thead>
                        <tr>
                            @if ($userBulkMay['update'] || $userBulkMay['delete'])
                                <th scope="col" class="rm-pick"><span class="visually-hidden">Select</span><input type="checkbox"
                                        class="form-check-input m-0" id="userPickPage" aria-label="Select every user on this page"></th>
                            @endif
                            <th scope="col" class="user-no">No</th>
                            <th scope="col" data-key="name">Name</th>
                            <th scope="col" data-key="email">Email</th>
                            <th scope="col">Role</th>
                            <th scope="col" data-key="active">Status</th>
                            <th scope="col">Sign-in</th>
                            <th scope="col" data-key="last_login_at">Last sign-in</th>
                            <th scope="col"><span class="visually-hidden">Action</span></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="pagination-container"></div>
        </div>

        <p class="user-note">
            Deactivating ends the account's session on its next request and keeps everything it did. Deleting takes the
            account off this list. Prefer deactivation for someone who has left.
        </p>
    </div>

    @include('UserManagement::user-registered.modal')
@endsection

@push('javascript')
    {{-- URL list --}}
    <script>
        const urlUserRegisteredList = "{{ route('user-management.user-registered.list') }}";
        // The columns of the last sign-in come with a migration: until it has run they are not sorted by.
        const userSignInReady = @json($signInReady);
        // What the buttons of a row may do (the server checks again).
        const userMay = @json($userMay);
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
                rmDialog.confirm({
                    tone: active ? 'green' : 'orange',
                    icon: active ? 'user-check' : 'alert-triangle',
                    title: `${active ? 'Activate' : 'Deactivate'} ${count} ${userBulk.noun(count)}?`,
                    text: active ? 'They will be able to sign in again.' :
                        'They cannot sign in any more, and a session they have open ends. Everything they did is kept.',
                    confirmText: active ? 'Activate' : 'Deactivate',
                    cancelText: 'Cancel',
                    confirmKind: active ? 'success' : 'primary',
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
                rmDialog.confirm({
                    tone: 'red',
                    icon: 'trash',
                    title: `Delete ${count} ${userBulk.noun(count)}?`,
                    html: 'Deleting takes the accounts off this list. Prefer deactivation for someone who has left.<br><br>' +
                        `Type <b>${count}</b> to delete.`,
                    input: 'text',
                    inputAttributes: { autocomplete: 'off', inputmode: 'numeric' },
                    confirmText: '<i class="ti ti-trash"></i> Delete',
                    cancelText: 'Cancel',
                    confirmKind: 'destructive',
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
        const userEsc = (value) => userBulk.esc(value);
        const userBadge = (text, color, title = '') =>
            `<span class="badge bg-${color}-lt"${title ? ` title="${userEsc(title)}"` : ''}>${userEsc(text)}</span>`;
        // How an account gets in (User::signInMethod()). No good/bad scale: only "Pending" is tinted to be seen,
        // because it is the one where somebody may be locked out without knowing it.
        const userSignInColors = {
            LDAP: 'secondary',
            Microsoft: 'blue',
            Both: 'purple',
            Pending: 'yellow',
        };
        const userSignInMeanings = @json($signInMeanings);
        // The roles of the app in their colours; another role is grey.
        const userRoleColors = {
            'Super Admin': 'purple',
            'Admin Radio': 'blue',
            'Viewer Radio': 'secondary',
            'Document Controller': 'orange',
        };

        /** "Y-m-d H:i" as the time since then: "just now", "5 minutes ago", "yesterday", "3 days ago", else the date. */
        const userAgo = (text, now = new Date()) => {
            const match = /^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})$/.exec(String(text || ''));
            if (!match) return String(text || '');
            const then = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), Number(match[4]), Number(match[5]));
            const minutes = Math.floor((now - then) / 60000);
            if (minutes < 1) return 'just now';
            if (minutes < 60) return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
            const hours = Math.floor(minutes / 60);
            if (hours < 24) return `${hours} hour${hours === 1 ? '' : 's'} ago`;
            const days = Math.round((new Date(now.getFullYear(), now.getMonth(), now.getDate()) - new Date(then.getFullYear(), then.getMonth(), then.getDate())) / 86400000);
            if (days <= 1) return 'yesterday';
            if (days < 30) return `${days} days ago`;
            return `${match[3]}-${match[2]}-${match[1]}`;
        };

        /** The buttons of a row, as far as the role may use them. */
        const userActions = (item) => {
            const id = Number(item.id);
            const act = (icon, title, call, danger = false) =>
                `<button type="button" class="rm-act${danger ? ' is-danger' : ''}" data-id="${id}" onclick="${call}" data-bs-toggle="tooltip" data-bs-title="${title}" aria-label="${title}"><i class="ti ti-${icon}"></i></button>`;
            const edit = userMay.update ? act('edit', 'Edit', `toggleUserModal('userModal','edit', ${id})`) : '';
            // Your own row: the edit only. (The server refuses the rest as well.)
            if (item.is_self) {
                return edit;
            }
            const status = !userMay.update ? '' : (item.active ?
                act('user-off', 'Deactivate', `userStatus(${id}, false)`) :
                act('user-check', 'Activate', `userStatus(${id}, true)`));
            // An account that signs in with its directory password has none to set here.
            const password = userMay.update && !item.directory ? act('key', 'Set password', `toggleUserPasswordModal(${id})`) : '';
            const remove = userMay.delete ? act('trash', 'Delete', `userDelete(${id})`, true) : '';
            return `<span class="rm-acts">${edit}${status}${password}${remove}</span>`;
        };

        const columns = [
            // The box that ticks the user for the bar (only when the role may do something to the users ticked).
            ...(userBulk.enabled ? [{ key: 'pick', className: 'rm-pick', render: (item) => userBulk.cell(item) }] : []),
            // The number of the row, counted across the pages.
            { key: 'no', className: 'user-no', render: (item, index) => String((currentPage - 1) * limit + index + 1) },
            {
                key: 'name',
                sortable: true,
                className: 'user-name-cell',
                render: (item) => `<div class="rm-strong user-name">${userEsc(item.name)}</div>` +
                    (item.is_self ? '<div class="rm-line2">You</div>' : '')
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
                render: (item) => {
                    const roles = String(item.role || '').split(', ').filter(Boolean);
                    return roles.length ? `<span class="rm-badges">${roles.map(role => userBadge(role, userRoleColors[role] || 'secondary')).join('')}</span>` :
                        '<span class="rm-none">—</span>';
                }
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
                className: 'text-nowrap',
                // When, in words; the date and time in the tooltip.
                render: (item) => item.last_sign_in ?
                    `<span class="user-muted" title="${userEsc(item.last_sign_in)}">${userEsc(userAgo(item.last_sign_in))}</span>` :
                    '<span class="user-muted">Never</span>'
            },
            {
                key: 'actions',
                className: 'user-actions',
                render: (item) => userActions(item)
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
                ...filterList
            };

            $.ajax({
                url: urlUserRegisteredList,
                type: 'GET',
                data: params,
                dataType: 'json',
                success: response => {
                    $('.tooltip').remove();
                    renderTableWithFeatures({
                        container: '#userTable',
                        data: response.data,
                        columns,
                        currentPage,
                        limit,
                        meta: response.meta,
                        paginationLabels: { noun: 'users' },
                        // Who is inactive is faded.
                        rowAttrs: (item) => item.active ? '' : 'class="user-off"',
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
                    userBulk.shown(response.data);

                    $('#userTable [data-bs-toggle="tooltip"]').tooltip({ trigger: 'hover' });
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
