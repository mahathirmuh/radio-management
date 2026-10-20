{{--
    Stock opname, for a phone (6.5): a page of its own, without the sidebar. Four screens: start a session for a department,
    scan (the camera where the browser can read barcodes, else the Radio ID or SN typed, which a handheld scanner does too:
    K13a), check the radio found, and the summary to send to the register. Every scan is saved at once: a session of the
    application ends after 30 minutes without a request, what was scanned stays.

    The scripts are pushed before the shared parts are printed (this page has no layout to collect them first).
--}}
@include('RadioManagement::partials.scripts')

@push('javascript')
    <script>
        const opnamePage = {
            urls: {
                outlook: @js(route('radio-management.opname.outlook')),
                start: @js(route('radio-management.opname.start')),
                show: @js(route('radio-management.opname.show', ['opname' => '__id__'])),
                lookup: @js(route('radio-management.opname.lookup', ['opname' => '__id__'])),
                scan: @js(route('radio-management.opname.scan', ['opname' => '__id__'])),
                report: @js(route('radio-management.opname.report', ['opname' => '__id__'])),
                submit: @js(route('radio-management.opname.submit', ['opname' => '__id__'])),
            },
            ready: @js($ready),
            /** What the role may do (OpnameController::index): start a session, scan and send, report a problem. */
            may: @js($may),
            overview: @js($overview),
            /** The conditions a radio is found in: value => label. */
            conditions: @js(collect($conditions)->pluck('label', 'value')),

            screen: 'start',
            /** The session as the server gave it (RadioOpnames::state), and the radio being checked (::card). */
            state: null,
            radio: null,
            /** What is chosen for the radio being checked. */
            holder: 'register',
            condition: 'layak',
            /** The session open in the department chosen on the first screen. */
            openId: null,
            asked: 0,
            looking: false,
            /** The camera: its stream, the barcode reader of the browser, the last code read (read again only after a while). */
            camera: { stream: null, detector: null, timer: null, torch: false, last: '', lastAt: 0 },

            esc(value) {
                return RadioUi.esc(value);
            },

            init() {
                $('#opnDepartment, #opnCompany').on('change', () => opnamePage.outlook());
                $('#opnStartButton').on('click', () => opnamePage.start());
                $('#opnSessions').on('click', '[data-session]', function() {
                    opnamePage.resume(Number($(this).data('session')));
                });
                $('#opnFind').on('click', () => opnamePage.lookup($('#opnTyped').val()));
                $('#opnTyped').on('keydown', event => {
                    if (event && event.key === 'Enter') {
                        event.preventDefault();
                        opnamePage.lookup($('#opnTyped').val());
                    }
                });
                $('#opnToSummary').on('click', () => opnamePage.summary());
                $('#opnBack, #opnSummaryBack').on('click', () => opnamePage.toScan());
                $('#opnHome, #opnCamHome').on('click', () => opnamePage.home());
                $('#opnVerify').on('change', 'input[name="opnHolder"]', function() {
                    opnamePage.holder = String($(this).val());
                    $('#opnHolderNameWrap').toggleClass('d-none', opnamePage.holder !== 'other');
                    if (opnamePage.holder === 'other') $('#opnHolderName').trigger('focus');
                });
                $('#opnVerify').on('change', 'input[name="opnCondition"]', function() {
                    opnamePage.condition = String($(this).val());
                });
                $('#opnVerifyButton').on('click', () => opnamePage.save('scan'));
                $('#opnReport').on('click', () => opnamePage.save('report'));
                $('#opnSubmit').on('click', () => opnamePage.submit());
                $('#opnAgain').on('click', () => opnamePage.home());
                $('#opnTorch').on('click', () => opnamePage.torch());
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'hidden') opnamePage.cameraStop();
                    else if (opnamePage.screen === 'scan') opnamePage.cameraStart();
                });

                opnamePage.drawSessions();
                const session = Number(new URLSearchParams(location.search).get('session'));
                if (opnamePage.ready && session > 0) opnamePage.resume(session);
            },

            /** One screen at a time; the camera runs on the screen that scans only. */
            show(screen) {
                opnamePage.screen = screen;
                ['start', 'scan', 'verify', 'summary', 'done'].forEach(name => {
                    $(`#opn${name.charAt(0).toUpperCase()}${name.slice(1)}`).toggleClass('d-none', name !== screen);
                });
                if (screen === 'scan') opnamePage.cameraStart();
                else opnamePage.cameraStop();
                if (typeof window.scrollTo === 'function') window.scrollTo(0, 0);
            },

            /** The session in the address: a reload (or a sign-in after the session of the application ended) goes on with it. */
            remember() {
                const id = opnamePage.state && opnamePage.state.session.status === 'open' ? opnamePage.state.session.id : null;
                history.replaceState(null, '', location.pathname + (id ? `?session=${id}` : ''));
            },

            /** What went wrong, as text. */
            plain(xhr) {
                const body = xhr.responseJSON || {};
                if (Array.isArray(body.errors)) return body.errors.join(' ');
                if (body.errors && typeof body.errors === 'object') return Object.values(body.errors).flat().join(' ');
                if (xhr.status === 403) return 'Anda tidak memiliki izin untuk aksi ini.';
                return body.message || 'Terjadi kesalahan, coba lagi.';
            },

            /** "2 mnt" ago, "baru saja", or the time. */
            ago(at) {
                const time = Date.parse(String(at || '').replace(' ', 'T'));
                if (!time) return '';
                const minutes = Math.round((Date.now() - time) / 60000);
                if (minutes < 1) return 'baru saja';
                return minutes < 60 ? `${minutes} mnt` : String(at).slice(11, 16);
            },

            // ------------------------------------------------------------------------------------------- start

            /** The open sessions (to go on with) and the latest sent, on the first screen. */
            drawSessions() {
                const esc = opnamePage.esc;
                const line = (session, open) => {
                    const share = session.expected > 0 ? Math.min(100, Math.round(session.verified / session.expected * 100)) : 100;
                    const body = `<span class="rm-phone-session-top">${esc(session.place)}<span>${esc(open ? 'Terbuka' : rmPickers.label(session.date))}</span></span>` +
                        `<span class="rm-phone-session-bar"><span class="rm-phone-bar${share < 90 ? ' is-low' : ''}"><span style="width: ${share}%"></span></span>` +
                        `${Number(session.verified)} dari ${Number(session.expected)}</span>`;
                    return open ? `<button type="button" class="rm-phone-session" data-session="${Number(session.id)}">${body}</button>` : `<div class="rm-phone-session">${body}</div>`;
                };
                const open = opnamePage.overview.open || [];
                const recent = opnamePage.overview.recent || [];
                $('#opnSessions').html((open.length ? '<div class="rm-phone-label">Sesi terbuka</div>' + open.map(session => line(session, true)).join('') : '') +
                    (recent.length ? '<div class="rm-phone-label">Sesi terakhir</div>' + recent.map(session => line(session, false)).join('') : ''));
            },

            /** How many radios the department chosen has, its last session, and whether one is open there. */
            outlook() {
                const department = String($('#opnDepartment').val() || '');
                const company = String($('#opnCompany').val() || '');
                const mine = ++opnamePage.asked;
                opnamePage.openId = null;
                if (department === '' || !opnamePage.ready) {
                    $('#opnExpect').html('<span class="rm-phone-muted">Pilih department yang dihitung.</span>');
                    $('#opnStartButton').prop('disabled', true);
                    return;
                }
                const query = { department_id: department };
                if (company !== '') query.company_id = company;
                $.getJSON(opnamePage.urls.outlook, query).done(response => {
                    if (mine !== opnamePage.asked) return;
                    const data = response.data || {};
                    opnamePage.openId = data.open || null;
                    const last = data.last ? `Opname terakhir ${rmPickers.label(data.last.date)}, ${Number(data.last.verified)} terverifikasi` : 'Belum pernah ada opname di sini';
                    $('#opnExpect').html(`<span class="rm-phone-big">${Number(data.expected) || 0}</span>` +
                        `<span class="rm-phone-muted">radio diharapkan di sini<br>${opnamePage.esc(last)}</span>`);
                    $('#opnStartButton').prop('disabled', false)
                        .html(`<i class="ti ti-${opnamePage.openId ? 'player-play' : 'camera'}" aria-hidden="true"></i> ${opnamePage.openId ? 'Lanjutkan sesi' : 'Mulai sesi'}`);
                }).fail(xhr => {
                    if (mine !== opnamePage.asked) return;
                    $('#opnExpect').html(`<span class="rm-phone-muted">${opnamePage.esc(opnamePage.plain(xhr))}</span>`);
                });
            },

            start() {
                const department = String($('#opnDepartment').val() || '');
                if (department === '') return;
                const data = { department_id: department };
                const company = String($('#opnCompany').val() || '');
                if (company !== '') data.company_id = company;
                rmFeedback.busy('#opnStartButton', true);
                $.ajax({ url: opnamePage.urls.start, type: 'POST', dataType: 'json', data })
                    .done(response => opnamePage.enter(response.data))
                    .fail(xhr => rmFeedback.toast(opnamePage.plain(xhr), 'error'))
                    .always(() => rmFeedback.busy('#opnStartButton', false));
            },

            resume(id) {
                if (!(id > 0)) return;
                $.getJSON(opnamePage.urls.show.replace('__id__', id))
                    .done(response => opnamePage.enter(response.data))
                    .fail(xhr => {
                        rmFeedback.toast(xhr.status === 404 ? 'Sesi itu tidak ditemukan.' : opnamePage.plain(xhr), 'error');
                        opnamePage.home();
                    });
            },

            /** Into a session: a sent one shows what it did, an open one is scanned. */
            enter(state) {
                opnamePage.state = state;
                opnamePage.remember();
                if (state.session.status !== 'open') {
                    opnamePage.drawSummary();
                    opnamePage.show('summary');
                    return;
                }
                opnamePage.drawScan();
                opnamePage.show('scan');
            },

            /** Back to the first screen, its numbers read again. */
            home() {
                opnamePage.state = null;
                opnamePage.radio = null;
                opnamePage.remember();
                opnamePage.show('start');
                opnamePage.outlook();
            },

            // -------------------------------------------------------------------------------------------- scan

            drawScan() {
                const esc = opnamePage.esc;
                const state = opnamePage.state;
                const expected = Number(state.expected) || 0;
                const found = Number(state.found) || 0;
                const share = expected > 0 ? Math.min(100, Math.round(found / expected * 100)) : 100;
                $('#opnCamPlace, #opnScanPlace').text(state.session.place);
                $('#opnProgress').html(`<div class="rm-phone-progress-top">${found} dari ${expected} terverifikasi<span>${share}%</span></div>` +
                    `<div class="rm-phone-bar lg"><span style="width: ${share}%"></span></div>`);
                const recent = state.recent || [];
                $('#opnRecent').html(recent.length ? '<div class="rm-phone-label">Baru diverifikasi</div>' + recent.map(item =>
                    `<div class="rm-phone-done"><i class="ti ti-circle-check" aria-hidden="true"></i><div class="rm-phone-done-text">` +
                    `<div class="rm-phone-done-id">${esc(item.radio)}</div><div class="rm-phone-done-who">${esc(item.who)}</div></div>` +
                    `<span class="rm-phone-done-at">${esc(opnamePage.ago(item.at))}</span></div>`).join('') : '');
                $('#opnToSummary').text(Number(state.scanned) > 0 ? `Selesai, lihat ringkasan (${Number(state.scanned)} radio)` : 'Lihat ringkasan');
            },

            toScan() {
                opnamePage.radio = null;
                if (!opnamePage.state) return opnamePage.home();
                opnamePage.drawScan();
                opnamePage.show('scan');
            },

            /** The radio of what was typed or read: its Radio ID or SN. */
            lookup(typed) {
                const text = String(typed || '').trim();
                if (text === '' || opnamePage.looking || !opnamePage.state) return;
                opnamePage.looking = true;
                $.getJSON(opnamePage.urls.lookup.replace('__id__', opnamePage.state.session.id), { q: text }).done(response => {
                    $('#opnTyped').val('');
                    opnamePage.radio = response.data;
                    opnamePage.drawVerify();
                    opnamePage.show('verify');
                }).fail(xhr => {
                    rmFeedback.toast(xhr.status === 404 ? `${text}: tidak ada di register.` : opnamePage.plain(xhr), 'error');
                }).always(() => {
                    opnamePage.looking = false;
                });
            },

            cameraStart() {
                const can = typeof window.BarcodeDetector === 'function' && typeof navigator !== 'undefined' && navigator.mediaDevices
                    && typeof navigator.mediaDevices.getUserMedia === 'function';
                $('#opnCam').toggleClass('d-none', !can);
                $('#opnNoCam').toggleClass('d-none', !!can);
                if (!can || opnamePage.camera.stream) return;
                navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false }).then(stream => {
                    if (opnamePage.screen !== 'scan') {
                        stream.getTracks().forEach(track => track.stop());
                        return;
                    }
                    opnamePage.camera.stream = stream;
                    opnamePage.camera.detector = new window.BarcodeDetector();
                    const video = document.getElementById('opnVideo');
                    video.srcObject = stream;
                    video.play();
                    const track = stream.getVideoTracks()[0];
                    const can = track && typeof track.getCapabilities === 'function' ? track.getCapabilities() : {};
                    $('#opnTorch').toggleClass('d-none', !can.torch);
                    opnamePage.cameraLoop();
                }).catch(() => {
                    $('#opnCam').addClass('d-none');
                    $('#opnNoCam').removeClass('d-none');
                    $('#opnNoCamText').text('Kamera tidak bisa dibuka. Ketik Radio ID atau SN di bawah, atau pakai scanner genggam.');
                });
            },

            /** Reads the picture a few times a second; the same code is not read again for 3 seconds. */
            cameraLoop() {
                clearTimeout(opnamePage.camera.timer);
                opnamePage.camera.timer = setTimeout(() => {
                    const camera = opnamePage.camera;
                    if (!camera.stream || opnamePage.screen !== 'scan') return;
                    camera.detector.detect(document.getElementById('opnVideo')).then(codes => {
                        const value = codes && codes[0] ? String(codes[0].rawValue || '').trim() : '';
                        if (value !== '' && (value !== camera.last || Date.now() - camera.lastAt > 3000)) {
                            camera.last = value;
                            camera.lastAt = Date.now();
                            opnamePage.lookup(value);
                        }
                        opnamePage.cameraLoop();
                    }).catch(() => opnamePage.cameraLoop());
                }, 350);
            },

            cameraStop() {
                clearTimeout(opnamePage.camera.timer);
                if (opnamePage.camera.stream) {
                    opnamePage.camera.stream.getTracks().forEach(track => track.stop());
                }
                opnamePage.camera.stream = null;
                opnamePage.camera.torch = false;
                $('#opnTorch').removeClass('is-on');
            },

            torch() {
                const stream = opnamePage.camera.stream;
                const track = stream ? stream.getVideoTracks()[0] : null;
                if (!track) return;
                opnamePage.camera.torch = !opnamePage.camera.torch;
                track.applyConstraints({ advanced: [{ torch: opnamePage.camera.torch }] }).catch(() => {});
                $('#opnTorch').toggleClass('is-on', opnamePage.camera.torch);
            },

            // ------------------------------------------------------------------------------------------ verify

            drawVerify() {
                const esc = opnamePage.esc;
                const radio = opnamePage.radio;
                const done = radio.scanned || null;
                $('#opnRadioTitle').text(`Radio ${radio.dmr_id || radio.sn || ''}`.trim());
                $('#opnRadioSub').text([radio.mdc ? `MDC ${radio.mdc}` : '', radio.sn ? `SN ${radio.sn}` : ''].filter(Boolean).join(' · '));
                const [badge, tone] = radio.archived ? ['Diarsipkan', 'gray'] : (done ? ['Sudah discan', 'blue'] : (radio.here ? ['Ditemukan', 'green'] : ['Department lain', 'orange']));
                $('#opnRadioBadge').html(`<span class="rm-badge ${tone}">${esc(badge)}</span>`);
                $('#opnRadioFacts').html(
                    `<div><dt>Tipe</dt><dd>${RadioUi.dash(radio.type)}</dd></div>` +
                    `<div><dt>Di register</dt><dd><b>${esc([radio.user, radio.department].filter(Boolean).join(', ') || '—')}</b></dd></div>` +
                    `<div><dt>Lokasi</dt><dd>${RadioUi.dash(radio.placement)}</dd></div>` +
                    `<div><dt>Verifikasi terakhir</dt><dd>${esc(radio.last_verified ? rmPickers.label(radio.last_verified) : 'Belum pernah')}</dd></div>`);
                $('#opnArchived').toggleClass('d-none', !radio.archived);

                opnamePage.holder = done ? done.holder : 'register';
                opnamePage.condition = done ? done.condition : (Object.prototype.hasOwnProperty.call(opnamePage.conditions, radio.condition) ? radio.condition : 'layak');
                $('#opnHolderUser').text(radio.user || 'Sesuai register');
                $('#opnHolderRegister').prop('checked', opnamePage.holder === 'register');
                $('#opnHolderOther').prop('checked', opnamePage.holder === 'other');
                $('#opnHolderNone').prop('checked', opnamePage.holder === 'none');
                $('#opnHolderName').val(done && done.holder_name ? done.holder_name : '');
                $('#opnHolderNameWrap').toggleClass('d-none', opnamePage.holder !== 'other');
                Object.keys(opnamePage.conditions).forEach(value => $(`#opnCond-${value}`).prop('checked', value === opnamePage.condition));
                $('#opnNote').val(done && done.note ? done.note : '');
                $('#opnVerifyButton').prop('disabled', radio.archived || !opnamePage.may.scan);
                $('#opnReport').toggleClass('d-none', radio.archived || !opnamePage.may.report || !!(done && done.ticket));
                $('#opnTicket').toggleClass('d-none', !(done && done.ticket)).text(done && done.ticket ? `Tiket servis ${done.ticket} sudah dibuat.` : '');
            },

            /** Saves what was found of the radio ("scan"), or that and a service ticket ("report"); then back to scanning. */
            save(kind) {
                const radio = opnamePage.radio;
                if (!radio || !opnamePage.state) return;
                const name = String($('#opnHolderName').val() || '').trim();
                const note = String($('#opnNote').val() || '').trim();
                if (opnamePage.holder === 'other' && name === '') {
                    rmFeedback.toast('Tulis nama yang memegang radionya.', 'error');
                    $('#opnHolderName').trigger('focus');
                    return;
                }
                if (kind === 'report' && note === '') {
                    rmFeedback.toast('Tulis masalahnya di catatan dulu.', 'error');
                    $('#opnNote').trigger('focus');
                    return;
                }
                const button = kind === 'report' ? '#opnReport' : '#opnVerifyButton';
                rmFeedback.busy(button, true);
                $.ajax({
                    url: opnamePage.urls[kind].replace('__id__', opnamePage.state.session.id),
                    type: 'POST',
                    dataType: 'json',
                    data: { _method: 'PUT', radio_id: radio.id, holder: opnamePage.holder, holder_name: opnamePage.holder === 'other' ? name : '', condition: opnamePage.condition, note },
                }).done(response => {
                    rmFeedback.toast(response.message);
                    opnamePage.state = response.data;
                    opnamePage.toScan();
                }).fail(xhr => {
                    rmFeedback.toast(opnamePage.plain(xhr), 'error');
                }).always(() => rmFeedback.busy(button, false));
            },

            // ----------------------------------------------------------------------------------------- summary

            summary() {
                if (!opnamePage.state) return;
                $.getJSON(opnamePage.urls.show.replace('__id__', opnamePage.state.session.id)).done(response => {
                    opnamePage.state = response.data;
                    opnamePage.drawSummary();
                    opnamePage.show('summary');
                }).fail(xhr => rmFeedback.toast(opnamePage.plain(xhr), 'error'));
            },

            drawSummary() {
                const esc = opnamePage.esc;
                const state = opnamePage.state;
                const open = state.session.status === 'open';
                $('#opnSummaryKicker').text(`${state.session.place} · ${rmPickers.label(state.session.date)}`);
                const sums = [
                    [open ? state.scanned : (state.summary ? state.summary.verified : state.found), 'Terverifikasi', 'green'],
                    [open ? state.not_found.length : (state.summary ? state.summary.not_found : 0), 'Tidak ditemukan', 'red'],
                    [open ? state.other_user.length : (state.summary ? state.summary.other_user : 0), 'Pemegang lain', 'orange'],
                ];
                $('#opnSums').html(sums.map(([value, label, tone]) => `<div class="rm-phone-sum"><div class="rm-phone-sum-value is-${tone}">${Number(value) || 0}</div>` +
                    `<div class="rm-phone-sum-label">${esc(label)}</div></div>`).join(''));
                const flag = (radio, badge, tone, text) => `<div class="rm-phone-flag"><div class="rm-phone-flag-top">${esc(radio)}<span class="rm-badge ${tone}">${esc(badge)}</span></div>` +
                    `<div class="rm-phone-flag-text">${esc(text)}</div></div>`;
                const flags = [].concat(
                    (state.not_found || []).map(one => flag(one.radio, 'Tidak ditemukan', 'red',
                        `${one.who || 'Tanpa user'}. Terakhir diverifikasi ${one.last_verified ? rmPickers.label(one.last_verified) : 'belum pernah'}. Tandai hilang dan isi No. BA di Register Radio.`)),
                    (state.other_user || []).map(one => flag(one.radio, 'Pemegang lain', 'orange',
                        `Register: ${one.register || '—'}. Dipegang ${one.holder}. Perbarui user atau serah terima ulang.`)),
                    (state.elsewhere || []).map(one => flag(one.radio, 'Department lain', 'blue',
                        `Terdaftar di ${one.department || 'department lain'}. Tetap diverifikasi.`)));
                $('#opnFlagsLabel').toggleClass('d-none', !open);
                $('#opnFlags').html(!open ? '<div class="rm-phone-muted">Sesi ini sudah dikirim ke register.</div>' :
                    (flags.length ? flags.join('') : '<div class="rm-phone-muted">Tidak ada yang perlu diputuskan.</div>'));
                $('#opnSubmit').toggleClass('d-none', !open || !opnamePage.may.scan).prop('disabled', !(Number(state.scanned) > 0));
                $('#opnSummaryBack').toggleClass('d-none', !open);
            },

            submit() {
                const state = opnamePage.state;
                if (!state || !(Number(state.scanned) > 0)) return;
                rmDialog.confirm({
                    tone: 'blue',
                    icon: 'send',
                    title: 'Kirim ke register?',
                    text: `${Number(state.scanned)} radio diverifikasi hari ini. Sesi ini tidak bisa diubah lagi sesudahnya.`,
                    confirmText: 'Kirim',
                    loader: true,
                    layout: 'center',
                    preConfirm: () => $.ajax({ url: opnamePage.urls.submit.replace('__id__', state.session.id), type: 'POST', dataType: 'json', data: { _method: 'PUT' } })
                        .then(response => ({ ok: true, response }), xhr => ({ ok: false, xhr })),
                }).then(result => {
                    if (!result.isConfirmed) return;
                    if (!result.value.ok) {
                        Swal.fire('Tidak terkirim', opnamePage.esc(opnamePage.plain(result.value.xhr)), 'error');
                        return;
                    }
                    opnamePage.state = result.value.response.data;
                    opnamePage.remember();
                    const skipped = (opnamePage.state.summary && opnamePage.state.summary.skipped) || [];
                    $('#opnDoneText').text(result.value.response.message);
                    $('#opnSkipped').html(skipped.map(one => `<div class="rm-phone-flag-text">${opnamePage.esc(one.radio)}: ${opnamePage.esc(one.reason)}</div>`).join(''));
                    opnamePage.show('done');
                });
            },
        };

        $(opnamePage.init);
    </script>
@endpush
<!DOCTYPE html>
<html lang="id">
@include('main.components.license.license')

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1, viewport-fit=cover" name="viewport">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Stock Opname | {{ config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/branding/favicon.ico') }}" />
    @include('main.components.style.style')
    <meta name="theme-color" content="#ffffff">
</head>

<body class="rm-phone-page">
    <main class="rm-phone" id="opname">
        {{-- 1. Start: the department, how many radios are expected there, the sessions. --}}
        <section class="rm-phone-screen" id="opnStart" aria-labelledby="opnStartTitle">
            <header class="rm-phone-head">
                <div class="rm-phone-titles">
                    <div class="rm-phone-kicker">Radio Management</div>
                    <h1 class="rm-phone-title" id="opnStartTitle">Stock opname</h1>
                </div>
                <a class="rm-phone-icon" href="{{ route('dashboard.index') }}" aria-label="Kembali ke aplikasi" title="Kembali ke aplikasi"><i class="ti ti-x" aria-hidden="true"></i></a>
            </header>
            <div class="rm-phone-main">
                @unless ($ready)
                    <div class="rm-alert warning" id="opnNotReady">
                        <i class="ti ti-database-off" aria-hidden="true"></i>
                        <span>{{ \Modules\RadioManagement\Http\Controllers\OpnameController::NOT_READY }}</span>
                    </div>
                @endunless
                <div class="rm-field">
                    <label for="opnDepartment" class="form-label required">Department</label>
                    <select id="opnDepartment" class="form-select">
                        <option value="">Pilih department</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="rm-field">
                    <label for="opnCompany" class="form-label">Company</label>
                    <select id="opnCompany" class="form-select">
                        <option value="">Semua company</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->code ?: $company->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="rm-phone-card rm-phone-expect" id="opnExpect" aria-live="polite">
                    <span class="rm-phone-muted">Pilih department yang dihitung.</span>
                </div>
                @if ($may['start'])
                    <button type="button" class="rm-phone-btn" id="opnStartButton" disabled><i class="ti ti-camera" aria-hidden="true"></i> Mulai sesi</button>
                @endif
                <div class="d-flex flex-column gap-2" id="opnSessions"></div>
            </div>
        </section>

        {{-- 2. Scan: the camera (where the browser reads barcodes), the count so far, the Radio ID or SN typed. --}}
        <section class="rm-phone-screen d-none" id="opnScan" aria-label="Pindai radio">
            <div class="rm-phone-cam d-none" id="opnCam">
                <video id="opnVideo" playsinline muted></video>
                <div class="rm-phone-cam-place"><button type="button" class="rm-phone-cam-back" id="opnCamHome" aria-label="Kembali ke awal"><i class="ti ti-arrow-left" aria-hidden="true"></i></button><span id="opnCamPlace"></span></div>
                <button type="button" class="rm-phone-torch d-none" id="opnTorch" aria-label="Senter"><i class="ti ti-sun" aria-hidden="true"></i></button>
                <div class="rm-phone-frame" aria-hidden="true"><span class="tl"></span><span class="tr"></span><span class="bl"></span><span class="br"></span><span class="line"></span></div>
                <div class="rm-phone-cam-hint">Arahkan ke barcode SN di radio</div>
            </div>
            <header class="rm-phone-head" id="opnNoCam">
                <button type="button" class="rm-phone-icon" id="opnHome" aria-label="Kembali ke awal"><i class="ti ti-arrow-left" aria-hidden="true"></i></button>
                <div class="rm-phone-titles">
                    <div class="rm-phone-kicker" id="opnNoCamText">Ketik Radio ID atau SN, atau pakai scanner genggam.</div>
                    <h1 class="rm-phone-title sm" id="opnScanPlace"></h1>
                </div>
            </header>
            <div class="rm-phone-main">
                <div class="rm-phone-card" id="opnProgress" aria-live="polite"></div>
                <div class="rm-phone-find">
                    <div class="rm-field">
                        <label for="opnTyped" class="form-label">Atau ketik Radio ID atau SN</label>
                        <input type="text" id="opnTyped" class="form-control radio-mono" maxlength="60" autocomplete="off" autocapitalize="characters"
                            inputmode="text" placeholder="mis. 1042">
                    </div>
                    <button type="button" class="btn-rm primary" id="opnFind">Cari</button>
                </div>
                <div class="d-flex flex-column gap-2" id="opnRecent" aria-live="polite"></div>
            </div>
            <div class="rm-phone-foot">
                <button type="button" class="rm-phone-link" id="opnToSummary">Lihat ringkasan</button>
            </div>
        </section>

        {{-- 3. The radio found: what the register says, who has it, its condition, a note. --}}
        <section class="rm-phone-screen d-none" id="opnVerify" aria-labelledby="opnRadioTitle">
            <header class="rm-phone-head">
                <button type="button" class="rm-phone-icon" id="opnBack" aria-label="Kembali memindai"><i class="ti ti-arrow-left" aria-hidden="true"></i></button>
                <div class="rm-phone-titles">
                    <h1 class="rm-phone-title sm" id="opnRadioTitle"></h1>
                    <div class="rm-phone-sub" id="opnRadioSub"></div>
                </div>
                <span id="opnRadioBadge"></span>
            </header>
            <div class="rm-phone-main">
                <div class="rm-alert warning d-none" id="opnArchived">
                    <i class="ti ti-archive" aria-hidden="true"></i>
                    <span>Radio ini sudah diarsipkan: tidak bisa diverifikasi. Pulihkan dulu di Register Radio.</span>
                </div>
                <dl class="rm-phone-card rm-phone-facts mb-0" id="opnRadioFacts"></dl>
                <div>
                    <div class="rm-phone-question" id="opnHolderQuestion">Siapa yang memegang sekarang?</div>
                    <div class="rm-seg rm-seg-full" role="radiogroup" aria-labelledby="opnHolderQuestion">
                        <input type="radio" class="btn-check" name="opnHolder" id="opnHolderRegister" value="register" checked>
                        <label for="opnHolderRegister" id="opnHolderUser">Sesuai register</label>
                        <input type="radio" class="btn-check" name="opnHolder" id="opnHolderOther" value="other">
                        <label for="opnHolderOther">Orang lain</label>
                        <input type="radio" class="btn-check" name="opnHolder" id="opnHolderNone" value="none">
                        <label for="opnHolderNone">Tidak ada</label>
                    </div>
                    <div class="rm-field mt-2 d-none" id="opnHolderNameWrap">
                        <label for="opnHolderName" class="form-label required">Nama yang memegang</label>
                        <input type="text" id="opnHolderName" class="form-control" maxlength="150" autocomplete="off">
                    </div>
                </div>
                <div>
                    <div class="rm-phone-question" id="opnConditionQuestion">Kondisi</div>
                    <div class="rm-phone-conds" role="radiogroup" aria-labelledby="opnConditionQuestion">
                        @foreach ($conditions as $condition)
                            <label class="rm-phone-cond is-{{ $condition['value'] }}">
                                <input type="radio" name="opnCondition" id="opnCond-{{ $condition['value'] }}" value="{{ $condition['value'] }}">
                                <span>{{ $condition['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="rm-field">
                    <label for="opnNote" class="form-label">Catatan</label>
                    <textarea id="opnNote" class="form-control" rows="3" maxlength="500" placeholder="Catatan, mis. antena retak"></textarea>
                </div>
                <div class="rm-phone-muted d-none" id="opnTicket"></div>
            </div>
            <div class="rm-phone-foot">
                <button type="button" class="rm-phone-btn is-success" id="opnVerifyButton"><i class="ti ti-check" aria-hidden="true"></i> Verifikasi radio</button>
                <button type="button" class="rm-phone-link d-none" id="opnReport">Laporkan masalah</button>
            </div>
        </section>

        {{-- 4. The summary: the counts, what needs a decision, and sending it to the register. --}}
        <section class="rm-phone-screen d-none" id="opnSummary" aria-labelledby="opnSummaryTitle">
            <header class="rm-phone-head">
                <div class="rm-phone-titles">
                    <div class="rm-phone-kicker" id="opnSummaryKicker"></div>
                    <h1 class="rm-phone-title" id="opnSummaryTitle">Ringkasan sesi</h1>
                </div>
            </header>
            <div class="rm-phone-main">
                <div class="rm-phone-sums" id="opnSums"></div>
                <div class="rm-phone-label" id="opnFlagsLabel">Perlu keputusan</div>
                <div class="d-flex flex-column gap-2" id="opnFlags"></div>
            </div>
            <div class="rm-phone-foot">
                <button type="button" class="rm-phone-btn" id="opnSubmit">Kirim ke register</button>
                <button type="button" class="rm-phone-link" id="opnSummaryBack">Kembali memindai</button>
            </div>
        </section>

        {{-- Sent. --}}
        <section class="rm-phone-screen d-none" id="opnDone" aria-labelledby="opnDoneTitle">
            <header class="rm-phone-head">
                <div class="rm-phone-titles">
                    <div class="rm-phone-kicker">Stock opname</div>
                    <h1 class="rm-phone-title" id="opnDoneTitle">Terkirim ke register</h1>
                </div>
            </header>
            <div class="rm-phone-main">
                <div class="rm-phone-card">
                    <div class="d-flex align-items-center gap-2"><i class="ti ti-circle-check text-green fs-2" aria-hidden="true"></i><b id="opnDoneText"></b></div>
                    <div class="mt-2" id="opnSkipped"></div>
                </div>
            </div>
            <div class="rm-phone-foot">
                <button type="button" class="rm-phone-btn" id="opnAgain">Selesai</button>
            </div>
        </section>
    </main>

    @include('sweetalert::alert')
    @include('main.components.js.js')
</body>

</html>
