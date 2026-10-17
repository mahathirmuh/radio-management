@extends('main.index')
@push('style')
    <style>
        #tokenTable th,
        #tokenTable td,
        #clientTable th,
        #clientTable td {
            padding: .7rem .8rem;
            vertical-align: middle;
        }

        #tokenTable th:first-child,
        #tokenTable td:first-child,
        #clientTable th:first-child,
        #clientTable td:first-child {
            padding-left: 1.25rem;
        }

        #tokenTable th,
        #clientTable th {
            font-weight: 600;
        }

        #tokenTable td,
        #clientTable td {
            font-size: .8125rem;
        }

        .token-muted {
            color: #667382;
        }

        .token-name {
            font-weight: 600;
        }

        #tokenTable tr.token-off td,
        #clientTable tr.token-off td {
            opacity: .55;
        }

        .token-actions {
            text-align: right;
            white-space: nowrap;
        }

        .token-actions .btn {
            width: 26px;
            height: 26px;
            padding: 0 !important;
            margin-left: 2px;
        }

        #tokenSecret {
            font-family: var(--tblr-font-monospace, monospace);
            font-size: .8125rem;
        }

        #tokenExample {
            font-size: .75rem;
            white-space: pre-wrap;
            word-break: break-all;
        }
    </style>
@endpush

@section('page-title', 'API Tokens')

@section('content')
    <div class="page-header d-print-none mb-3">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">API Tokens</h2>
                <div class="text-secondary mt-1">
                    A token lets another application use the register through the API, at
                    <code>{{ $apiBase }}</code>. It acts as its owner, with the owner's role.
                </div>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <a href="{{ $docsUrl }}" target="_blank" rel="noopener" class="btn btn-outline-primary">
                        <i class="ti ti-book me-1"></i>API documentation
                    </a>
                    <button type="button" class="btn btn-primary" id="tokenAddButton" @disabled(! $ready)>
                        <i class="ti ti-plus me-1"></i>New token
                    </button>
                </div>
            </div>
        </div>
    </div>

    @unless ($ready)
        <div class="alert alert-warning" role="alert" id="tokensNotReady">
            The API is not switched on yet: its migrations have not been run on this database
            (<code>php artisan migrate</code>). Until then no token can be made, and the API answers 503.
        </div>
    @endunless

    <div class="card mb-3">
        <div class="card-header">
            <div>
                <h3 class="card-title">Tokens</h3>
                <div class="text-secondary small mt-1">
                    @if ($superAdmin)
                        Every token: those of the API clients below, and the personal tokens of people.
                    @else
                        Your own tokens. They act as you, with your role, and stop when your account is set to inactive.
                    @endif
                    A token is shown once, when it is made; only its hash is kept.
                </div>
            </div>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-vcenter card-table mb-0" id="tokenTable">
                <thead>
                    <tr class="mdka-bg-gray-50">
                        <th scope="col" class="fs-5">Name</th>
                        <th scope="col" class="fs-5">Owner</th>
                        <th scope="col" class="fs-5">Access</th>
                        <th scope="col" class="fs-5">Created</th>
                        <th scope="col" class="fs-5">Expires</th>
                        <th scope="col" class="fs-5">Last used</th>
                        <th scope="col" class="fs-5"><span class="visually-hidden">Action</span></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    @if ($superAdmin)
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">API clients</h3>
                    <div class="text-secondary small mt-1">
                        Accounts of applications, not of people: a client holds one role and its tokens, never signs in
                        on the screen, and is not listed under User Management. Setting it to inactive stops all its
                        tokens at once.
                    </div>
                </div>
                <div class="card-actions">
                    <button type="button" class="btn btn-outline-primary" id="clientAddButton" @disabled(! $ready)>
                        <i class="ti ti-plus me-1"></i>New client
                    </button>
                </div>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-vcenter card-table mb-0" id="clientTable">
                    <thead>
                        <tr class="mdka-bg-gray-50">
                            <th scope="col" class="fs-5">Name</th>
                            <th scope="col" class="fs-5">Role</th>
                            <th scope="col" class="fs-5">Status</th>
                            <th scope="col" class="fs-5">Tokens</th>
                            <th scope="col" class="fs-5">Created</th>
                            <th scope="col" class="fs-5"><span class="visually-hidden">Action</span></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- A new token --}}
    <div class="modal fade" data-bs-backdrop="static" id="tokenModal" tabindex="-1" aria-labelledby="tokenModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h3 class="modal-title mb-0" id="tokenModalTitle">New token</h3>
                        <button type="button" class="btn-close position-static m-0" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="tokenForm" autocomplete="off">
                        <div class="mb-3">
                            <label for="tokenName" class="form-label required">Name</label>
                            <input type="text" id="tokenName" class="form-control" maxlength="100" required
                                placeholder="What it is for, e.g. Power BI report">
                        </div>
                        <div class="mb-3 @unless ($superAdmin) d-none @endunless">
                            <label for="tokenOwner" class="form-label">Owner</label>
                            <select id="tokenOwner" class="form-select">
                                <option value="me">Me ({{ auth()->user()->name }})</option>
                            </select>
                            <small class="form-hint">An application gets a token of its API client; a token of yours acts as you.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Access</label>
                            <div class="form-selectgroup">
                                <label class="form-selectgroup-item">
                                    <input type="radio" name="tokenAccess" value="read" class="form-selectgroup-input" checked>
                                    <span class="form-selectgroup-label"><i class="ti ti-eye me-1"></i>Read only</span>
                                </label>
                                <label class="form-selectgroup-item">
                                    <input type="radio" name="tokenAccess" value="read_write" class="form-selectgroup-input">
                                    <span class="form-selectgroup-label"><i class="ti ti-pencil me-1"></i>Read &amp; write</span>
                                </label>
                            </div>
                            <small class="form-hint">Within what the owner's role allows (Role &amp; Permission).</small>
                        </div>
                        <div class="mb-3">
                            <label for="tokenDays" class="form-label">Expires in</label>
                            <select id="tokenDays" class="form-select">
                                @foreach ($lifetimes as $days)
                                    <option value="{{ $days }}" @selected($days === 365)>{{ $days }} days</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="tokenSave">Create token</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- The token, shown once --}}
    <div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="tokenShownModal" tabindex="-1" aria-labelledby="tokenShownTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body p-3">
                    <h3 class="modal-title mb-2" id="tokenShownTitle">Copy the token now</h3>
                    <p class="text-secondary mb-3">
                        This is the only time it is shown: the application keeps only its hash. Give it to the
                        application through a safe channel, never in a chat or an e-mail. If it is lost, revoke it and
                        make a new one.
                    </p>
                    <div class="input-group mb-3">
                        <input type="text" id="tokenSecret" class="form-control" readonly aria-label="Token">
                        <button type="button" class="btn btn-primary" id="tokenCopy"><i class="ti ti-copy me-1"></i>Copy</button>
                    </div>
                    <div class="text-secondary small mb-1">Try it:</div>
                    <pre class="mb-3 p-2" id="tokenExample"></pre>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-primary" id="tokenShownDone">I have copied it</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($superAdmin)
        {{-- An API client --}}
        <div class="modal fade" data-bs-backdrop="static" id="clientModal" tabindex="-1" aria-labelledby="clientModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h3 class="modal-title mb-0" id="clientModalTitle">New API client</h3>
                            <button type="button" class="btn-close position-static m-0" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="clientForm" autocomplete="off">
                            <div class="mb-3">
                                <label for="clientName" class="form-label required">Name</label>
                                <input type="text" id="clientName" class="form-control" maxlength="100" required
                                    placeholder="The application, e.g. Power BI">
                            </div>
                            <div class="mb-3">
                                <label for="clientRole" class="form-label required">Role</label>
                                <select id="clientRole" class="form-select" required>
                                    <option value="">Choose a role…</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                <small class="form-hint">What its tokens may do (Role &amp; Permission). Super Admin is not offered.</small>
                            </div>
                            <div class="mb-3 d-none" id="clientStatusRow">
                                <label for="clientActive" class="form-label">Status</label>
                                <select id="clientActive" class="form-select">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive (its tokens are refused)</option>
                                </select>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary" id="clientSave">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('javascript')
    <script>
        const apiTokens = {
            ready: @json($ready),
            superAdmin: @json($superAdmin),
            apiBase: @json($apiBase),
            urls: {
                list: @json(route('api-tokens.list')),
                storeToken: @json(route('api-tokens.store-token')),
                destroyToken: @json(route('api-tokens.destroy-token', ['token' => '__id__'])),
                storeClient: @json(route('api-tokens.store-client')),
                updateClient: @json(route('api-tokens.update-client', ['client' => '__id__'])),
                destroyClient: @json(route('api-tokens.destroy-client', ['client' => '__id__'])),
            },
            clients: [],
            editing: null,

            esc(value) {
                return String(value ?? '').replace(/[&<>"']/g, c => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                })[c]);
            },

            reason(xhr, fallback) {
                const r = xhr.responseJSON || {};
                return [].concat(r.errors || r.message || fallback).join(' ');
            },

            load() {
                const empty = (table, columns, text) => $(table + ' tbody').html(
                    `<tr><td colspan="${columns}" class="text-center text-secondary py-4">${apiTokens.esc(text)}</td></tr>`);
                if (!apiTokens.ready) {
                    empty('#tokenTable', 7, 'The API is not switched on yet.');
                    empty('#clientTable', 6, 'The API is not switched on yet.');
                    return;
                }
                empty('#tokenTable', 7, 'Loading...');
                $.getJSON(apiTokens.urls.list).done(response => {
                    apiTokens.clients = response.clients || [];
                    apiTokens.renderTokens(response.tokens || []);
                    apiTokens.renderClients();
                }).fail(xhr => empty('#tokenTable', 7, apiTokens.reason(xhr, 'The tokens could not be loaded.')));
            },

            renderTokens(tokens) {
                const esc = apiTokens.esc;
                if (!tokens.length) {
                    $('#tokenTable tbody').html('<tr><td colspan="7" class="text-center text-secondary py-4">No tokens yet.</td></tr>');
                    return;
                }
                $('#tokenTable tbody').html(tokens.map(t => {
                    const owner = t.owner;
                    const off = t.expired || !owner || owner.deleted || !owner.active;
                    const ownerHtml = !owner ? '<span class="token-muted">—</span>' :
                        `<div>${esc(owner.name)}${owner.me ? ' <span class="badge bg-blue-lt ms-1">You</span>' : ''}</div>` +
                        `<div class="token-muted">${owner.kind === 'client' ? 'API client' : esc(owner.email)}` +
                        `${owner.deleted ? ' · deleted' : (!owner.active ? ' · inactive' : '')}</div>`;
                    const access = t.writes ?
                        '<span class="badge bg-orange-lt">Read &amp; write</span>' :
                        '<span class="badge bg-green-lt">Read only</span>';
                    const expires = t.expired ? `<span class="text-danger">${esc(t.expires_at)} (expired)</span>` : esc(t.expires_at || '—');
                    const used = t.last_used_at ?
                        `<div>${esc(t.last_used_at)}</div><div class="token-muted">${esc(t.last_used_ip || '')}</div>` :
                        '<span class="token-muted">Never</span>';
                    return `<tr class="${off ? 'token-off' : ''}">
                        <td><span class="token-name">${esc(t.name)}</span></td>
                        <td>${ownerHtml}</td>
                        <td>${access}</td>
                        <td class="text-nowrap">${esc(t.created_at)}</td>
                        <td class="text-nowrap">${expires}</td>
                        <td class="text-nowrap">${used}</td>
                        <td class="token-actions">
                            <button type="button" class="btn btn-danger" data-revoke="${t.id}" data-name="${esc(t.name)}"
                                title="Revoke" aria-label="Revoke ${esc(t.name)}"><i class="ti ti-trash"></i></button>
                        </td>
                    </tr>`;
                }).join(''));
            },

            renderClients() {
                const esc = apiTokens.esc;
                const select = $('#tokenOwner');
                select.find('option[value!="me"]').remove();
                apiTokens.clients.filter(c => c.active).forEach(c => select.append(
                    $('<option>').val(c.id).text(c.name + ' (API client)')));
                if (!apiTokens.superAdmin) return;
                if (!apiTokens.clients.length) {
                    $('#clientTable tbody').html('<tr><td colspan="6" class="text-center text-secondary py-4">No API clients yet.</td></tr>');
                    return;
                }
                $('#clientTable tbody').html(apiTokens.clients.map(c => `
                    <tr class="${c.active ? '' : 'token-off'}">
                        <td><span class="token-name">${esc(c.name)}</span></td>
                        <td>${esc(c.role ? c.role.name : '—')}</td>
                        <td>${c.active ? '<span class="badge bg-green-lt">Active</span>' : '<span class="badge bg-secondary-lt">Inactive</span>'}</td>
                        <td>${esc(c.tokens)}</td>
                        <td class="text-nowrap">${esc(c.created_at)}</td>
                        <td class="token-actions">
                            <button type="button" class="btn btn-orange" data-edit-client="${c.id}" title="Edit"
                                aria-label="Edit ${esc(c.name)}"><i class="ti ti-edit"></i></button>
                            <button type="button" class="btn btn-danger" data-delete-client="${c.id}" data-name="${esc(c.name)}"
                                title="Delete" aria-label="Delete ${esc(c.name)}"><i class="ti ti-trash"></i></button>
                        </td>
                    </tr>`).join(''));
            },

            createToken(event) {
                event.preventDefault();
                const name = $('#tokenName').val().trim();
                if (!name) {
                    Swal.fire({ icon: 'error', title: 'Error!', text: 'The name of the token is empty.' });
                    return;
                }
                $('#tokenSave').prop('disabled', true);
                $.ajax({
                    url: apiTokens.urls.storeToken,
                    type: 'POST',
                    data: {
                        name: name,
                        owner: $('#tokenOwner').val() || 'me',
                        access: $('input[name="tokenAccess"]:checked').val(),
                        days: $('#tokenDays').val(),
                    },
                }).done(response => {
                    $('#tokenModal').modal('hide');
                    $('#tokenSecret').val(response.token);
                    $('#tokenExample').text(`curl -H "Authorization: Bearer ${response.token}" ${apiTokens.apiBase}/me`);
                    $('#tokenShownModal').modal('show');
                    apiTokens.load();
                }).fail(xhr => Swal.fire({ icon: 'error', title: 'Error!', text: apiTokens.reason(xhr, 'The token could not be made.') }))
                    .always(() => $('#tokenSave').prop('disabled', false));
            },

            revoke(id, name) {
                Swal.fire({
                    title: 'Revoke this token?',
                    text: `"${name}" stops working at once. An application that uses it gets 401.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '<i class="ti ti-trash"></i> Revoke',
                    cancelButtonText: '<i class="ti ti-x"></i> Cancel',
                    reverseButtons: true,
                }).then(result => {
                    if (!result.isConfirmed) return;
                    $.ajax({ url: apiTokens.urls.destroyToken.replace('__id__', id), type: 'DELETE' })
                        .done(() => {
                            Swal.fire('Revoked!', 'The token no longer works.', 'success');
                            apiTokens.load();
                        })
                        .fail(xhr => Swal.fire({ icon: 'error', title: 'Error!', text: apiTokens.reason(xhr, 'The token could not be revoked.') }));
                });
            },

            openClient(client) {
                apiTokens.editing = client;
                $('#clientModalTitle').text(client ? 'Edit API client' : 'New API client');
                $('#clientName').val(client ? client.name : '');
                $('#clientRole').val(client && client.role ? String(client.role.id) : '');
                $('#clientActive').val(client && !client.active ? '0' : '1');
                $('#clientStatusRow').toggleClass('d-none', !client);
                $('#clientModal').modal('show');
            },

            saveClient(event) {
                event.preventDefault();
                const client = apiTokens.editing;
                const data = { name: $('#clientName').val().trim(), role_id: $('#clientRole').val() };
                if (client) {
                    data.active = $('#clientActive').val();
                    data._method = 'PUT';
                }
                $('#clientSave').prop('disabled', true);
                $.ajax({
                    url: client ? apiTokens.urls.updateClient.replace('__id__', client.id) : apiTokens.urls.storeClient,
                    type: 'POST',
                    data: data,
                }).done(() => {
                    $('#clientModal').modal('hide');
                    Swal.fire('Saved!', client ? 'The API client has been saved.' : 'The API client has been made. Now make a token for it.', 'success');
                    apiTokens.load();
                }).fail(xhr => Swal.fire({ icon: 'error', title: 'Error!', text: apiTokens.reason(xhr, 'The API client could not be saved.') }))
                    .always(() => $('#clientSave').prop('disabled', false));
            },

            deleteClient(id, name) {
                Swal.fire({
                    title: 'Delete this API client?',
                    text: `"${name}" and all its tokens stop working at once. What it did stays in the history and the Audit Log.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '<i class="ti ti-trash"></i> Delete',
                    cancelButtonText: '<i class="ti ti-x"></i> Cancel',
                    reverseButtons: true,
                }).then(result => {
                    if (!result.isConfirmed) return;
                    $.ajax({ url: apiTokens.urls.destroyClient.replace('__id__', id), type: 'DELETE' })
                        .done(() => {
                            Swal.fire('Deleted!', 'The API client and its tokens are gone.', 'success');
                            apiTokens.load();
                        })
                        .fail(xhr => Swal.fire({ icon: 'error', title: 'Error!', text: apiTokens.reason(xhr, 'The API client could not be deleted.') }));
                });
            },
        };

        $(function() {
            $('#tokenAddButton').on('click', () => {
                $('#tokenForm')[0].reset();
                $('#tokenModal').modal('show');
            });
            $('#tokenForm').on('submit', apiTokens.createToken);
            $('#tokenCopy').on('click', () => {
                const secret = $('#tokenSecret');
                secret.trigger('select');
                (navigator.clipboard ? navigator.clipboard.writeText(secret.val()) : Promise.reject())
                    .catch(() => document.execCommand('copy'))
                    .finally(() => $('#tokenCopy').html('<i class="ti ti-check me-1"></i>Copied'));
            });
            $('#tokenShownDone').on('click', () => {
                // The token leaves the page with the dialog.
                $('#tokenSecret').val('');
                $('#tokenExample').text('');
                $('#tokenCopy').html('<i class="ti ti-copy me-1"></i>Copy');
                $('#tokenShownModal').modal('hide');
            });
            $('#tokenTable').on('click', '[data-revoke]', function() {
                apiTokens.revoke($(this).data('revoke'), String($(this).data('name')));
            });
            $('#clientAddButton').on('click', () => apiTokens.openClient(null));
            $('#clientForm').on('submit', apiTokens.saveClient);
            $('#clientTable').on('click', '[data-edit-client]', function() {
                apiTokens.openClient(apiTokens.clients.find(c => c.id === Number($(this).data('edit-client'))) || null);
            }).on('click', '[data-delete-client]', function() {
                apiTokens.deleteClient($(this).data('delete-client'), String($(this).data('name')));
            });
            apiTokens.load();
        });
    </script>
@endpush
