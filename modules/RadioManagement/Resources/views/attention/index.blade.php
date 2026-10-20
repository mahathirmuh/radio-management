@extends('main.index')

@include('RadioManagement::partials.scripts')

@section('page-title', 'Perlu Perhatian')

@section('content')
    <div class="rm-head">
        <div>
            <h1 class="rm-title">Perlu Perhatian</h1>
            <p class="rm-sub">Cek otomatis pada register. Betulkan datanya, abaikan dengan alasan, atau tugaskan ke seseorang.</p>
        </div>
    </div>

    <div class="rm-card">
        <div class="rm-toolbar">
            @unless ($ready)
                <div class="rm-alert warning flex-fill" id="attnNotReady">
                    <i class="ti ti-database-off" aria-hidden="true"></i>
                    <span>{{ \Modules\RadioManagement\Http\Controllers\AttentionController::NOT_READY }} Sampai saat itu cek tetap tampil,
                        tanpa penugasan dan tanpa abaikan.</span>
                </div>
            @endunless
            <div class="rm-end">
                <button type="button" class="btn-rm secondary" id="attnRefresh"><i class="ti ti-refresh" aria-hidden="true"></i> Jalankan cek sekarang</button>
            </div>
        </div>

        <div class="rm-inbox-grid">
            <nav class="rm-inbox-checks" aria-label="Cek">
                <div class="rm-inbox-checks-head" id="attnChecksHead">Cek</div>
                <div id="attnChecks"></div>
            </nav>

            <section class="rm-inbox-items" aria-labelledby="attnItemsTitle">
                <div class="rm-inbox-items-head">
                    <span class="rm-inbox-items-title" id="attnItemsTitle"></span>
                    <span id="attnItemsCount"></span>
                    <div class="rm-field rm-inbox-who">
                        <select id="attnWho" class="form-select" aria-label="Ditugaskan ke">
                            <option value="all">Semua</option>
                            <option value="mine">Tugas saya</option>
                            <option value="none">Belum ditugaskan</option>
                        </select>
                    </div>
                </div>
                <div class="rm-inbox-items" id="attnItems" aria-live="polite"></div>
                <button type="button" class="btn-rm secondary sm rm-inbox-more d-none" id="attnMore">Tampilkan lagi</button>
            </section>

            <section class="rm-inbox-detail" id="attnDetail" aria-live="polite">
                <div class="rm-inbox-empty">Pilih item di tengah untuk melihat datanya.</div>
            </section>
        </div>
    </div>
@endsection

@push('javascript')
    <script>
        const attnPage = {
            urls: {
                list: @js(route('radio-management.attention.list')),
                show: @js(route('radio-management.attention.show')),
                assign: @js(route('radio-management.attention.assign')),
                dismiss: @js(route('radio-management.attention.dismiss')),
                fix: @js(route('radio-management.attention.fix')),
            },
            ready: @js($ready),
            /** What the role may do (AttentionController::index): give and set aside items; fix them. */
            may: @js($may),
            /** The checks, in the order of the list: code => {label, color}. */
            checks: @js($checks),
            people: @js($people),
            me: @js(auth()->id()),

            check: null,
            who: 'all',
            page: 1,
            items: [],
            counts: {},
            more: false,
            /** How many open items the check has (all pages). */
            total: 0,
            /** The item open at the right, as the server gave it, and the fix chosen in it. */
            subject: null,
            detail: null,
            choice: null,
            /** The latest requests: an older answer is dropped. */
            asked: 0,
            askedItem: 0,

            esc(value) {
                return RadioUi.esc(value);
            },

            /** The colour of a check's badge and dot: red, orange, yellow or gray. */
            tone(color) {
                return RadioUi.tone(color);
            },

            /** "BK" of "Budi Kurniawan". */
            initials(name) {
                return String(name || '?').trim().split(/\s+/).slice(0, 2).map(word => word.charAt(0).toUpperCase()).join('') || '?';
            },

            init() {
                const params = new URLSearchParams(location.search);
                attnPage.check = Object.prototype.hasOwnProperty.call(attnPage.checks, params.get('check')) ? params.get('check') : Object.keys(attnPage.checks)[0];
                attnPage.subject = params.get('item') || null;
                attnPage.who = ['mine', 'none'].includes(params.get('who')) ? params.get('who') : 'all';
                $('#attnWho').val(attnPage.who);

                $('#attnChecks').on('click', '[data-check]', function() {
                    attnPage.pick(String($(this).data('check')));
                });
                $('#attnItems').on('click', '[data-subject]', function() {
                    attnPage.open(String($(this).data('subject')));
                });
                $('#attnWho').on('change', () => {
                    attnPage.who = String($('#attnWho').val() || 'all');
                    attnPage.load();
                });
                $('#attnMore').on('click', () => attnPage.load(attnPage.page + 1));
                $('#attnRefresh').on('click', () => attnPage.load(1, true));
                $('#attnDetail').on('change', 'input[name="attnFix"]', function() {
                    attnPage.choose(String($(this).val()));
                });
                $('#attnDetail').on('change', '#attnAssign', () => attnPage.assign($('#attnAssign').val()));
                $('#attnDetail').on('click', '#attnApply', () => attnPage.apply());
                $('#attnDetail').on('click', '#attnDismiss', () => attnPage.dismiss());

                attnPage.load();
            },

            /** Another check: its items, the first one open. */
            pick(check) {
                if (!Object.prototype.hasOwnProperty.call(attnPage.checks, check)) return;
                attnPage.check = check;
                attnPage.subject = null;
                attnPage.load();
            },

            /** The counts and a page of the items of the check; `page` > 1 adds to the list. `told`: say it was done. */
            load(page = 1, told = false) {
                const mine = ++attnPage.asked;
                const button = page > 1 ? '#attnMore' : '#attnRefresh';
                rmFeedback.busy(button, true);
                $.getJSON(attnPage.urls.list, { check: attnPage.check, who: attnPage.who, page }).done(response => {
                    if (mine !== attnPage.asked) return;
                    attnPage.page = page;
                    attnPage.counts = response.counts || {};
                    attnPage.items = page > 1 ? attnPage.items.concat(response.data || []) : (response.data || []);
                    attnPage.more = !!response.more;
                    attnPage.total = Number(response.total) || 0;
                    attnPage.drawChecks();
                    attnPage.drawItems();
                    if (page === 1) {
                        const keep = attnPage.items.find(item => item.subject === attnPage.subject);
                        const next = keep || attnPage.items[0];
                        if (next) {
                            attnPage.open(next.subject);
                        } else {
                            attnPage.subject = null;
                            attnPage.detail = null;
                            $('#attnDetail').html('<div class="rm-inbox-empty">Tidak ada item di cek ini.</div>');
                            attnPage.remember();
                        }
                    }
                    if (told) rmFeedback.toast('Cek sudah dijalankan ulang.');
                }).fail(xhr => {
                    if (mine !== attnPage.asked) return;
                    $('#attnItems').html(`<div class="rm-alert danger"><i class="ti ti-alert-triangle" aria-hidden="true"></i><span>${RadioUi.errors(xhr).join('<br>')}</span></div>`);
                }).always(() => {
                    if (mine === attnPage.asked) rmFeedback.busy(button, false);
                });
            },

            drawChecks() {
                const esc = attnPage.esc;
                const total = Object.values(attnPage.counts).reduce((sum, count) => sum + (Number(count) || 0), 0);
                $('#attnChecksHead').text(`Cek · ${rmMotion.number(total)} terbuka`);
                $('#attnChecks').html(Object.entries(attnPage.checks).map(([code, check]) => {
                    const count = Number(attnPage.counts[code]) || 0;
                    const on = code === attnPage.check;
                    return `<button type="button" class="rm-inbox-check${on ? ' is-on' : ''}${count ? '' : ' is-none'}" data-check="${esc(code)}"${on ? ' aria-current="true"' : ''}>` +
                        `<span class="rm-inbox-dot is-${attnPage.tone(check.color)}" aria-hidden="true"></span>` +
                        `<span class="rm-inbox-check-label">${esc(check.label)}</span><span class="rm-inbox-check-count">${rmMotion.number(count)}</span></button>`;
                }).join(''));
            },

            drawItems() {
                const esc = attnPage.esc;
                const check = attnPage.checks[attnPage.check] || { label: '', color: 'secondary' };
                $('#attnItemsTitle').text(check.label);
                $('#attnItemsCount').html(`<span class="rm-badge ${attnPage.tone(check.color)}">${rmMotion.number(attnPage.total || 0)} item</span>`);
                $('#attnItems').html(attnPage.items.length ? attnPage.items.map(item => {
                    const on = item.subject === attnPage.subject;
                    const holder = item.assignee ?
                        `<span class="rm-inbox-av" aria-hidden="true">${esc(attnPage.initials(item.assignee.name))}</span>${esc(item.assignee.name)}` :
                        'Belum ditugaskan';
                    return `<button type="button" class="rm-inbox-item${on ? ' is-on' : ''}" data-subject="${esc(item.subject)}"${on ? ' aria-current="true"' : ''}>` +
                        `<span class="rm-inbox-item-top"><span class="rm-inbox-item-head">${esc(item.head)}</span>` +
                        `<span class="rm-inbox-item-side">${esc(item.side || '')}</span></span>` +
                        `<span class="rm-inbox-item-who">${esc(item.who)}</span>` +
                        `<span class="rm-inbox-item-foot">${holder}</span></button>`;
                }).join('') : `<div class="rm-inbox-none">${attnPage.who === 'all' ? 'Tidak ada item terbuka di cek ini.' : 'Tidak ada item yang cocok.'}</div>`);
                $('#attnMore').toggleClass('d-none', !attnPage.more);
            },

            /** Opens an item at the right. */
            open(subject) {
                attnPage.subject = subject;
                attnPage.drawItems();
                attnPage.remember();
                const mine = ++attnPage.askedItem;
                $.getJSON(attnPage.urls.show, { check: attnPage.check, subject }).done(response => {
                    if (mine !== attnPage.askedItem) return;
                    attnPage.detail = response.data;
                    attnPage.choice = (response.data.options || [])[0] ? response.data.options[0].id : null;
                    attnPage.drawDetail();
                }).fail(xhr => {
                    if (mine !== attnPage.askedItem) return;
                    $('#attnDetail').html(`<div class="rm-inbox-empty">${attnPage.esc(xhr.status === 404 ? 'Item ini sudah tidak ada: datanya sudah dibetulkan atau berubah.' : attnPage.plain(xhr))}</div>`);
                });
            },

            drawDetail() {
                const esc = attnPage.esc;
                const item = attnPage.detail;
                if (!item) return;
                const seen = item.first_seen ? `Terlihat pertama ${rmPickers.label(item.first_seen)}` : 'Hasil cek otomatis register';
                const records = (item.records || []).map(record => `<div class="rm-inbox-record${record.suspect ? ' is-suspect' : ''}">` +
                    `<div class="rm-inbox-record-head">${esc(record.head)}<span class="rm-inbox-record-tag">${esc(record.tag)}</span></div>` +
                    record.rows.map(row => `<div class="rm-inbox-row${row.marked ? ' is-marked' : ''}"><div class="rm-inbox-row-label">${esc(row.label)}</div>` +
                        `<div class="rm-inbox-row-value${row.mono ? ' is-mono' : ''}">${esc(row.value)}</div></div>`).join('') +
                    '</div>').join('');
                const note = item.note ? `<div class="rm-alert rm-inbox-note"><i class="ti ti-info-circle" aria-hidden="true"></i><span>${esc(item.note)}</span></div>` : '';
                const aside = item.dismissed ? `<div class="rm-alert warning rm-inbox-note"><i class="ti ti-eye-off" aria-hidden="true"></i><span>Diabaikan` +
                    `${item.dismissed.by ? ` oleh ${esc(item.dismissed.by)}` : ''}: ${esc(item.dismissed.reason || '')}</span></div>` : '';
                const fixes = (item.options || []).length && attnPage.may.update ?
                    `<div class="rm-inbox-fixes"><div class="rm-inbox-fixes-title">Saran perbaikan</div>${attnPage.drawFixes()}</div>` : '<div class="pb-3"></div>';

                $('#attnDetail').html(`<div class="rm-inbox-detail-head"><div><h2 class="rm-inbox-detail-title">${esc(item.title)}</h2>` +
                    `<div class="rm-inbox-detail-sub">${esc(seen)}</div></div><span class="rm-badge ${attnPage.tone(item.color)}">${esc(item.label)}</span></div>` +
                    note + aside +
                    `<div class="rm-inbox-records${(item.records || []).length > 1 ? '' : ' is-one'}">${records}</div>` +
                    fixes + attnPage.drawFoot());
            },

            /** The fixes, the chosen one marked, with its field. */
            drawFixes() {
                const esc = attnPage.esc;
                return attnPage.detail.options.map((option, index) => {
                    const on = option.id === attnPage.choice;
                    let field = '';
                    if (on && option.field && option.field.kind === 'select') {
                        field = `<div class="rm-field rm-inbox-fix-field"><select class="form-select" id="attnField" aria-label="${esc(option.title)}">` +
                            '<option value="">Pilih…</option>' +
                            option.field.choices.map(choice => `<option value="${esc(choice.value)}">${esc(choice.label)}</option>`).join('') + '</select></div>';
                    } else if (on && option.field) {
                        field = `<div class="rm-field rm-inbox-fix-field"><input type="text" class="form-control${option.field.mono ? ' radio-mono' : ''}" id="attnField"` +
                            ` value="${esc(option.field.value || '')}" placeholder="${esc(option.field.placeholder || '')}" maxlength="${Number(option.field.max) || 100}"` +
                            ` autocomplete="off" aria-label="${esc(option.title)}"></div>`;
                    }
                    // A label: a click anywhere on the card chooses it (the field in it is the field's own).
                    return `<label class="rm-inbox-fix${on ? ' is-on' : ''}"><input type="radio" name="attnFix" id="attnFix${index}" value="${esc(option.id)}"${on ? ' checked' : ''}>` +
                        `<span class="rm-inbox-fix-text"><span class="rm-inbox-fix-title">${esc(option.title)}</span>` +
                        `<span class="rm-inbox-fix-detail">${esc(option.detail || '')}</span>${field}</span></label>`;
                }).join('');
            },

            /** Who has it, and the buttons: for who may change it. */
            drawFoot() {
                const esc = attnPage.esc;
                const item = attnPage.detail;
                if (!attnPage.may.update) {
                    return `<div class="rm-inbox-detail-foot">Ditugaskan ke <span class="rm-inbox-holder">${item.assignee ? esc(item.assignee.name) : 'belum ada'}</span></div>`;
                }
                const current = item.assignee ? item.assignee.id : '';
                const people = attnPage.people.slice();
                if (item.assignee && !people.some(person => person.id === item.assignee.id)) people.push(item.assignee);
                const choices = ['<option value="">Belum ditugaskan</option>'].concat(people.map(person =>
                    `<option value="${Number(person.id)}"${Number(person.id) === Number(current) ? ' selected' : ''}>${esc(person.name)}${Number(person.id) === Number(attnPage.me) ? ' (saya)' : ''}</option>`));
                const disabled = attnPage.ready ? '' : ' disabled';
                const option = (item.options || []).find(one => one.id === attnPage.choice);
                const applyText = option && option.id === 'dismiss' ? 'Abaikan' : 'Terapkan';
                const canApply = (item.options || []).length && (attnPage.may.fix || (option && option.id === 'dismiss'));
                return '<div class="rm-inbox-detail-foot"><label for="attnAssign" class="mb-0">Ditugaskan ke</label>' +
                    `<div class="rm-field rm-inbox-assign"><select class="form-select" id="attnAssign"${disabled}>${choices.join('')}</select></div>` +
                    '<span class="rm-end">' +
                    `<button type="button" class="btn-rm secondary" id="attnDismiss"${disabled}>Abaikan</button>` +
                    `<button type="button" class="btn-rm primary" id="attnApply"${canApply ? '' : ' disabled'}${attnPage.may.fix ? '' : ' title="Memperbaiki data radio butuh izin Update di Register Radio"'}>` +
                    `<i class="ti ti-check" aria-hidden="true"></i> ${applyText}</button></span></div>`;
            },

            choose(id) {
                attnPage.choice = id;
                attnPage.drawDetail();
                $('#attnField').trigger('focus');
            },

            /** The check and the item open, in the address: a link (or a reload) shows them again. */
            remember() {
                const params = new URLSearchParams();
                params.set('check', attnPage.check);
                if (attnPage.subject) params.set('item', attnPage.subject);
                if (attnPage.who !== 'all') params.set('who', attnPage.who);
                history.replaceState(null, '', `${location.pathname}?${params.toString()}`);
            },

            /** What went wrong, as text. */
            plain(xhr) {
                const body = xhr.responseJSON || {};
                if (Array.isArray(body.errors)) return body.errors.join(' ');
                if (body.errors && typeof body.errors === 'object') return Object.values(body.errors).flat().join(' ');
                if (xhr.status === 403) return 'Anda tidak memiliki izin untuk aksi ini.';
                return body.message || 'Terjadi kesalahan, coba lagi.';
            },

            /** The item every change names. */
            target() {
                return { _method: 'PUT', check: attnPage.detail.check, subject: attnPage.detail.subject };
            },

            assign(value) {
                if (!attnPage.detail) return;
                $.ajax({ url: attnPage.urls.assign, type: 'POST', dataType: 'json', data: { ...attnPage.target(), user_id: value ? Number(value) : '' } })
                    .done(response => {
                        rmFeedback.toast(response.message);
                        attnPage.load();
                    })
                    .fail(xhr => {
                        rmFeedback.toast(attnPage.plain(xhr), 'error');
                        attnPage.drawDetail();
                    });
            },

            apply() {
                const item = attnPage.detail;
                if (!item || !attnPage.choice) return;
                if (attnPage.choice === 'dismiss') {
                    attnPage.dismiss();
                    return;
                }
                // (A fix without a field has no #attnField: its value is empty.)
                const value = String($('#attnField').val() ?? '').trim();
                const option = item.options.find(one => one.id === attnPage.choice);
                if (option && option.field && value === '') {
                    rmFeedback.toast(option.field.kind === 'select' ? 'Pilih dulu isiannya.' : 'Isi dulu kolomnya.', 'error');
                    $('#attnField').trigger('focus');
                    return;
                }
                rmFeedback.busy('#attnApply', true);
                $.ajax({ url: attnPage.urls.fix, type: 'POST', dataType: 'json', data: { ...attnPage.target(), option: attnPage.choice, value } })
                    .done(response => {
                        rmFeedback.toast(response.message);
                        attnPage.subject = null;
                        attnPage.load();
                    })
                    .fail(xhr => {
                        rmFeedback.busy('#attnApply', false);
                        Swal.fire('Tidak diterapkan', attnPage.esc(attnPage.plain(xhr)), 'error');
                    });
            },

            dismiss() {
                if (!attnPage.detail) return;
                rmDialog.confirm({
                    tone: 'yellow',
                    icon: 'eye-off',
                    title: 'Abaikan item ini?',
                    text: 'Item ini muncul lagi bila data radionya berubah.',
                    input: 'textarea',
                    inputLabel: 'Alasan',
                    inputAttributes: { maxlength: 500 },
                    inputValidator: value => (String(value || '').trim() === '' ? 'Tulis alasannya.' : undefined),
                    confirmText: 'Abaikan',
                    loader: true,
                    preConfirm: reason => $.ajax({ url: attnPage.urls.dismiss, type: 'POST', dataType: 'json', data: { ...attnPage.target(), reason: String(reason || '').trim() } })
                        .then(response => ({ ok: true, response }), xhr => ({ ok: false, xhr })),
                }).then(result => {
                    if (!result.isConfirmed) return;
                    if (result.value.ok) {
                        rmFeedback.toast(result.value.response.message);
                        attnPage.subject = null;
                        attnPage.load();
                    } else {
                        Swal.fire('Tidak diabaikan', attnPage.esc(attnPage.plain(result.value.xhr)), 'error');
                    }
                });
            },
        };

        $(attnPage.init);
    </script>
@endpush
