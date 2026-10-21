@extends('main.index')
@push('style')
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/select2.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/select2-bootstrap.css') }}" />
@endpush

@include('RadioManagement::partials.scripts')

@section('page-title', __('RadioManagement::detail.page_title'))
@section('crumb', $radio->dmr_id || ! $radio->previous_dmr_id
    ? __('RadioManagement::detail.crumb', ['id' => $radio->dmr_id ?? $radio->serial_number])
    : __('RadioManagement::detail.crumb_ex', ['id' => $radio->previous_dmr_id]))

@section('content')
    @php
        $label = ($radio->dmr_id ? 'ID ' . $radio->dmr_id . ' · ' : '') . ($radio->serial_number ?? __('RadioManagement::detail.no_sn'));
        // The names to show of what the history keeps in Indonesian: the kind of a line, a field that changed, a value.
        $changeFields = __('RadioManagement::detail.change_fields');
        $changeValues = __('RadioManagement::detail.change_values');
        $changeValue = fn ($value) => $value === null || $value === '' ? '-' : ($changeValues[$value] ?? $value);
        // What the role may do with the radio (the server checks again).
        $may = [
            'update' => \Illuminate\Support\Facades\Blade::check('permission', 'radio-management.radio', 'Update'),
            'delete' => \Illuminate\Support\Facades\Blade::check('permission', 'radio-management.radio', 'Delete'),
        ];
        // The tone and icon of each kind of history entry (the design's timeline).
        $historyLook = fn (string $action) => match ($action) {
            'Serah Terima' => ['blue', 'user'],
            'Ubah Status' => ['yellow', 'refresh'],
            'Program' => ['purple', 'code'],
            'Verifikasi' => ['green', 'circle-check'],
            'Service' => ['orange', 'tool'],
            'Dipulihkan' => ['green', 'archive-off'],
            'Dihapus' => ['red', 'trash'],
            'Arsip' => ['gray', 'archive'],
            'Impor' => ['gray', 'file-import'],
            'Dibuat' => ['gray', 'plus'],
            default => ['gray', 'pencil'],
        };
        $badgeTone = fn (?string $color) => match ($color) {
            'secondary', 'muted', null => 'gray',
            'azure', 'cyan', 'info', 'primary' => 'blue',
            'teal', 'lime', 'success' => 'green',
            'danger', 'pink' => 'red',
            'warning' => 'yellow',
            'indigo' => 'purple',
            default => in_array($color, ['blue', 'green', 'yellow', 'orange', 'red', 'purple', 'gray'], true) ? $color : 'gray',
        };
        $reprogram = collect($issues)->firstWhere('code', 'reprogram');
        $shownIssues = collect($issues)->reject(fn ($issue) => $issue['code'] === 'needs_review');
    @endphp

    <div class="radio-detail">
        {{-- On a phone, where the top bar with the path is not shown. --}}
        <nav class="rm-crumbs d-lg-none" aria-label="{{ __('ui.topbar.breadcrumb') }}">
            <a href="{{ route('radio-management.radio.index') }}">{{ __('RadioManagement::register.title') }}</a>
            <i class="ti ti-chevron-right" aria-hidden="true"></i>
            <span aria-current="page">@yield('crumb')</span>
        </nav>

        @if (!$may['update'] && !$may['delete'])
            <div class="rm-banner">
                <i class="ti ti-lock" aria-hidden="true"></i>
                <span>{{ __('RadioManagement::detail.read_only') }}</span>
            </div>
        @endif

        {{-- Header --}}
        <section class="rm-hero">
            <span class="rm-hero-tile"><i class="ti ti-radio" aria-hidden="true"></i></span>
            <div class="rm-hero-main">
                <div class="rm-hero-kicker">{{ __('RadioManagement::detail.kicker') }}</div>
                <div class="rm-hero-id">
                    @if ($radio->dmr_id)
                        <span>{{ $radio->dmr_id }}</span> <span class="rm-hero-mdc radio-mono">/ MDC {{ $radio->mdc_code }}</span>
                    @elseif ($radio->previous_dmr_id)
                        <span class="text-secondary">ex {{ $radio->previous_dmr_id }}</span>
                    @else
                        <span class="text-secondary">{{ __('RadioManagement::detail.no_id') }}</span>
                    @endif
                </div>
                <div class="rm-hero-meta">
                    <span class="radio-mono">SN {{ $radio->serial_number ?? '-' }}</span>
                    @if ($radio->isArchived())
                        <span class="rm-badge gray">{{ __('RadioManagement::detail.archived', ['reason' => \Modules\RadioManagement\Services\RadioWords::of('archive_reasons', $radio->archive_reason), 'date' => $radio->archived_at->format('d-m-Y')]) }}</span>
                    @else
                        <span class="rm-badge {{ $badgeTone($radio->placement->color()) }}">{{ $radio->placement->display() }}</span>
                    @endif
                    <span class="rm-badge {{ $badgeTone($radio->condition->color()) }}">{{ $radio->condition->display() }}</span>
                </div>
            </div>
            <div class="rm-hero-actions">
                @unless ($radio->isArchived())
                    <button type="button" class="btn-rm secondary" id="radioDetailPrint"
                        onclick="radioPrint.open(@js(route('radio-management.radio.checkout-form', $radio)), radioLabel)">
                        <i class="ti ti-printer" aria-hidden="true"></i> {{ __('RadioManagement::detail.print') }}
                    </button>
                @endunless
                @if ($may['update'] && ! $radio->isArchived())
                    <button type="button" class="btn-rm secondary" id="radioHandoverButton" onclick="radioHandover.open()">
                        <i class="ti ti-user-check" aria-hidden="true"></i> {{ __('RadioManagement::detail.handover') }}
                    </button>
                @endif
                @if ($may['update'])
                    <button type="button" class="btn-rm secondary" onclick="radioHistory.open()">
                        <i class="ti ti-plus" aria-hidden="true"></i> {{ __('RadioManagement::detail.add_history') }}
                    </button>
                    @if ($radio->isArchived())
                        <button type="button" class="btn-rm secondary" onclick="radioArchive.restore({{ $radio->id }}, radioLabel)">
                            <i class="ti ti-archive-off" aria-hidden="true"></i> {{ __('RadioManagement::detail.restore') }}
                        </button>
                    @else
                        <button type="button" class="btn-rm secondary" onclick="radioArchive.open({{ $radio->id }}, radioLabel)">
                            <i class="ti ti-archive" aria-hidden="true"></i> {{ __('RadioManagement::detail.archive') }}
                        </button>
                    @endif
                @endif
                @if ($may['delete'])
                    <button type="button" class="rm-danger-icon" onclick="radioDelete()" data-bs-toggle="tooltip"
                        data-bs-title="{{ __('RadioManagement::detail.delete') }}" aria-label="{{ __('RadioManagement::detail.delete_label') }}"><i class="ti ti-trash"></i></button>
                @endif
                @if ($may['update'])
                    <button type="button" class="btn-rm primary" onclick="radioForm.open('edit', {{ $radio->id }})">
                        <i class="ti ti-edit" aria-hidden="true"></i> {{ __('RadioManagement::detail.edit') }}
                    </button>
                @endif
            </div>
        </section>

        {{-- Cek --}}
        @if (count($issues))
            <section class="rm-cardbox">
                <header class="rm-cardbox-head">
                    <div>
                        <h2 class="rm-cardbox-title">{{ __('RadioManagement::detail.checks') }}</h2>
                        <div class="rm-cardbox-sub">{{ __('RadioManagement::detail.checks_sub') }}</div>
                    </div>
                </header>
                <div class="rm-cardbox-body d-flex flex-column gap-3">
                    @if ($shownIssues->isNotEmpty())
                        <div class="rm-badges">
                            @foreach ($shownIssues as $issue)
                                <span class="rm-badge {{ $badgeTone($issue['color']) }}" @if ($issue['hint']) title="{{ $issue['hint'] }}" @endif>{{ $issue['label'] }}</span>
                            @endforeach
                        </div>
                    @endif
                    @if ($reprogram)
                        <div class="rm-alert warning">
                            <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                            <div class="flex-fill">
                                <b>{{ $reprogram['label'] }}</b>
                                @if ($reprogram['hint'])
                                    <div>{{ $reprogram['hint'] }}</div>
                                @endif
                                <div>{{ __('RadioManagement::detail.reprogram') }}</div>
                            </div>
                            @if ($may['update'])
                                <button type="button" class="btn-rm sm secondary" onclick="radioHistory.open('Program')">{{ __('RadioManagement::detail.add_program') }}</button>
                            @endif
                        </div>
                    @endif
                    @if ($radio->needs_review)
                        <div class="rm-alert danger">
                            <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                            <div>
                                <b>{{ __('RadioManagement::detail.needs_review') }}</b>
                                <ul class="mb-1 ps-3">
                                    @foreach (array_filter(explode("\n", (string) $radio->review_notes)) as $note)
                                        <li>{{ $note }}</li>
                                    @endforeach
                                </ul>
                                <div>{!! __('RadioManagement::detail.review_after') !!}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        @endif

        {{-- Data --}}
        <div class="rm-info-grid">
            <section class="rm-cardbox">
                <header class="rm-cardbox-head">
                    <h2 class="rm-cardbox-title">{{ __('RadioManagement::detail.unit') }}</h2>
                </header>
                <dl class="rm-facts">
                    <div><dt>{{ __('RadioManagement::detail.sn') }}</dt><dd class="radio-mono">{{ $radio->serial_number ?? '-' }}</dd></div>
                    <div><dt>{{ __('RadioManagement::detail.type') }}</dt><dd>{{ $radio->radioType?->name ?? '-' }}</dd></div>
                    <div><dt>{{ __('RadioManagement::detail.category_power') }}</dt><dd>{{ $radio->radioType?->category_label ?? '-' }} · {{ $radio->radioType?->power ?? '-' }}</dd></div>
                    <div><dt>{{ __('RadioManagement::detail.owner') }}</dt><dd>{{ $radio->ownerCompany?->label ?? '-' }}</dd></div>
                    <div>
                        <dt>{{ __('RadioManagement::detail.owner_group') }}</dt>
                        <dd>
                            @if ($radio->ownerGroup())
                                <span class="rm-badge {{ $radio->ownerGroup() === 'MTI' ? 'blue' : 'orange' }}">{{ $radio->ownerGroup() }}</span>
                            @else
                                -
                            @endif
                        </dd>
                    </div>
                    @if ($radio->previous_dmr_id)
                        <div><dt>{{ __('RadioManagement::detail.old_id') }}</dt><dd>{{ $radio->previous_dmr_id }}</dd></div>
                    @endif
                </dl>
            </section>
            <section class="rm-cardbox">
                <header class="rm-cardbox-head">
                    <h2 class="rm-cardbox-title">{{ __('RadioManagement::detail.user') }}</h2>
                </header>
                <dl class="rm-facts">
                    <div><dt>{{ __('RadioManagement::detail.user_name') }}</dt><dd>{{ $radio->user_name ?? '-' }}</dd></div>
                    <div><dt>{{ __('RadioManagement::detail.department') }}</dt><dd>{{ $radio->department?->name ?? '-' }}</dd></div>
                    <div><dt>{{ __('RadioManagement::detail.company') }}</dt><dd>{{ $radio->company?->label ?? '-' }}</dd></div>
                    <div><dt>{{ __('RadioManagement::detail.document') }}</dt><dd class="radio-mono">{{ $radio->document_no ?? '-' }}</dd></div>
                </dl>
            </section>
            <section class="rm-cardbox">
                <header class="rm-cardbox-head">
                    <h2 class="rm-cardbox-title">{{ __('RadioManagement::detail.program') }}</h2>
                </header>
                <dl class="rm-facts">
                    <div>
                        <dt>{{ __('RadioManagement::detail.template') }}</dt>
                        <dd>
                            {{ $radio->channelTemplate?->name ?? '-' }}
                            @if ($radio->channelTemplate?->channels)
                                <div class="radio-muted">{{ $radio->channelTemplate->channels }}</div>
                            @endif
                        </dd>
                    </div>
                    <div><dt>{{ __('RadioManagement::detail.programmed') }}</dt><dd>{{ $radio->last_programmed_at?->format('d-m-Y') ?? '-' }}</dd></div>
                    <div><dt>{{ __('RadioManagement::detail.verified') }}</dt><dd>{{ $radio->last_verified_at?->format('d-m-Y') ?? '-' }}</dd></div>
                    <div><dt>{{ __('RadioManagement::detail.changed') }}</dt><dd>{{ $radio->updated_at?->format('d-m-Y H:i') }} {{ $radio->updater ? __('RadioManagement::detail.by', ['name' => $radio->updater->name]) : '' }}</dd></div>
                </dl>
            </section>
        </div>

        <div class="rm-detail-lower">
            {{-- Riwayat --}}
            <section class="rm-cardbox">
                <header class="rm-cardbox-head">
                    <div>
                        <h2 class="rm-cardbox-title">{{ __('RadioManagement::detail.history') }}</h2>
                        <div class="rm-cardbox-sub">{{ trans_choice('RadioManagement::detail.history_sub', $radio->histories->count(), ['count' => $radio->histories->count()]) }}</div>
                    </div>
                    @if ($may['update'])
                        <a href="#" class="rm-cardbox-link" onclick="radioHistory.open(); return false;">{{ __('RadioManagement::detail.add_history') }}</a>
                    @endif
                </header>
                <div class="rm-cardbox-body">
                    @if ($radio->histories->isEmpty())
                        <div class="radio-muted">{{ __('RadioManagement::detail.no_history') }}</div>
                    @else
                        <ol class="rm-timeline">
                            @foreach ($radio->histories as $history)
                                @php([$tone, $icon] = $historyLook($history->action))
                                <li class="rm-timeline-item">
                                    <span class="rm-timeline-dot tone-{{ $tone }}"><i class="ti ti-{{ $icon }}" aria-hidden="true"></i></span>
                                    <div class="rm-timeline-content">
                                        <div class="rm-timeline-title">
                                            <span>{{ \Modules\RadioManagement\Services\RadioWords::of('history_actions', $history->action) }}</span>
                                            @if ($history->document_no)
                                                <span class="rm-badge outline radio-mono">{{ $history->document_no }}</span>
                                            @endif
                                        </div>
                                        <div class="rm-timeline-meta">{{ $history->event_date->format('d-m-Y') }} ·
                                            {{ $history->user?->name ?? __('RadioManagement::detail.system') }}</div>
                                        @if ($history->changes)
                                            <div class="rm-changes">
                                                @foreach ($history->changes as $change)
                                                    <div class="rm-change">
                                                        <span class="rm-change-field">{{ $changeFields[$change['label']] ?? $change['label'] }}</span>
                                                        <span class="rm-change-old">{{ $changeValue($change['old'] ?? null) }}</span>
                                                        <i class="ti ti-arrow-right" aria-hidden="true"></i>
                                                        <span class="rm-change-new">{{ $changeValue($change['new'] ?? null) }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @elseif ($history->description)
                                            <div class="rm-timeline-text radio-pre">{{ $history->description }}</div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </section>

            <div class="d-flex flex-column gap-3">
                @if ($radio->notes)
                    <section class="rm-cardbox">
                        <header class="rm-cardbox-head">
                            <h2 class="rm-cardbox-title">{{ __('RadioManagement::detail.notes') }}</h2>
                        </header>
                        <div class="rm-cardbox-body radio-pre">{{ $radio->notes }}</div>
                    </section>
                @endif

                {{-- Data asli Excel --}}
                <section class="rm-cardbox">
                    <header class="rm-cardbox-head">
                        <div>
                            <h2 class="rm-cardbox-title">{{ __('RadioManagement::detail.source') }}</h2>
                            @if ($radio->source_data)
                                <div class="rm-cardbox-sub">{{ __('RadioManagement::detail.source_sub', ['sheet' => $radio->source_sheet, 'row' => $radio->source_row]) }}</div>
                            @endif
                        </div>
                    </header>
                    <div class="rm-cardbox-body">
                        @if ($radio->source_data)
                            <dl class="rm-source">
                                @foreach ($radio->source_data as $column => $value)
                                    <dt>{{ $column }}</dt>
                                    <dd>{{ is_scalar($value) ? $value : json_encode($value) }}</dd>
                                @endforeach
                            </dl>
                        @else
                            <div class="radio-muted">{{ __('RadioManagement::detail.source_none') }}</div>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </div>

    @include('RadioManagement::radio.modal')
    @include('RadioManagement::radio.archive-modal')
    @include('RadioManagement::radio.print-modal')
    @if ($may['update'] && ! $radio->isArchived())
        @include('RadioManagement::radio.handover-modal')
    @endif

    {{-- Tambah riwayat --}}
    <div class="modal fade rm-modal" data-bs-backdrop="static" id="radioHistoryModal" aria-hidden="true"
        aria-labelledby="radioHistoryTitle">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="rm-dialog-head">
                    <span class="rm-dialog-icon"><i class="ti ti-plus" aria-hidden="true"></i></span>
                    <div class="rm-dialog-titles">
                        <h3 class="rm-dialog-title" id="radioHistoryTitle">{{ __('RadioManagement::detail.history_dialog.title') }}</h3>
                        <p class="rm-dialog-sub">{{ __('RadioManagement::detail.history_dialog.sub') }}</p>
                    </div>
                    <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="{{ __('RadioManagement::common.ui.close') }}"><i
                            class="ti ti-x"></i></button>
                </div>
                <div class="rm-dialog-body">
                    <div class="row g-3">
                        <div class="col-md-6 rm-field">
                            <label for="history_action" class="form-label required">{{ __('RadioManagement::detail.history_dialog.kind') }}</label>
                            <select id="history_action" class="form-select">
                                @foreach ($historyActions as $action)
                                    <option value="{{ $action }}">{{ \Modules\RadioManagement\Services\RadioWords::of('history_actions', $action) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 rm-field">
                            <label for="history_date" class="form-label required">{{ __('RadioManagement::detail.history_dialog.date') }}</label>
                            <input type="date" id="history_date" class="form-control">
                        </div>
                    </div>
                    <div class="rm-field">
                        <label for="history_description" class="form-label required">{{ __('RadioManagement::detail.history_dialog.description') }}</label>
                        <textarea id="history_description" class="form-control" rows="3" maxlength="2000"
                            placeholder="{{ __('RadioManagement::detail.history_dialog.description_hint') }}"></textarea>
                        <div class="rm-hint">{{ __('RadioManagement::detail.history_dialog.updates') }}</div>
                    </div>
                    <div class="rm-field">
                        <label for="history_document" class="form-label">{{ __('RadioManagement::detail.history_dialog.document') }}</label>
                        <input type="text" id="history_document" class="form-control radio-mono" maxlength="100"
                            placeholder="{{ __('RadioManagement::detail.history_dialog.document_hint') }}">
                    </div>
                </div>
                <div class="rm-dialog-foot">
                    <button type="button" class="btn-rm secondary" data-bs-dismiss="modal">{{ __('RadioManagement::common.ui.cancel') }}</button>
                    <button type="button" class="btn-rm primary" id="radioHistoryButton" onclick="radioHistory.submit()">
                        {{ __('RadioManagement::detail.history_dialog.submit') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('javascript')
    <script src="{{ asset('assets/libs/select2/select2.min.js') }}"></script>
    <script>
        const radioLabel = @js($label);
        /** The words of the dialogs of this page in its language (RadioManagement::detail). */
        const radioDetailText = { history: @json(__('RadioManagement::detail.history_dialog')), delete: @json(__('RadioManagement::detail.delete_dialog')) };
        // What is typed to delete: the Radio ID, or the SN of a radio without one.
        const radioConfirmWord = @js((string) ($radio->dmr_id ?? $radio->serial_number ?? ''));

        const radioHistory = {
            url: "{{ route('radio-management.radio.add-history', $radio) }}",
            open(action = null) {
                if (action && $(`#history_action option[value="${action}"]`).length) {
                    $('#history_action').val(action);
                } else {
                    $('#history_action').prop('selectedIndex', 0);
                }
                $('#history_date').val(RadioUi.today()).attr('max', RadioUi.today());
                $('#history_description').val('');
                $('#history_document').val('');
                $('#radioHistoryModal').modal('show');
            },
            submit() {
                RadioUi.send('#radioHistoryButton', {
                    url: radioHistory.url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _method: 'PUT',
                        action: $('#history_action').val(),
                        event_date: $('#history_date').val(),
                        description: $('#history_description').val(),
                        document_no: $('#history_document').val(),
                    },
                })?.done(response => {
                    $('#radioHistoryModal').modal('hide');
                    rmFeedback.toast(response.message || radioDetailText.history.added);
                    setTimeout(() => window.location.reload(), 900);
                }).fail(RadioUi.fail);
            },
        };

        /** Deleting is for a radio entered by mistake: the person types its Radio ID (or SN) as a last look. */
        function radioDelete() {
            const words = radioDetailText.delete;
            rmDialog.confirm({
                tone: 'red',
                icon: 'trash',
                title: RadioUi.fill(words.title, { word: radioConfirmWord }),
                html: RadioUi.fill(words.text, { word: RadioUi.esc(radioConfirmWord) }),
                input: 'text',
                inputAttributes: { autocomplete: 'off', 'aria-label': words.type },
                confirmText: words.submit,
                confirmKind: 'destructive',
                preConfirm: typed => {
                    if (String(typed || '').trim().toUpperCase() !== radioConfirmWord.toUpperCase()) {
                        Swal.showValidationMessage(RadioUi.fill(words.typed_other, { word: radioConfirmWord }));
                        return false;
                    }
                    return true;
                },
            }).then(result => {
                if (!result.isConfirmed) return;
                $.ajax({
                    url: "{{ route('radio-management.radio.destroy', $radio) }}",
                    type: 'POST',
                    dataType: 'json',
                    data: { _method: 'DELETE' },
                }).done(response => {
                    rmFeedback.toast(response.message || radioDetailText.delete.deleted);
                    setTimeout(() => window.location = "{{ route('radio-management.radio.index') }}", 900);
                }).fail(RadioUi.fail);
            });
        }

        $(function() {
            radioForm.onSaved = () => setTimeout(() => window.location.reload(), 900);
            radioArchive.onDone = () => setTimeout(() => window.location.reload(), 900);
        });
    </script>
@endpush
