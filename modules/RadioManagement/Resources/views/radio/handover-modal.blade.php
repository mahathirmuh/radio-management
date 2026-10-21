{{--
    Handing the radio over ("Serah terima", 6.2): to whom (from the employee directory, or typed), with which BA and PO and
    accessories, in what condition and where it goes; the receiver signs on the pad. The signature is kept nowhere: the
    handover is recorded, then the checkout form opens with the signature in it, to be printed (K13b).

    Needs: $radio, $handoverAccessories, $conditions, $placements, $departments, $companies, $processedBy (detail page).
--}}
@php
    $handoverTitle = __('RadioManagement::detail.handover_dialog.title', ['radio' => $radio->dmr_id ?? ($radio->serial_number ?? '')]);
    $handoverSub = collect([$radio->radioType?->name, $radio->serial_number ? 'SN ' . $radio->serial_number : null, $radio->mdc_code ? 'MDC ' . $radio->mdc_code : null])->filter()->implode(' · ');
@endphp
<div class="modal fade rm-modal xwide" data-bs-backdrop="static" id="radioHandoverModal" tabindex="-1" aria-labelledby="radioHandoverTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="rm-dialog-head is-ruled">
                <span class="rm-dialog-icon"><i class="ti ti-user-check" aria-hidden="true"></i></span>
                <div class="rm-dialog-titles">
                    <h3 class="rm-dialog-title modal-title" id="radioHandoverTitle">{{ $handoverTitle }}</h3>
                    <p class="rm-dialog-sub">{{ $handoverSub }}</p>
                </div>
                <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="{{ __('RadioManagement::common.ui.close') }}"><i class="ti ti-x"></i></button>
            </div>

            <div class="rm-handover">
                <div class="rm-handover-form">
                    <div class="rm-field">
                        <label for="handoverReceiver" class="form-label required">{{ __('RadioManagement::detail.handover_dialog.receiver') }}</label>
                        <div class="rm-search w-100">
                            <i class="ti ti-user" aria-hidden="true"></i>
                            <input type="search" id="handoverReceiver" maxlength="120" autocomplete="off" placeholder="{{ __('RadioManagement::detail.handover_dialog.receiver_hint') }}">
                        </div>
                        <div id="handoverMatches" aria-live="polite"></div>
                        <div class="rm-hint" id="handoverReceiverHint">{{ __('RadioManagement::detail.handover_dialog.receiver_help') }}</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6 rm-field">
                            <label for="handoverBa" class="form-label required">{{ __('RadioManagement::detail.handover_dialog.ba') }}</label>
                            <input type="text" id="handoverBa" class="form-control radio-mono" maxlength="60" autocomplete="off" placeholder="BA2300412">
                        </div>
                        <div class="col-sm-6 rm-field">
                            <label for="handoverPo" class="form-label">{{ __('RadioManagement::detail.handover_dialog.po') }}</label>
                            <input type="text" id="handoverPo" class="form-control radio-mono" maxlength="60" autocomplete="off" placeholder="PO 4500187">
                        </div>
                    </div>

                    <div class="rm-field">
                        <span class="form-label" id="handoverAccessoriesLabel">{{ __('RadioManagement::detail.handover_dialog.accessories') }}</span>
                        <div class="rm-handover-acc" role="group" aria-labelledby="handoverAccessoriesLabel">
                            @foreach ($handoverAccessories as $item)
                                <label class="rm-acc">
                                    <input type="checkbox" name="handover_accessories" value="{{ $item }}">
                                    <span>{{ \Modules\RadioManagement\Services\RadioWords::of('accessories', $item) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6 rm-field">
                            <label for="handoverCondition" class="form-label">{{ __('RadioManagement::detail.handover_dialog.condition') }}</label>
                            <select id="handoverCondition" class="form-select">
                                @foreach ($conditions as $condition)
                                    <option value="{{ $condition['value'] }}" @selected($condition['value'] === $radio->condition?->value)>{{ $condition['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 rm-field">
                            <label for="handoverPlacement" class="form-label">{{ __('RadioManagement::detail.handover_dialog.placement') }}</label>
                            <select id="handoverPlacement" class="form-select">
                                @foreach ($placements as $placement)
                                    <option value="{{ $placement['value'] }}" @selected($placement['value'] === 'di_user')>{{ $placement['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 rm-field">
                            <label for="handoverDepartment" class="form-label">{{ __('RadioManagement::detail.handover_dialog.department') }}</label>
                            <select id="handoverDepartment" class="form-select">
                                <option value="">{{ __('RadioManagement::detail.handover_dialog.unchanged') }}</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected($department->id === $radio->department_id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 rm-field">
                            <label for="handoverCompany" class="form-label">{{ __('RadioManagement::detail.handover_dialog.company') }}</label>
                            <select id="handoverCompany" class="form-select">
                                <option value="">{{ __('RadioManagement::detail.handover_dialog.unchanged') }}</option>
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}" @selected($company->id === $radio->company_id)>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div role="alert">
                        <div class="rm-alert danger d-none" id="handoverError">
                            <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                            <span id="handoverErrorText"></span>
                        </div>
                    </div>
                </div>

                <div class="rm-handover-sign">
                    <span class="form-label required mb-0" id="handoverSignLabel">{{ __('RadioManagement::detail.handover_dialog.signature') }}</span>
                    <div class="rm-pad" id="handoverPad">
                        <canvas id="handoverCanvas" aria-labelledby="handoverSignLabel"></canvas>
                        <span class="rm-pad-line" aria-hidden="true"></span>
                        <span class="rm-pad-cap" id="handoverPadCap">{{ __('RadioManagement::detail.handover_dialog.sign_here') }}</span>
                        <button type="button" class="btn-rm sm secondary rm-pad-clear" id="handoverPadClear" disabled>{{ __('RadioManagement::detail.handover_dialog.clear') }}</button>
                    </div>
                    <div class="rm-handover-accept">
                        <i class="ti ti-square-check" aria-hidden="true"></i>
                        <span>{{ __('RadioManagement::detail.handover_dialog.accept') }}</span>
                    </div>
                    <div class="rm-handover-ict">
                        @if ($processedBy)
                            <img src="{{ $processedBy['image'] }}" alt="{{ __('RadioManagement::detail.handover_dialog.signature_of', ['name' => $processedBy['name']]) }}">
                            <div><b>{{ __('RadioManagement::detail.handover_dialog.processed_by', ['name' => $processedBy['name']]) }}</b><span>E-Sign Master</span></div>
                        @else
                            <div><b>{{ __('RadioManagement::detail.handover_dialog.processed_by', ['name' => auth()->user()?->name]) }}</b><span>{{ __('RadioManagement::detail.handover_dialog.no_esign') }}</span></div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="rm-dialog-foot is-gray">
                <span class="rm-dialog-note">{{ __('RadioManagement::detail.handover_dialog.note') }}</span>
                <button type="button" class="btn-rm secondary" data-bs-dismiss="modal">{{ __('RadioManagement::detail.handover_dialog.cancel') }}</button>
                <button type="button" class="btn-rm primary" id="handoverConfirm" disabled><i class="ti ti-check" aria-hidden="true"></i> {{ __('RadioManagement::detail.handover_dialog.confirm') }}</button>
            </div>
        </div>
    </div>
</div>

@push('javascript')
    <script>
        const radioHandover = {
            /** Its words in the language of the page (RadioManagement::detail.handover_dialog). */
            words: @json(__('RadioManagement::detail.handover_dialog')),
            urls: {
                handover: @js(route('radio-management.radio.handover', $radio)),
                employees: @js(route('radio-management.radio.employees')),
            },
            /** The last search of the directory (an older answer is dropped). */
            asked: 0,
            /** The pad: strokes of points (as shares of the pad's width and height), and how long the ink is (in widths). */
            pad: { strokes: [], drawing: null, length: 0, ratio: 0.5 },

            open() {
                $('#handoverReceiver, #handoverBa, #handoverPo').val('');
                $('#handoverMatches').empty();
                $('input[name="handover_accessories"]').prop('checked', false);
                radioHandover.employee = '';
                radioHandover.error('');
                radioHandover.clear();
                $('#radioHandoverModal').modal('show');
            },

            /** The employee number of the person chosen in the directory ('' when the name was typed). */
            employee: '',

            /** Confirmed only with a receiver, a BA and a signature. */
            ready() {
                const ok = String($('#handoverReceiver').val() || '').trim() !== '' && String($('#handoverBa').val() || '').trim() !== '' && radioHandover.hasInk();
                $('#handoverConfirm').prop('disabled', !ok);
                return ok;
            },

            /** A signature is ink at least 6% of the pad's width long: a tap, or a speck, is none. */
            hasInk() {
                return radioHandover.pad.length >= 0.06;
            },

            error(text) {
                $('#handoverErrorText').text(text);
                $('#handoverError').toggleClass('d-none', !text);
            },

            /** People of the directory whose name or address holds what was typed; chosen, their name and number are taken. */
            lookup() {
                const typed = String($('#handoverReceiver').val() || '').trim();
                const mine = ++radioHandover.asked;
                radioHandover.employee = '';
                if (typed.length < 3) {
                    $('#handoverMatches').empty();
                    return;
                }
                $.getJSON(radioHandover.urls.employees, { q: typed }).done(response => {
                    if (mine !== radioHandover.asked) return;
                    const found = Array.isArray(response.data) ? response.data : [];
                    const esc = RadioUi.esc;
                    $('#handoverMatches').html(found.length ? `<div class="rm-matches mt-2">${found.map((person, index) =>
                        `<button type="button" class="rm-match" data-pick="${index}"><span class="rm-match-name">${esc(person.name)}</span>` +
                        `<span class="rm-match-line">${esc(person.email || '')}</span><span class="rm-match-id">${esc(person.employee_id || '')}</span></button>`
                    ).join('')}</div>` : '');
                    radioHandover.found = found;
                });
            },

            found: [],

            pick(index) {
                const person = radioHandover.found[Number(index)];
                if (!person) return;
                $('#handoverReceiver').val(person.name || '');
                radioHandover.employee = String(person.employee_id || '');
                radioHandover.asked++;
                $('#handoverMatches').empty();
                radioHandover.ready();
            },

            payload() {
                const accessories = [];
                $('input[name="handover_accessories"]:checked').each(function() {
                    accessories.push($(this).val());
                });
                return {
                    _method: 'PUT',
                    receiver: String($('#handoverReceiver').val() || '').trim(),
                    employee_id: radioHandover.employee,
                    ba: String($('#handoverBa').val() || '').trim(),
                    po: String($('#handoverPo').val() || '').trim(),
                    accessories,
                    condition: $('#handoverCondition').val(),
                    placement: $('#handoverPlacement').val(),
                    department_id: $('#handoverDepartment').val() || '',
                    company_id: $('#handoverCompany').val() || '',
                    signature: radioHandover.picture(),
                };
            },

            submit() {
                if (!radioHandover.ready()) return;
                const data = radioHandover.payload();
                radioHandover.error('');
                rmFeedback.busy('#handoverConfirm', true);
                $.ajax({ url: radioHandover.urls.handover, type: 'POST', dataType: 'json', data })
                    .done(response => {
                        $('#radioHandoverModal').modal('hide');
                        rmFeedback.toast(response.message);
                        // The form opens with the signature in it; when it closes, the page shows the radio as it is now.
                        radioPrint.open(response.checkout_form, radioLabel, { signature: data.signature, after: () => window.location.reload() });
                    })
                    .fail(xhr => {
                        const body = xhr.responseJSON || {};
                        radioHandover.error([].concat(body.errors || body.message || radioHandover.words.failed).join(' '));
                    })
                    .always(() => rmFeedback.busy('#handoverConfirm', false));
            },

            // ---- the pad (a canvas the size of its box; the picture is a PNG with see-through paper)
            canvas() {
                return document.getElementById('handoverCanvas');
            },

            size() {
                const canvas = radioHandover.canvas();
                if (!canvas || !canvas.getBoundingClientRect) return;
                const box = canvas.getBoundingClientRect();
                const density = Math.max(2, window.devicePixelRatio || 1);
                canvas.width = Math.round(box.width * density);
                canvas.height = Math.round(box.height * density);
                radioHandover.pad.ratio = box.width ? box.height / box.width : 0.5;
                radioHandover.paint();
            },

            clear() {
                radioHandover.pad.strokes = [];
                radioHandover.pad.drawing = null;
                radioHandover.pad.length = 0;
                radioHandover.paint();
                $('#handoverPadClear').prop('disabled', true);
                $('#handoverPadCap').removeClass('d-none');
                radioHandover.ready();
            },

            point(event) {
                const box = radioHandover.canvas().getBoundingClientRect();
                return { x: (event.clientX - box.left) / box.width, y: (event.clientY - box.top) / box.height };
            },

            paint() {
                const canvas = radioHandover.canvas();
                if (!canvas || !canvas.getContext) return;
                const context = canvas.getContext('2d');
                context.clearRect(0, 0, canvas.width, canvas.height);
                context.strokeStyle = '#101010';
                context.lineCap = 'round';
                context.lineJoin = 'round';
                context.lineWidth = Math.max(2, canvas.width * 0.005);
                const [w, h] = [canvas.width, canvas.height];
                radioHandover.pad.strokes.forEach(stroke => {
                    context.beginPath();
                    stroke.forEach((at, index) => (index ? context.lineTo(at.x * w, at.y * h) : context.moveTo(at.x * w, at.y * h)));
                    if (stroke.length === 1) context.lineTo(stroke[0].x * w + 0.1, stroke[0].y * h);
                    context.stroke();
                });
            },

            picture() {
                const canvas = radioHandover.canvas();
                return canvas && canvas.toDataURL ? canvas.toDataURL('image/png') : '';
            },

            down(event) {
                const canvas = radioHandover.canvas();
                event.preventDefault();
                canvas.setPointerCapture?.(event.pointerId);
                radioHandover.pad.drawing = [radioHandover.point(event)];
                radioHandover.pad.strokes.push(radioHandover.pad.drawing);
                $('#handoverPadCap').addClass('d-none');
                radioHandover.paint();
            },

            move(event) {
                const stroke = radioHandover.pad.drawing;
                if (!stroke) return;
                const at = radioHandover.point(event);
                const last = stroke[stroke.length - 1];
                radioHandover.pad.length += Math.hypot(at.x - last.x, (at.y - last.y) * radioHandover.pad.ratio);
                stroke.push(at);
                radioHandover.paint();
            },

            up() {
                if (!radioHandover.pad.drawing) return;
                radioHandover.pad.drawing = null;
                $('#handoverPadClear').prop('disabled', false);
                radioHandover.ready();
            },
        };

        $(function() {
            $('#handoverReceiver').on('input', debounce(radioHandover.lookup, 400)).on('input', radioHandover.ready);
            $('#handoverBa').on('input', radioHandover.ready);
            $('#handoverMatches').on('click', '[data-pick]', function() {
                radioHandover.pick($(this).attr('data-pick'));
            });
            $('#handoverPadClear').on('click', radioHandover.clear);
            $('#handoverConfirm').on('click', radioHandover.submit);
            $('#radioHandoverModal').on('shown.bs.modal', radioHandover.size);
            const canvas = radioHandover.canvas();
            if (canvas && canvas.addEventListener) {
                canvas.addEventListener('pointerdown', radioHandover.down);
                canvas.addEventListener('pointermove', radioHandover.move);
                canvas.addEventListener('pointerup', radioHandover.up);
                canvas.addEventListener('pointercancel', radioHandover.up);
                window.addEventListener('resize', () => {
                    if ($('#radioHandoverModal').hasClass('show')) radioHandover.size();
                });
            }
        });
    </script>
@endpush
