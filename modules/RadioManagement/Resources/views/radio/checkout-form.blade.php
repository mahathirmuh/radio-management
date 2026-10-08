<!doctype html>
{{--
    The "IT Equipment Checkout Form" (MTI-HRM-ICT-FRM-001, Rev.000) of one radio, laid out like the Word form it comes from,
    on one A4 page. A page of its own (no application layout): the register shows it in a pop-up (an iframe) and prints it from there.
    What the register knows is filled in; every filled and empty field can be edited by clicking it before printing. Nothing is saved.
--}}
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IT Equipment Checkout Form - {{ $radio->display_name }}</title>
    <style>
        /* No margin on the page itself (the 12 mm above and below are the body's padding when printed, below): browsers print the
           title and the address of the page into a margin, and a form of the company is not to carry either. */
        @page {
            size: A4;
            margin: 0;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        html,
        body {
            margin: 0;
            background: #e9ecef;
        }

        body {
            font-family: Calibri, Carlito, "Segoe UI", Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.1;
            color: #000;
        }

        .hint {
            width: 160mm;
            margin: 10px auto 0;
            padding: 6px 10px;
            font: 12px/1.4 "Segoe UI", Arial, sans-serif;
            color: #495057;
            background: #fff8e1;
            border: 1px solid #ffe08a;
            border-radius: 4px;
        }

        .sheet {
            width: 160mm;
            margin: 10px auto 24px;
            background: #fff;
            box-shadow: 0 1px 6px rgba(0, 0, 0, .3);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td,
        th {
            padding: 0 3pt;
            vertical-align: top;
            font-weight: normal;
            text-align: left;
            overflow-wrap: anywhere;
        }

        /* ---- the heading: logo, company, form name and number */
        .head td {
            border: .5pt solid #000;
            background: #bd6147;
            color: #fff;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
        }

        .head td.logo {
            background: #fff;
            padding: 2pt 4pt;
        }

        .head td.logo img {
            display: block;
            width: 100%;
            height: auto;
        }

        .head .brand {
            font-size: 10pt;
        }

        .head .title {
            font-size: 13pt;
        }

        .head .number {
            font-size: 9pt;
        }

        .head .rev {
            font-size: 10pt;
        }

        .head .dept {
            font-size: 10pt;
        }

        /* ---- the number ICT writes in */
        .reg td {
            border: 0;
            padding-top: 2pt;
        }

        .reg .label {
            text-align: right;
        }

        .reg small {
            display: block;
            font-size: 8pt;
        }

        /* ---- the body: label : value, twice in a row */
        .grid {
            margin-top: 4pt;
        }

        .grid td,
        .grid th {
            border: .5pt solid #000;
        }

        .grid th.band {
            background: #d9d9d9;
            font-weight: bold;
            text-align: center;
        }

        .grid th.band small {
            display: block;
            font-size: 7pt;
            font-weight: normal;
        }

        /* Label, colon and value are one box: no lines between them. */
        .grid td.l {
            border-right: 0;
        }

        .grid td.m {
            border-left: 0;
            border-right: 0;
            padding-left: 0;
            padding-right: 0;
        }

        .grid td.r {
            border-left: 0;
        }

        .grid td.empty {
            border-left: 0;
            border-right: 0;
        }

        .grid tr.gap td {
            height: 3mm;
            border-left: 0;
            border-right: 0;
            border-bottom: 0;
        }

        .grid tr.gap + tr.gap td {
            border-top: 0;
        }

        .grid td.other {
            padding: 1pt 3pt;
        }

        .grid td.sign {
            height: 7mm;
        }

        .grid td.date {
            height: 5.5mm;
        }

        /* "Date:" and what follows it on one line. */
        .grid td.date .f {
            display: inline-block;
            min-width: 17mm;
        }

        .grid td.small {
            border: 0;
            font-size: 7pt;
            padding: 1pt 0 0;
        }

        .grid td.note {
            border-left: 0;
            border-right: 0;
        }

        /* ---- what is typed in */
        .f {
            display: block;
            min-height: 1.3em;
            outline: 0;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        /* Six ruled lines for the note, as many as the form has room for. */
        .f.ruled {
            min-height: calc(6 * 6.5mm);
            line-height: 6.5mm;
            background-size: 100% 6.5mm;
            background-image: linear-gradient(to bottom, transparent calc(6.5mm - .5pt), #777 calc(6.5mm - .5pt));
        }

        .alt {
            cursor: pointer;
        }

        .alt.struck {
            text-decoration: line-through;
        }

        .foot {
            margin: 4pt 0 0;
            padding: 0;
            text-align: right;
            font-size: 10pt;
            font-weight: bold;
        }

        /* ---- signing on the screen: the box of RESPONSIBLE USER opens a pad, and what is drawn goes into the box as a picture */
        .grid td.sign {
            position: relative;
        }

        .sig-open {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            border: 0;
            background: transparent;
            font: 9px/1.2 "Segoe UI", Arial, sans-serif;
            color: #adb5bd;
            cursor: pointer;
        }

        .signed .sig-open {
            color: transparent;
        }

        .sig-open:hover,
        .sig-open:focus-visible {
            background-color: rgba(255, 224, 138, .35);
            outline: 1px dashed #bd6147;
            outline-offset: -2px;
        }

        .sig-img {
            position: absolute;
            inset: 1mm;
            width: calc(100% - 2mm);
            height: calc(100% - 2mm);
            object-fit: contain;
        }

        .sig-remove {
            position: absolute;
            top: 1px;
            right: 1px;
            z-index: 2;
            width: 18px;
            height: 18px;
            padding: 0;
            border: 1px solid #ced4da;
            border-radius: 50%;
            background: #fff;
            color: #495057;
            font: 13px/1 "Segoe UI", Arial, sans-serif;
            cursor: pointer;
        }

        .sig-remove[hidden] {
            display: none;
        }

        .sig-overlay {
            position: fixed;
            inset: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(33, 37, 41, .55);
            font: 14px/1.4 "Segoe UI", Arial, sans-serif;
        }

        .sig-overlay[hidden] {
            display: none;
        }

        .sig-panel {
            padding: 14px 16px 16px;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 8px 30px rgba(0, 0, 0, .35);
            outline: 0;
        }

        .sig-panel h2 {
            margin: 0;
            font-size: 16px;
        }

        .sig-panel p {
            margin: 2px 0 10px;
            font-size: 12px;
            color: #6c757d;
        }

        .sig-surface {
            position: relative;
            border: 2px solid #ced4da;
            border-radius: 6px;
            background: #fff;
            line-height: 0;
        }

        .sig-surface canvas {
            display: block;
            touch-action: none;
            -webkit-user-select: none;
            user-select: none;
            cursor: crosshair;
        }

        /* The line to sign on is part of the pad's page, never of the picture. */
        .sig-line {
            position: absolute;
            left: 4%;
            right: 4%;
            bottom: 24%;
            border-bottom: 1px dashed #adb5bd;
            pointer-events: none;
        }

        .sig-note {
            margin: 8px 0 0;
            font-size: 12px;
            color: #c92a2a;
        }

        .sig-note[hidden] {
            display: none;
        }

        .sig-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 8px;
            margin-top: 12px;
        }

        .sig-actions button {
            min-height: 40px;
            padding: 0 16px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            background: #fff;
            color: #212529;
            font: inherit;
            cursor: pointer;
        }

        .sig-actions button.primary {
            border-color: #206bc4;
            background: #206bc4;
            color: #fff;
        }

        .sig-actions button:disabled {
            opacity: .45;
            cursor: default;
        }

        .hint[hidden] {
            display: none;
        }

        /* In view whatever the page is scrolled to (the fields that grow are far down), and in no one's way: it takes no room in the page. */
        .hint.warn {
            position: fixed;
            left: 50%;
            bottom: 10px;
            z-index: 30;
            margin: 0;
            max-width: calc(100vw - 20px);
            transform: translateX(-50%);
            background: #fff0f0;
            border-color: #ffc9c9;
            color: #c92a2a;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .25);
        }

        @media screen {
            .f {
                border-bottom: 1px dotted #adb5bd;
            }

            .f:hover {
                background-color: #fffbe6;
            }

            .f:focus {
                background-color: #fff3bf;
                border-bottom-color: #f08c00;
            }

            .f.ruled {
                border-bottom: 0;
            }

            .alt:hover {
                background-color: #fff3bf;
            }

            .sheet {
                padding: 0;
            }
        }

        @media print {

            html,
            body {
                background: #fff;
            }

            /* The 12 mm above and below are the body's padding (see @page); the sheet is 160 mm wide, in the middle, with any margin the dialog leaves. */
            body {
                padding: 12mm 0;
            }

            .hint,
            .no-print {
                display: none !important;
            }

            .sheet {
                width: 160mm;
                margin: 0 auto;
                box-shadow: none;
            }

            tr {
                break-inside: avoid;
            }
        }
    </style>
</head>

<body>
    <div class="hint no-print">Klik sebuah isian untuk mengubahnya sebelum dicetak, dan klik tulisan seperti "HT" atau "RIG" untuk
        mencoretnya (klik lagi untuk membatalkan). Klik kotak tanda tangan RESPONSIBLE USER untuk menandatangani di layar
        (pena, jari, atau mouse). Perubahan hanya untuk cetakan ini dan tidak disimpan.</div>
    <div class="hint warn no-print" id="fitWarning" role="alert" hidden>Isi form lebih panjang dari satu halaman A4: saat dicetak, form
        diperkecil otomatis menjadi <b id="fitScale"></b>% supaya tetap satu halaman. Persingkat isian supaya tetap terbaca.</div>

    <main class="sheet">
        <table class="head">
            <colgroup>
                <col style="width: 23.7%">
                <col style="width: 58%">
                <col style="width: 18.3%">
            </colgroup>
            <tr>
                <td class="logo" rowspan="3"><img src="{{ asset('assets/img/branding/mti-logo.png') }}" alt="Merdeka Tsingshan Indonesia"></td>
                <td class="brand">MERDEKA TSINGSHAN INDONESIA</td>
                <td class="dept" rowspan="2">HR/ICT</td>
            </tr>
            <tr>
                <td class="title">IT EQUIPMENT CHECKOUT FORM</td>
            </tr>
            <tr>
                <td class="number">MTI-HRM-ICT-FRM-001</td>
                <td class="rev">Rev.000</td>
            </tr>
        </table>

        <table class="reg">
            <colgroup>
                <col style="width: 81.2%">
                <col style="width: 18.8%">
            </colgroup>
            <tr>
                <td></td>
                <td class="label">Reg Number:<small>(Filled by ICT)</small></td>
            </tr>
            <tr>
                <td></td>
                <td><span class="f line" data-field="reg_number"></span></td>
            </tr>
        </table>

        <table class="grid">
            <colgroup>
                <col style="width: 18.7%">
                <col style="width: 3.1%">
                <col style="width: 29.8%">
                <col style="width: 19.4%">
                <col style="width: 5.5%">
                <col style="width: 23.5%">
            </colgroup>

            <tr>
                <th class="band" colspan="6">REQUESTOR (filled by ICT)</th>
            </tr>
            <tr>
                <td class="l">Employee Name</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="requestor_name">{{ $radio->user_name }}</span></td>
                <td class="l">Position</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="requestor_position"></span></td>
            </tr>
            <tr>
                <td class="l">Employee ID</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="requestor_id"></span></td>
                <td class="l">Department</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="requestor_department">{{ $radio->department?->name }}</span></td>
            </tr>
            <tr>
                <td class="l">Reg. No. FO-001</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="requestor_reg_no"></span></td>
                <td class="empty" colspan="3"></td>
            </tr>
            <tr class="gap">
                <td colspan="6"></td>
            </tr>
            <tr class="gap">
                <td colspan="6"></td>
            </tr>

            <tr>
                <th class="band" colspan="3">*) <span class="alt">DESKTOP</span> / <span class="alt">LAPTOP</span></th>
                <th class="band" colspan="3">*) <span class="alt @if ($category === 'RIG') struck @endif">HT</span> / <span class="alt @if ($category === 'HT') struck @endif">RIG</span></th>
            </tr>
            <tr>
                <td class="l">Device Name</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="device_name"></span></td>
                <td class="l">No. ID</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="radio_id">{{ $radio->dmr_id }}</span></td>
            </tr>
            <tr>
                <td class="l">Merk &amp; Type</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="device_type"></span></td>
                <td class="l">Merk &amp; Type</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="radio_type">{{ $radio->radioType?->name }}</span></td>
            </tr>
            <tr>
                <td class="l">Processor</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="device_processor"></span></td>
                <td class="l">Serial Number</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="radio_serial">{{ $radio->serial_number }}</span></td>
            </tr>
            <tr>
                <td class="l">Service Tag</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="device_service_tag"></span></td>
                <td class="empty" colspan="3"></td>
            </tr>
            <tr class="gap">
                <td colspan="6"></td>
            </tr>

            <tr>
                <th class="band" colspan="3">*) <span class="alt">MOUSE</span> / <span class="alt">KEYBOARD</span></th>
                <th class="band" colspan="3">HANDPHONE</th>
            </tr>
            <tr>
                <td class="l">Merk</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="peripheral_brand"></span></td>
                <td class="l">Merk</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="phone_brand"></span></td>
            </tr>
            <tr>
                <td class="l">Type</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="peripheral_type"></span></td>
                <td class="l">Type</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="phone_type"></span></td>
            </tr>
            <tr>
                <td class="l">Serial Number</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="peripheral_serial"></span></td>
                <td class="l">Serial Number</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="phone_serial"></span></td>
            </tr>
            <tr class="gap">
                <td colspan="6"></td>
            </tr>

            <tr>
                <th class="band" colspan="3">MONITOR</th>
                <td class="other" colspan="3" rowspan="4"><b>Other:</b><span class="f" data-field="other"></span></td>
            </tr>
            <tr>
                <td class="l">Merk</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="monitor_brand"></span></td>
            </tr>
            <tr>
                <td class="l">Type</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="monitor_type"></span></td>
            </tr>
            <tr>
                <td class="l">Serial Number</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="monitor_serial"></span></td>
            </tr>
            <tr>
                <td class="small" colspan="6">*) Streak one</td>
            </tr>
            <tr class="gap">
                <td colspan="6"></td>
            </tr>

            <tr>
                <td class="note" colspan="6">Note :<span class="f ruled" data-field="note"></span></td>
            </tr>
            <tr class="gap">
                <td colspan="6"></td>
            </tr>

            <tr>
                <th class="band" colspan="5">RESPONSIBLE USER</th>
                <th class="band">PROCESSED BY<small>(ICT Person)</small></th>
            </tr>
            <tr>
                <td class="l">Employee Name</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="user_name">{{ $radio->user_name }}</span></td>
                <td class="sign" id="signCell" colspan="2" rowspan="3" data-today="{{ $today }}">
                    <button type="button" class="sig-open no-print" id="signOpen" aria-label="Tanda tangan Responsible User di layar">Klik untuk tanda tangan</button>
                    <button type="button" class="sig-remove no-print" id="signRemove" aria-label="Hapus tanda tangan" title="Hapus tanda tangan" hidden>&times;</button>
                </td>
                <td class="sign" rowspan="3"></td>
            </tr>
            <tr>
                <td class="l">Employee ID</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="user_id"></span></td>
            </tr>
            <tr>
                <td class="l">Department</td>
                <td class="m">:</td>
                <td class="r"><span class="f line" data-field="user_department">{{ $radio->department?->name }}</span></td>
            </tr>
            <tr>
                <td class="empty" colspan="3"></td>
                <td class="date" colspan="2">Date: <span class="f line" data-field="user_date"></span></td>
                <td class="date">Date: <span class="f line" data-field="ict_date">{{ $today }}</span></td>
            </tr>
        </table>

        <p class="foot">The equipment belongs to the company.<br>All forms of equipment’s loss and damage are user’s responsibility.</p>
    </main>

    @include('RadioManagement::radio.checkout-signature')

    <script>
        (function() {
            // "plaintext-only" where the browser has it (typed and pasted text stays plain), else "true" with a paste handler.
            const probe = document.createElement('span');
            probe.setAttribute('contenteditable', 'plaintext-only');
            const mode = probe.contentEditable === 'plaintext-only' ? 'plaintext-only' : 'true';

            document.querySelectorAll('.f').forEach(field => {
                const oneLine = field.classList.contains('line');
                field.setAttribute('contenteditable', mode);
                field.setAttribute('spellcheck', 'false');
                if (oneLine) {
                    field.addEventListener('keydown', event => {
                        if (event.key === 'Enter') event.preventDefault();
                    });
                }
                field.addEventListener('paste', event => {
                    event.preventDefault();
                    const text = (event.clipboardData || window.clipboardData).getData('text');
                    document.execCommand('insertText', false, oneLine ? text.replace(/\s*[\r\n]+\s*/g, ' ') : text);
                });
            });

            document.querySelectorAll('.alt').forEach(word => {
                word.addEventListener('click', () => word.classList.toggle('struck'));
            });

            // The form is one A4 page. What is typed can make it longer: it is then made smaller for the printing (a second page would have
            // no margin above and below), and the person is told, because the text gets smaller too.
            const sheet = document.querySelector('main.sheet');
            const warning = document.getElementById('fitWarning');
            const scaleOf = document.getElementById('fitScale');
            const page = 273 * 96 / 25.4; // the height of A4 less the 12 mm above and below, in CSS pixels
            /**
             * The zoom (from 0 to 1) that makes the sheet one page long; 1 when it is, already. "zoom" does not scale a sheet exactly (the lines of
             * text are rounded), so a sheet shrunk by the share it is too long comes out a little long: it is measured again and corrected.
             * The sheet is left as it was found (so it may be looked at while the browser has it zoomed for the printing).
             */
            const fitting = () => {
                const was = sheet.style.zoom;
                sheet.style.zoom = '';
                let zoom = 1;
                let height = sheet.getBoundingClientRect().height;
                if (height > page + 1) {
                    zoom = page / height;
                    for (let tries = 0; tries < 6; tries++) {
                        sheet.style.zoom = String(zoom);
                        height = sheet.getBoundingClientRect().height;
                        if (!(height > page + 0.5)) break;
                        zoom *= page / height;
                    }
                }
                sheet.style.zoom = was;

                return zoom;
            };
            const fit = () => {
                const zoom = fitting();
                warning.hidden = zoom === 1;
                if (!warning.hidden) scaleOf.textContent = String(Math.floor(zoom * 100));
            };
            document.addEventListener('input', fit);
            window.addEventListener('load', fit);
            window.addEventListener('resize', fit);
            // A frame that is shown after the form has loaded (the pop-up is not on the screen yet when the form arrives) had no height before.
            if (typeof ResizeObserver === 'function') new ResizeObserver(fit).observe(sheet);
            fit();

            window.addEventListener('beforeprint', () => {
                const zoom = fitting();
                sheet.style.zoom = zoom < 1 ? String(zoom) : '';
            });
            window.addEventListener('afterprint', () => { sheet.style.zoom = ''; });

            // Ctrl+P prints this form, and not what is around it.
            window.addEventListener('keydown', event => {
                if ((event.ctrlKey || event.metaKey) && !event.shiftKey && !event.altKey && String(event.key).toLowerCase() === 'p') {
                    event.preventDefault();
                    // A signature that is still being drawn is not on the form yet: it would be left off the paper.
                    const pad = document.getElementById('signOverlay');
                    const note = document.getElementById('signNote');
                    if (pad && !pad.hidden) {
                        if (note) note.hidden = false;
                        return;
                    }
                    window.print();
                }
            });
        })();
    </script>
</body>

</html>
