{{-- The filters of the register (in the card, under the toolbar), and the chips of those that are on. --}}
<div class="rm-panel d-none" id="radioFilterCard">
    <div class="rm-panel-title">{{ __('RadioManagement::register.filters.title') }}</div>
    <div class="rm-filter-grid">
        <div>
            <label for="filterCompany" class="form-label">{{ __('RadioManagement::register.filters.company') }}</label>
            <select id="filterCompany" class="form-select" multiple="multiple">
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->code }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filterOwner" class="form-label">{{ __('RadioManagement::register.filters.owner') }}</label>
            <select id="filterOwner" class="form-select" multiple="multiple">
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->code }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filterOwnerGroup" class="form-label">{{ __('RadioManagement::register.filters.owner_group') }}</label>
            <select id="filterOwnerGroup" class="form-select">
                <option value="">{{ __('RadioManagement::register.filters.all') }}</option>
                <option value="mti">MTI</option>
                <option value="non_mti">Non MTI</option>
            </select>
        </div>
        <div>
            <label for="filterDepartment" class="form-label">{{ __('RadioManagement::register.filters.department') }}</label>
            <select id="filterDepartment" class="form-select" multiple="multiple">
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filterType" class="form-label">{{ __('RadioManagement::register.filters.type') }}</label>
            <select id="filterType" class="form-select" multiple="multiple">
                @foreach ($types as $type)
                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filterTemplate" class="form-label">{{ __('RadioManagement::register.filters.template') }}</label>
            <select id="filterTemplate" class="form-select" multiple="multiple">
                @foreach ($templates as $template)
                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filterPlacement" class="form-label">{{ __('RadioManagement::register.filters.placement') }}</label>
            <select id="filterPlacement" class="form-select" multiple="multiple">
                @foreach ($placements as $placement)
                    <option value="{{ $placement['value'] }}">{{ $placement['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filterCondition" class="form-label">{{ __('RadioManagement::register.filters.condition') }}</label>
            <select id="filterCondition" class="form-select" multiple="multiple">
                @foreach ($conditions as $condition)
                    <option value="{{ $condition['value'] }}">{{ $condition['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filterIssue" class="form-label">{{ __('RadioManagement::register.filters.issue') }}</label>
            <select id="filterIssue" class="form-select">
                <option value="">{{ __('RadioManagement::register.filters.all') }}</option>
                @foreach ($issues as $code => $issue)
                    <option value="{{ $code }}">{{ \Modules\RadioManagement\Services\RadioCheckService::label($code) }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="rm-panel-foot">
        <button type="button" class="btn-rm secondary" onclick="radioFilter.reset()">{{ __('RadioManagement::register.filters.clear') }}</button>
        <button type="button" class="btn-rm primary" id="radioFilterApply" onclick="radioFilter.apply()">{{ __('RadioManagement::register.filters.apply') }}</button>
    </div>
</div>

@push('javascript')
    <script>
        /** The words of the filters in the language of the page (RadioManagement::register.filters). */
        const radioFilterText = @json(__('RadioManagement::register.filters'));

        $(function() {
            [
                ['#filterCompany', radioFilterText.pick_company],
                ['#filterOwner', radioFilterText.pick_owner],
                ['#filterDepartment', radioFilterText.pick_department],
                ['#filterType', radioFilterText.pick_type],
                ['#filterTemplate', radioFilterText.pick_template],
                ['#filterPlacement', radioFilterText.pick_placement],
                ['#filterCondition', radioFilterText.pick_condition],
            ].forEach(([selector, placeholder]) => {
                $(selector).select2({
                    theme: 'bootstrap-5',
                    placeholder,
                    allowClear: true,
                    multiple: true,
                    dropdownParent: $('#radioFilterCard'),
                    width: '100%',
                    closeOnSelect: false
                });
            });
            $('#radioApplied').on('click', 'button[data-filter]', function() {
                radioFilter.clear($(this).data('filter'));
            });
        });

        const radioFilter = {
            values: {},
            /** Each filter: its field, and its name on a chip. */
            fields: {
                company: ['#filterCompany', radioFilterText.chips.company],
                owner: ['#filterOwner', radioFilterText.chips.owner],
                owner_group: ['#filterOwnerGroup', radioFilterText.chips.owner_group],
                department: ['#filterDepartment', radioFilterText.chips.department],
                type: ['#filterType', radioFilterText.chips.type],
                template: ['#filterTemplate', radioFilterText.chips.template],
                placement: ['#filterPlacement', radioFilterText.chips.placement],
                condition: ['#filterCondition', radioFilterText.chips.condition],
                issue: ['#filterIssue', radioFilterText.chips.issue],
            },
            read() {
                return {
                    company: $('#filterCompany').val(),
                    owner: $('#filterOwner').val(),
                    owner_group: $('#filterOwnerGroup').val(),
                    department: $('#filterDepartment').val(),
                    type: $('#filterType').val(),
                    template: $('#filterTemplate').val(),
                    placement: $('#filterPlacement').val(),
                    condition: $('#filterCondition').val(),
                    issue: $('#filterIssue').val(),
                };
            },
            /** The card of the filters opens and closes (on: open or close it). */
            toggle(on) {
                const open = on === undefined ? $('#radioFilterButton').attr('aria-expanded') !== 'true' : !!on;
                $('#radioFilterCard').toggleClass('d-none', !open);
                $('#radioFilterButton').attr('aria-expanded', open ? 'true' : 'false');
            },
            busy(on) {
                rmFeedback.busy('#radioFilterApply', on);
            },
            apply() {
                radioFilter.values = radioFilter.read();
                radioTable.page = 1;
                radioFilter.busy(true);
                radioTable.load();
            },
            reset() {
                $('#radioFilterCard select[multiple]').val(null).trigger('change');
                $('#filterIssue').val('');
                $('#filterOwnerGroup').val('');
                radioFilter.apply();
            },
            /** One filter off (the × of its chip). */
            clear(key) {
                const field = radioFilter.fields[key];
                if (!field) return;
                const multiple = !['owner_group', 'issue'].includes(key);
                $(field[0]).val(multiple ? null : '').trigger('change');
                radioFilter.apply();
            },
            /** "Diterapkan:" and a chip per filter that is on, with the names of what was chosen (as text). */
            chips() {
                const chips = Object.entries(radioFilter.fields).map(([key, [, title]]) => {
                    const chosen = [].concat(radioFilter.values[key] ?? []).filter(value => value !== '' && value !== null);
                    if (!chosen.length) return '';
                    const names = radioFilterNames[key] || {};
                    const text = chosen.map(value => names[value] ?? value).join(', ');
                    return `<span class="rm-chip ${key === 'issue' ? '' : 'blue'}">${RadioUi.esc(title)}: ${RadioUi.esc(text)}` +
                        `<button type="button" data-filter="${key}" aria-label="${RadioUi.esc(RadioUi.fill(radioFilterText.remove, { name: title }))}"><i class="ti ti-x"></i></button></span>`;
                }).join('');
                $('#radioAppliedChips').html(chips);
                $('#radioApplied').toggleClass('d-none', chips === '');
            },
        };
    </script>
@endpush
