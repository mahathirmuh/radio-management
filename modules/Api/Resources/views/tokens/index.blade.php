@extends('main.index')
@push('style')
    <style>
        #tokenTable td,
        #clientTable td {
            vertical-align: top;
        }

        #tokenTable td.token-actions,
        #clientTable td.token-actions {
            text-align: right;
            white-space: nowrap;
        }

        #tokenTable .token-expired {
            color: var(--rm-error-600);
        }

        #tokenModal .rm-seg {
            align-self: flex-start;
        }
    </style>
@endpush

@section('page-title', 'API Tokens')

@section('content')
    <div class="rm-head">
        <div>
            <h1 class="rm-title">API Tokens</h1>
            <p class="rm-sub">A token lets another application use the register through the API, at <code>{{ $apiBase }}</code>.
                It acts as its owner, with the owner's role.</p>
        </div>
    </div>

    @unless ($ready)
        <div class="rm-alert warning mb-3" role="alert" id="tokensNotReady">
            <i class="ti ti-alert-triangle" aria-hidden="true"></i>
            <span>The API is not switched on yet: its migrations have not been run on this database
                (<code>php artisan migrate</code>). Until then no token can be made, and the API answers 503.</span>
        </div>
    @endunless

    <div class="rm-card">
        <div class="rm-section-head">
            <div class="min-w-0">
                <h2 class="rm-section-title">Tokens</h2>
                <p class="rm-section-sub">
                    @if ($superAdmin)
                        Every token: those of the API clients below, and the personal tokens of people.
                    @else
                        Your own tokens. They act as you, with your role, and stop when your account is set to inactive.
                    @endif
                    A token is shown once, when it is made; only its hash is kept.
                </p>
            </div>
            <div class="rm-section-actions">
                <a href="{{ $docsUrl }}" target="_blank" rel="noopener" class="btn-rm secondary"><i class="ti ti-file-text"
                        aria-hidden="true"></i> API documentation</a>
                <button type="button" class="btn-rm primary" id="tokenAddButton" @disabled(! $ready)><i class="ti ti-plus"
                        aria-hidden="true"></i> New token</button>
            </div>
        </div>
        <div class="rm-box">
            <div class="table-responsive">
                <table class="rm-table" id="tokenTable">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Owner</th>
                            <th scope="col">Access</th>
                            <th scope="col">Created</th>
                            <th scope="col">Expires</th>
                            <th scope="col">Last used</th>
                            <th scope="col"><span class="visually-hidden">Action</span></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        @if ($superAdmin)
            <div class="rm-section-head pt-2">
                <div class="min-w-0">
                    <h2 class="rm-section-title">API clients</h2>
                    <p class="rm-section-sub">
                        Accounts of applications, not of people: a client holds one role and its tokens, never signs in on
                        the screen, and is not listed under User Management. Setting it to inactive stops all its tokens at
                        once.
                    </p>
                </div>
                <div class="rm-section-actions">
                    <button type="button" class="btn-rm secondary" id="clientAddButton" @disabled(! $ready)><i class="ti ti-plus"
                            aria-hidden="true"></i> New client</button>
                </div>
            </div>
            <div class="rm-box">
                <div class="table-responsive">
                    <table class="rm-table" id="clientTable">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Role</th>
                                <th scope="col">Status</th>
                                <th scope="col">Tokens</th>
                                <th scope="col">Created</th>
                                <th scope="col"><span class="visually-hidden">Action</span></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    {{-- A new token --}}
    <div class="modal fade rm-modal" data-bs-backdrop="static" id="tokenModal" tabindex="-1" aria-labelledby="tokenModalTitle" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" id="tokenForm" autocomplete="off">
                <div class="rm-dialog-head">
                    <span class="rm-dialog-icon"><i class="ti ti-key" aria-hidden="true"></i></span>
                    <div class="rm-dialog-titles">
                        <h3 class="rm-dialog-title modal-title" id="tokenModalTitle">New token</h3>
                    </div>
                    <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="Close"><i
                            class="ti ti-x"></i></button>
                </div>
                <div class="rm-dialog-body is-form">
                    <div class="rm-field">
                        <label for="tokenName" class="form-label required">Name</label>
                        <input type="text" id="tokenName" class="form-control" maxlength="100" required
                            placeholder="What it is for, e.g. Power BI report">
                    </div>
                    <div class="rm-field @unless ($superAdmin) d-none @endunless">
                        <label for="tokenOwner" class="form-label">Owner</label>
                        <select id="tokenOwner" class="form-select">
                            <option value="me">Me ({{ auth()->user()->name }})</option>
                        </select>
                        <div class="rm-hint">An application gets a token of its API client; a token of yours acts as you.</div>
                    </div>
                    <div class="rm-field">
                        <span class="form-label" id="tokenAccessLabel">Access</span>
                        <div class="rm-seg" role="radiogroup" aria-labelledby="tokenAccessLabel">
                            <input type="radio" name="tokenAccess" id="tokenAccessRead" value="read" checked>
                            <label for="tokenAccessRead"><i class="ti ti-eye me-1" aria-hidden="true"></i>Read only</label>
                            <input type="radio" name="tokenAccess" id="tokenAccessWrite" value="read_write">
                            <label for="tokenAccessWrite"><i class="ti ti-pencil me-1" aria-hidden="true"></i>Read &amp; write</label>
                        </div>
                        <div class="rm-hint">Within what the owner's role allows (Role &amp; Permission).</div>
                    </div>
                    <div class="rm-field">
                        <label for="tokenDays" class="form-label">Expires in</label>
                        <select id="tokenDays" class="form-select">
                            @foreach ($lifetimes as $days)
                                <option value="{{ $days }}" @selected($days === 365)>{{ $days }} days</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="rm-dialog-foot">
                    <button type="button" class="btn-rm secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-rm primary" id="tokenSave">Create token</button>
                </div>
            </form>
        </div>
    </div>

    {{-- The token, shown once --}}
    <div class="modal fade rm-modal" data-bs-backdrop="static" data-bs-keyboard="false" id="tokenShownModal" tabindex="-1" aria-labelledby="tokenShownTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="rm-dialog-head">
                    <span class="rm-dialog-icon tone-green"><i class="ti ti-circle-check" aria-hidden="true"></i></span>
                    <div class="rm-dialog-titles">
                        <h3 class="rm-dialog-title modal-title" id="tokenShownTitle">Copy the token now</h3>
                    </div>
                </div>
                <div class="rm-dialog-body is-form">
                    <p class="mb-0">
                        This is the only time it is shown: the application keeps only its hash. Give it to the application
                        through a safe channel, never in a chat or an e-mail. If it is lost, revoke it and make a new one.
                    </p>
                    <div class="rm-copyrow">
                        <input type="text" id="tokenSecret" class="rm-codebox" readonly aria-label="Token">
                        <button type="button" class="btn-rm primary" id="tokenCopy"><i class="ti ti-copy" aria-hidden="true"></i> Copy</button>
                    </div>
                    <div class="rm-hint mb-n2">Try it:</div>
                    <pre class="rm-codebox mb-0" id="tokenExample"></pre>
                </div>
                <div class="rm-dialog-foot">
                    <button type="button" class="btn-rm primary" id="tokenShownDone">I have copied it</button>
                </div>
            </div>
        </div>
    </div>

    @if ($superAdmin)
        {{-- An API client --}}
        <div class="modal fade rm-modal" data-bs-backdrop="static" id="clientModal" tabindex="-1" aria-labelledby="clientModalTitle" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" id="clientForm" autocomplete="off">
                    <div class="rm-dialog-head">
                        <span class="rm-dialog-icon" id="clientModalIcon"><i class="ti ti-plus" aria-hidden="true"></i></span>
                        <div class="rm-dialog-titles">
                            <h3 class="rm-dialog-title modal-title" id="clientModalTitle">New API client</h3>
                        </div>
                        <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="Close"><i
                                class="ti ti-x"></i></button>
                    </div>
                    <div class="rm-dialog-body is-form">
                        <div class="rm-field">
                            <label for="clientName" class="form-label required">Name</label>
                            <input type="text" id="clientName" class="form-control" maxlength="100" required
                                placeholder="The application, e.g. Power BI">
                        </div>
                        <div class="rm-field">
                            <label for="clientRole" class="form-label required">Role</label>
                            <select id="clientRole" class="form-select" required>
                                <option value="">Choose a role…</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                            <div class="rm-hint">What its tokens may do (Role &amp; Permission). Super Admin is not offered.</div>
                        </div>
                        <div class="rm-field d-none" id="clientStatusRow">
                            <label for="clientActive" class="form-label">Status</label>
                            <select id="clientActive" class="form-select">
                                <option value="1">Active</option>
                                <option value="0">Inactive (its tokens are refused)</option>
                            </select>
                        </div>
                    </div>
                    <div class="rm-dialog-foot">
                        <button type="button" class="btn-rm secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-rm primary" id="clientSave">Save</button>
                    </div>
                </form>
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
            /** The first choice of "Owner": the person who makes the token. */
            me: @json('Me (' . auth()->user()->name . ')'),
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

            /** A value and a second line under it ("19-10-2026" / "10:12"), or the value alone. */
            two(first, second = '') {
                const esc = apiTokens.esc;
                return `<div>${esc(first)}</div>${second ? `<div class="rm-line2">${esc(second)}</div>` : ''}`;
            },

            load() {
                const empty = (table, columns, text) => $(table + ' tbody').html(
                    `<tr><td colspan="${columns}" class="text-center rm-empty">${apiTokens.esc(text)}</td></tr>`);
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
                    $('#tokenTable tbody').html('<tr><td colspan="7" class="text-center rm-empty">No tokens yet.</td></tr>');
                    return;
                }
                $('#tokenTable tbody').html(tokens.map(t => {
                    const owner = t.owner;
                    const off = t.expired || !owner || owner.deleted || !owner.active;
                    const ownerHtml = !owner ? '<span class="rm-none">—</span>' :
                        `<div>${esc(owner.name)}${owner.me ? ' <span class="rm-tag ms-1">You</span>' : ''}</div>` +
                        `<div class="rm-line2">${owner.kind === 'client' ? 'API client' : esc(owner.email)}` +
                        `${owner.deleted ? ' · deleted' : (!owner.active ? ' · inactive' : '')}</div>`;
                    const access = t.writes ?
                        '<span class="badge bg-orange-lt">Read &amp; write</span>' :
                        '<span class="badge bg-green-lt">Read only</span>';
                    const [day, time] = String(t.created_at ?? '').split(' ');
                    const expires = t.expired ? `<div>${esc(t.expires_at)}</div><div class="rm-line2 token-expired">expired</div>` : esc(t.expires_at || '—');
                    const used = t.last_used_at ? apiTokens.two(t.last_used_at, t.last_used_ip || '') : '<span class="rm-none">Never</span>';
                    return `<tr${off ? ' class="rm-dim"' : ''}>` +
                        `<td class="rm-strong">${esc(t.name)}</td>` +
                        `<td>${ownerHtml}</td>` +
                        `<td>${access}</td>` +
                        `<td class="text-nowrap">${apiTokens.two(day, time)}</td>` +
                        `<td class="text-nowrap">${expires}</td>` +
                        `<td class="text-nowrap">${used}</td>` +
                        `<td class="token-actions"><button type="button" class="rm-act is-danger" data-revoke="${Number(t.id)}" data-name="${esc(t.name)}"` +
                        ` data-bs-toggle="tooltip" data-bs-title="Revoke" aria-label="Revoke ${esc(t.name)}"><i class="ti ti-trash"></i></button></td>` +
                        `</tr>`;
                }).join(''));
                $('#tokenTable [data-bs-toggle="tooltip"]').tooltip({ trigger: 'hover' });
            },

            renderClients() {
                const esc = apiTokens.esc;
                // An application gets a token of its API client: the active ones are offered as owners.
                $('#tokenOwner').html(`<option value="me">${esc(apiTokens.me)}</option>` + apiTokens.clients.filter(c => c.active)
                    .map(c => `<option value="${Number(c.id)}">${esc(c.name)} (API client)</option>`).join(''));
                if (!apiTokens.superAdmin) return;
                if (!apiTokens.clients.length) {
                    $('#clientTable tbody').html('<tr><td colspan="6" class="text-center rm-empty">No API clients yet.</td></tr>');
                    return;
                }
                $('#clientTable tbody').html(apiTokens.clients.map(c =>
                    `<tr${c.active ? '' : ' class="rm-dim"'}>` +
                    `<td class="rm-strong">${esc(c.name)}</td>` +
                    `<td>${esc(c.role ? c.role.name : '—')}</td>` +
                    `<td>${c.active ? '<span class="badge bg-green-lt">Active</span>' : '<span class="badge bg-secondary-lt">Inactive</span>'}</td>` +
                    `<td>${esc(c.tokens)}</td>` +
                    `<td class="text-nowrap">${esc(c.created_at)}</td>` +
                    `<td class="token-actions"><span class="rm-acts">` +
                    `<button type="button" class="rm-act" data-edit-client="${Number(c.id)}" data-bs-toggle="tooltip" data-bs-title="Edit"` +
                    ` aria-label="Edit ${esc(c.name)}"><i class="ti ti-edit"></i></button>` +
                    `<button type="button" class="rm-act is-danger" data-delete-client="${Number(c.id)}" data-name="${esc(c.name)}"` +
                    ` data-bs-toggle="tooltip" data-bs-title="Delete" aria-label="Delete ${esc(c.name)}"><i class="ti ti-trash"></i></button>` +
                    `</span></td></tr>`).join(''));
                $('#clientTable [data-bs-toggle="tooltip"]').tooltip({ trigger: 'hover' });
            },

            openToken() {
                $('#tokenForm').trigger('reset');
                $('#tokenModal').modal('show');
            },

            createToken(event) {
                event.preventDefault();
                const name = $('#tokenName').val().trim();
                if (!name) {
                    Swal.fire({ icon: 'error', title: 'Error!', text: 'The name of the token is empty.' });
                    return;
                }
                rmFeedback.busy('#tokenSave', true);
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
                    .always(() => rmFeedback.busy('#tokenSave', false));
            },

            /** The token leaves the page with the dialog. */
            forget() {
                $('#tokenSecret').val('');
                $('#tokenExample').text('');
                $('#tokenCopy').html('<i class="ti ti-copy" aria-hidden="true"></i> Copy');
                $('#tokenShownModal').modal('hide');
            },

            revoke(id, name) {
                $('[data-bs-toggle="tooltip"]').tooltip('hide');
                rmDialog.confirm({
                    tone: 'orange',
                    icon: 'alert-triangle',
                    title: 'Revoke this token?',
                    text: `"${name}" stops working at once. An application that uses it gets 401.`,
                    confirmText: 'Revoke',
                    cancelText: 'Cancel',
                    confirmKind: 'destructive',
                }).then(result => {
                    if (!result.isConfirmed) return;
                    $.ajax({ url: apiTokens.urls.destroyToken.replace('__id__', id), type: 'DELETE' })
                        .done(() => {
                            rmFeedback.toast('Revoked! The token no longer works.');
                            apiTokens.load();
                        })
                        .fail(xhr => Swal.fire({ icon: 'error', title: 'Error!', text: apiTokens.reason(xhr, 'The token could not be revoked.') }));
                });
            },

            openClient(client) {
                apiTokens.editing = client;
                $('#clientModalTitle').text(client ? 'Edit API client' : 'New API client');
                $('#clientModalIcon').toggleClass('tone-orange', !!client)
                    .html(`<i class="ti ti-${client ? 'edit' : 'plus'}" aria-hidden="true"></i>`);
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
                rmFeedback.busy('#clientSave', true);
                $.ajax({
                    url: client ? apiTokens.urls.updateClient.replace('__id__', client.id) : apiTokens.urls.storeClient,
                    type: 'POST',
                    data: data,
                }).done(() => {
                    $('#clientModal').modal('hide');
                    rmFeedback.toast(client ? 'The API client has been saved.' : 'The API client has been made. Now make a token for it.');
                    apiTokens.load();
                }).fail(xhr => Swal.fire({ icon: 'error', title: 'Error!', text: apiTokens.reason(xhr, 'The API client could not be saved.') }))
                    .always(() => rmFeedback.busy('#clientSave', false));
            },

            deleteClient(id, name) {
                $('[data-bs-toggle="tooltip"]').tooltip('hide');
                rmDialog.confirm({
                    tone: 'orange',
                    icon: 'alert-triangle',
                    title: 'Delete this API client?',
                    text: `"${name}" and all its tokens stop working at once. What it did stays in the history and the Audit Log.`,
                    confirmText: 'Delete',
                    cancelText: 'Cancel',
                    confirmKind: 'destructive',
                }).then(result => {
                    if (!result.isConfirmed) return;
                    $.ajax({ url: apiTokens.urls.destroyClient.replace('__id__', id), type: 'DELETE' })
                        .done(() => {
                            rmFeedback.toast('Deleted! The API client and its tokens are gone.');
                            apiTokens.load();
                        })
                        .fail(xhr => Swal.fire({ icon: 'error', title: 'Error!', text: apiTokens.reason(xhr, 'The API client could not be deleted.') }));
                });
            },
        };

        $(function() {
            $('#tokenAddButton').on('click', () => apiTokens.openToken());
            $('#tokenForm').on('submit', apiTokens.createToken);
            $('#tokenCopy').on('click', () => {
                const secret = $('#tokenSecret');
                secret.trigger('select');
                (navigator.clipboard ? navigator.clipboard.writeText(secret.val()) : Promise.reject())
                    .catch(() => document.execCommand('copy'))
                    .finally(() => $('#tokenCopy').html('<i class="ti ti-check" aria-hidden="true"></i> Copied'));
            });
            $('#tokenShownDone').on('click', () => apiTokens.forget());
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
