{{-- The filters of the register (in the card, under the toolbar), and the chips of those that are on. --}}
<div class="rm-panel d-none" id="radioFilterCard">
    <div class="rm-panel-title">Filter</div>
    <div class="rm-filter-grid">
        <div>
            <label for="filterCompany" class="form-label">Company (pengguna)</label>
            <select id="filterCompany" class="form-select" multiple="multiple">
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->code }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filterOwner" class="form-label">Pemilik Aset</label>
            <select id="filterOwner" class="form-select" multiple="multiple">
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->code }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filterOwnerGroup" class="form-label">Owner</label>
            <select id="filterOwnerGroup" class="form-select">
                <option value="">Semua</option>
                <option value="mti">MTI</option>
                <option value="non_mti">Non MTI</option>
            </select>
        </div>
        <div>
            <label for="filterDepartment" class="form-label">Department</label>
            <select id="filterDepartment" class="form-select" multiple="multiple">
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filterType" class="form-label">Tipe Radio</label>
            <select id="filterType" class="form-select" multiple="multiple">
                @foreach ($types as $type)
                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filterTemplate" class="form-label">Template Channel</label>
            <select id="filterTemplate" class="form-select" multiple="multiple">
                @foreach ($templates as $template)
                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filterPlacement" class="form-label">Posisi</label>
            <select id="filterPlacement" class="form-select" multiple="multiple">
                @foreach ($placements as $placement)
                    <option value="{{ $placement['value'] }}">{{ $placement['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filterCondition" class="form-label">Kondisi</label>
            <select id="filterCondition" class="form-select" multiple="multiple">
                @foreach ($conditions as $condition)
                    <option value="{{ $condition['value'] }}">{{ $condition['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filterIssue" class="form-label">Hasil Cek</label>
            <select id="filterIssue" class="form-select">
                <option value="">Semua</option>
                @foreach ($issues as $code => $issue)
                    <option value="{{ $code }}">{{ $issue['label'] }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="rm-panel-foot">
        <button type="button" class="btn-rm secondary" onclick="radioFilter.reset()">Kosongkan filter</button>
        <button type="button" class="btn-rm primary" id="radioFilterApply" onclick="radioFilter.apply()">Terapkan filter</button>
    </div>
</div>

@push('javascript')
    <script>
        $(function() {
            [
                ['#filterCompany', 'Pilih company'],
                ['#filterOwner', 'Pilih pemilik aset'],
                ['#filterDepartment', 'Pilih department'],
                ['#filterType', 'Pilih tipe'],
                ['#filterTemplate', 'Pilih template'],
                ['#filterPlacement', 'Pilih posisi'],
                ['#filterCondition', 'Pilih kondisi'],
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
                company: ['#filterCompany', 'Company'],
                owner: ['#filterOwner', 'Pemilik aset'],
                owner_group: ['#filterOwnerGroup', 'Owner'],
                department: ['#filterDepartment', 'Department'],
                type: ['#filterType', 'Tipe'],
                template: ['#filterTemplate', 'Template'],
                placement: ['#filterPlacement', 'Posisi'],
                condition: ['#filterCondition', 'Kondisi'],
                issue: ['#filterIssue', 'Hasil cek'],
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
                        `<button type="button" data-filter="${key}" aria-label="Hapus filter ${RadioUi.esc(title)}"><i class="ti ti-x"></i></button></span>`;
                }).join('');
                $('#radioAppliedChips').html(chips);
                $('#radioApplied').toggleClass('d-none', chips === '');
            },
        };
    </script>
@endpush
