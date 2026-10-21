@extends('main.index')

@include('RadioManagement::partials.scripts')

@section('page-title', 'Import Excel')

@section('content')
    <div class="rm-import-grid">
        <section class="rm-cardbox">
            <header class="rm-cardbox-head">
                <div>
                    <h2 class="rm-cardbox-title">{{ __('RadioManagement::import.title') }}</h2>
                    <div class="rm-cardbox-sub">{!! __('RadioManagement::import.sub') !!}</div>
                </div>
            </header>
            <div class="rm-cardbox-body d-flex flex-column gap-3">
                <ul class="rm-import-rules">
                    <li>{{ __('RadioManagement::import.rules.rows') }}</li>
                    <li>{!! __('RadioManagement::import.rules.tidy') !!}</li>
                    <li>{!! __('RadioManagement::import.rules.conflicts') !!}</li>
                    <li>{{ __('RadioManagement::import.rules.original') }}</li>
                </ul>

                <form id="importForm" class="d-flex flex-column gap-3" onsubmit="return false;">
                    <div class="rm-field">
                        <span class="form-label required">{{ __('RadioManagement::import.file') }}</span>
                        <label class="rm-file" for="importFile">
                            <span class="rm-file-btn"><i class="ti ti-upload" aria-hidden="true"></i> {{ __('RadioManagement::import.pick_file') }}</span>
                            <span class="rm-file-name" id="importFileName">{{ __('RadioManagement::import.page.no_file') }}</span>
                            <input type="file" id="importFile" accept=".xlsx" class="visually-hidden">
                        </label>
                    </div>
                    <label class="rm-check">
                        <input type="checkbox" id="importKonawe" checked>
                        <span>{{ __('RadioManagement::import.konawe') }}</span>
                    </label>
                    {{-- Wiping the register needs Delete on Register Radio (checked again on the server). --}}
                    @permission('radio-management.radio', 'Delete')
                        <label class="rm-check is-danger">
                            <input type="checkbox" id="importFresh" @checked($existing > 0)>
                            <span>{{ __('RadioManagement::import.fresh') }}</span>
                        </label>
                    @endpermission
                    @if ($existing > 0)
                        <div class="rm-alert warning">
                            <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                            <span>{!! trans_choice('RadioManagement::import.existing', $existing, ['count' => number_format($existing)]) !!}
                                @permission('radio-management.radio', 'Delete')
                                @else
                                    {!! __('RadioManagement::import.no_delete') !!}
                                @endpermission
                            </span>
                        </div>
                    @endif
                    <div>
                        <button type="button" class="btn-rm primary" id="importButton" onclick="radioImport.start()">
                            <i class="ti ti-file-import" aria-hidden="true"></i> {{ __('RadioManagement::import.start') }}
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="rm-cardbox">
            <div id="importReport">
                <header class="rm-cardbox-head">
                    <h2 class="rm-cardbox-title">{{ __('RadioManagement::import.page.last') }}</h2>
                </header>
                <div class="rm-cardbox-body radio-muted">{{ __('RadioManagement::import.none') }}</div>
            </div>
        </section>
    </div>
@endsection

@push('javascript')
    <script>
        const radioImport = {
            url: "{{ route('radio-management.import.store') }}",
            radioUrl: "{{ route('radio-management.radio.index') }}",
            detailUrl: "{{ route('radio-management.radio.detail', ':id') }}",
            /** The words of the script in the language of the page (RadioManagement::import.page). */
            words: @json(__('RadioManagement::import.page')),

            start() {
                const words = radioImport.words;
                const file = $('#importFile')[0].files[0];
                if (!file) {
                    showError([words.pick_first]);
                    return;
                }
                const fresh = $('#importFresh').is(':checked');
                // Orange when the register is wiped first (the design's import dialog).
                rmDialog.confirm({
                    tone: fresh ? 'orange' : 'blue',
                    icon: fresh ? 'alert-triangle' : 'file-import',
                    title: words.confirm_title,
                    html: fresh ? words.confirm_fresh : words.confirm_add,
                    confirmText: words.confirm,
                    confirmKind: 'primary',
                }).then(result => {
                    if (!result.isConfirmed) return;
                    const data = new FormData();
                    data.append('file', file);
                    data.append('fresh', fresh ? 1 : 0);
                    data.append('konawe', $('#importKonawe').is(':checked') ? 1 : 0);

                    Swal.fire({
                        title: words.importing,
                        text: words.wait,
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading(),
                    });
                    $.ajax({
                        url: radioImport.url,
                        type: 'POST',
                        data,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                    }).done(response => {
                        Swal.close();
                        rmFeedback.toast(RadioUi.choice(words.done, response.report.imported, { flagged: response.report.flagged }));
                        radioImport.render(response.report);
                        $('#importFresh').prop('checked', true);
                    }).fail(RadioUi.fail);
                });
            },

            render(report) {
                if (!report) return;
                const esc = RadioUi.esc;
                const words = radioImport.words;
                const stat = (label, value, tone) => `
                    <div class="rm-import-stat tone-${tone}">
                        <div class="rm-import-stat-label">${esc(label)}</div>
                        <div class="rm-import-stat-value">${esc(value)}</div>
                    </div>`;
                const flags = (report.flags || []).map(flag => `
                    <tr>
                        <td class="text-nowrap">${esc(flag.sheet)} #${esc(flag.row)}</td>
                        <td class="text-nowrap"><a href="${esc(radioImport.detailUrl.replace(':id', flag.radio))}" class="radio-mono">${esc(flag.sn || '-')}</a>
                            <div class="radio-muted">${flag.dmr_id ? 'ID ' + esc(flag.dmr_id) : ''}</div></td>
                        <td><ul class="mb-0 ps-3">${flag.reasons.map(r => `<li>${esc(r)}</li>`).join('')}</ul></td>
                    </tr>`).join('');
                const skipped = (report.skipped || []).map(item =>
                    `<li>${RadioUi.fill(esc(words.skipped_row), { sheet: esc(item.sheet), row: esc(item.row), reason: esc(item.reason) })}</li>`).join('');
                const created = report.masters_created || {};
                const summary = RadioUi.fill(words.summary, {
                    slots: esc(report.empty_slots), types: esc(created.types ?? 0), companies: esc(created.companies ?? 0),
                    departments: esc(created.departments ?? 0), templates: esc(created.templates ?? 0),
                });

                $('#importReport').html(`
                    <header class="rm-cardbox-head">
                        <div>
                            <h2 class="rm-cardbox-title">${esc(words.last)}</h2>
                            <div class="rm-cardbox-sub">${esc(report.file)} · ${esc(report.at || '')} ${report.by ? '· ' + esc(report.by) : ''} · ${esc(RadioUi.fill(words.seconds, { seconds: report.seconds }))}</div>
                        </div>
                    </header>
                    <div class="rm-cardbox-body d-flex flex-column gap-3">
                        <div class="rm-import-stats">
                            ${stat(words.imported, report.imported, 'blue')}
                            ${stat(words.active, report.imported_active, 'green')}
                            ${stat(words.archived, report.imported_archived, 'gray')}
                            ${stat(words.flagged, report.flagged, 'red')}
                        </div>
                        <div class="rm-note"><i class="ti ti-info-circle" aria-hidden="true"></i><span>${summary}</span></div>
                        ${skipped ? `<div class="rm-alert warning"><i class="ti ti-alert-triangle" aria-hidden="true"></i><div>${words.skipped}<ul class="mb-0 ps-3">${skipped}</ul></div></div>` : ''}
                        ${flags ? `
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="rm-cardbox-title">${esc(words.flags)}</div>
                                <a href="${radioImport.radioUrl}?issue=needs_review" class="rm-cardbox-link">${esc(words.open_register)} <i class="ti ti-arrow-right"></i></a>
                            </div>
                            <div class="rm-box table-responsive" style="max-height: 480px">
                                <table class="rm-table compact">
                                    <thead><tr><th>${esc(words.row)}</th><th>${esc(words.sn)}</th><th>${esc(words.reason)}</th></tr></thead>
                                    <tbody>${flags}</tbody>
                                </table>
                            </div>` : ''}
                    </div>
                `);
            },
        };

        $(function() {
            radioImport.render(@json($lastReport));
            // The name of the file chosen, as text.
            $('#importFile').on('change', function() {
                const file = this.files && this.files[0];
                $('#importFileName').text(file ? file.name : radioImport.words.no_file).toggleClass('is-set', !!file);
            });
        });
    </script>
@endpush
