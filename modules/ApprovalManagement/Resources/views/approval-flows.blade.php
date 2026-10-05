@extends('main.index')

@push('style')
<link rel="stylesheet" href="{{ asset('assets/libs/select2/select2.min.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/libs/select2/select2-bootstrap.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/libs/datatables/datatables.min.css') }}" />
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <div>
                        <h5 class="card-title mb-0">Approval Flow List</h5>
                        <small class="text-muted">Flow &rarr; Stages (sequential) &rarr; Steps (parallel approvers)</small>
                    </div>
                    <button id="btn-modal" class="btn btn-primary btn-sm rounded-pill px-3" data-action="Create">
                        <i class="ti ti-plus"></i>&nbsp; Create Approval Flow
                    </button>
                </div>
                <div class="card-body">
                    <table id="approval-flows-table" class="table table-striped table-bordered w-100">
                        <thead>
                            <tr>
                                <th width="5%"></th>
                                <th>#</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Stages</th>
                                <th>Status</th>
                                <th width="15%">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@include('ApprovalManagement::partials.modal-flows')
@include('ApprovalManagement::partials.modal-stages')
@include('ApprovalManagement::partials.modal-steps')
@endsection

@push('javascript')
<script src="{{ asset('assets/libs/select2/select2.min.js') }}"></script>
<script src="{{ asset('assets/libs/datatables/datatables.min.js') }}"></script>
<script>
    const routes = {
        flowShow:    "{{ route('api.approval-management.flows.show', [':id']) }}",
        flowStore:   "{{ route('api.approval-management.flows.store') }}",
        flowUpdate:  "{{ route('api.approval-management.flows.update', [':id']) }}",
        flowDestroy: "{{ route('api.approval-management.flows.destroy', [':id']) }}",
        flowDt:      "{{ route('api.approval-management.flows.datatable') }}",

        stageDt:      "{{ route('api.approval-management.stages.datatable') }}",
        stageShow:    "{{ route('api.approval-management.stages.show', [':id']) }}",
        stageStore:   "{{ route('api.approval-management.stages.store') }}",
        stageUpdate:  "{{ route('api.approval-management.stages.update', [':id']) }}",
        stageDestroy: "{{ route('api.approval-management.stages.destroy', [':id']) }}",

        stepDt:      "{{ route('api.approval-management.steps.datatable') }}",
        stepShow:    "{{ route('api.approval-management.steps.show', [':id']) }}",
        stepStore:   "{{ route('api.approval-management.steps.store') }}",
        stepUpdate:  "{{ route('api.approval-management.steps.update', [':id']) }}",
        stepDestroy: "{{ route('api.approval-management.steps.destroy', [':id']) }}",
    };

    const flowModal  = $('#flow-modal');
    const stageModal = $('#stage-modal');
    const stepsModal = $('#steps-modal');
    const flowForm   = $('#flow-form');
    const stageForm  = $('#stage-form');
    const stepsForm  = $('#steps-form');
    const table      = $('#approval-flows-table');

    /* ---------------- helpers ---------------- */
    function ruleBadge(rule, quorum) {
        const map = { ALL: 'bg-primary', ANY: 'bg-success', QUORUM: 'bg-warning text-dark' };
        const label = rule === 'QUORUM' ? `QUORUM (${quorum})` : rule;
        return `<span class="badge ${map[rule] || 'bg-secondary'}">${label}</span>`;
    }

    function confirmDelete(url, onDone) {
        Swal.fire({
            title: 'Are you sure?', text: 'This cannot be undone!', icon: 'warning',
            showCancelButton: true, confirmButtonText: 'Yes, delete it!', cancelButtonText: 'Cancel'
        }).then((r) => {
            if (!r.isConfirmed) return;
            $.ajax({ url, method: 'DELETE',
                success(res) {
                    if (res.status) { Swal.fire('Deleted!', res.message, 'success'); onDone && onDone(); }
                    else Swal.fire('Failed', res.message, 'error');
                },
                error() { Swal.fire('Error', 'Something went wrong.', 'error'); }
            });
        });
    }

    function showValidation(xhr) {
        Swal.close();
        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
            $.each(xhr.responseJSON.errors, (key, val) => {
                $(`#${key}`).addClass('is-invalid');
                $(`#${key}-error`).text(val[0]);
            });
        } else if (xhr.status === 422 && xhr.responseJSON) {
            Swal.fire('Failed', xhr.responseJSON.message, 'error');
        } else {
            Swal.fire('Error', 'Something went wrong. Please try again.', 'error');
        }
    }

    /* ---------------- FLOW modal ---------------- */
    function setFlowMode(mode, data = {}) {
        flowForm[0].reset();
        $('.is-invalid').removeClass('is-invalid');
        flowForm.attr('data-mode', mode);
        flowModal.find('.modal-title').text(mode + ' Approval Flow');
        $('#flow-id').val(data.id || '');
        $('#name').val(data.name || '');
        $('#description').val(data.description || '');
        $('#status').val(data.status || 'ACTIVE');
        flowForm.find('input, textarea, select').prop('disabled', mode === 'View');
        $('#btn-save').toggle(mode !== 'View');
    }

    /* ---------------- STAGE modal ---------------- */
    function setStageMode(mode, flowId, data = {}) {
        stageForm[0].reset();
        $('.is-invalid').removeClass('is-invalid');
        stageForm.attr('data-mode', mode);
        stageModal.find('.modal-title').text(mode + ' Stage');
        $('#stage-id').val(data.id || '');
        $('#stage_flow_id').val(flowId);
        $('#stage_name').val(data.name || '');
        $('#stage_order').val(data.stage_order || '');
        $('#rule').val(data.rule || 'ALL');
        $('#quorum_count').val(data.quorum_count || '');
        toggleQuorum();
        stageForm.find('input, select').prop('disabled', mode === 'View');
        $('#btn-save-stage').toggle(mode !== 'View');
    }

    function toggleQuorum() {
        const isQuorum = $('#rule').val() === 'QUORUM';
        $('#quorum-wrapper').toggleClass('d-none', !isQuorum);
    }

    /* ---------------- STEP modal + fields builder ---------------- */
    function addFieldRow(field = {}) {
        const tpl = document.getElementById('field-row-template').content.cloneNode(true);
        const $row = $(tpl).find('.field-row');
        $row.find('.field-label').val(field.label || '');
        $row.find('.field-type').val(field.type || 'text');
        $row.find('.field-required').prop('checked', !!field.is_required);
        let opts = field.options;
        if (Array.isArray(opts)) opts = opts.join(', ');
        $row.find('.field-options').val(opts || '');
        $('#fields-container').append($row);
        toggleFieldOptions($row);
        $('#fields-empty').toggle($('#fields-container .field-row').length === 0);
    }

    function toggleFieldOptions($row) {
        const isSelect = $row.find('.field-type').val() === 'select';
        $row.find('.field-options-wrapper').toggleClass('d-none', !isSelect);
    }

    function setStepMode(mode, flowId, stageId, data = {}) {
        stepsForm[0].reset();
        $('.is-invalid').removeClass('is-invalid');
        $('#fields-container').empty();
        stepsForm.attr('data-mode', mode);
        stepsModal.find('.modal-title').text(mode + ' Approval Step');
        $('#step-id').val(data.id || '');
        $('#approval_flow_id').val(flowId);
        $('#stage_id').val(stageId || data.stage_id || '');
        $('#name_step').val(data.name || '');
        $('#step_order').val(data.step_order || '');
        $('#user_id').val(data.user_id || '').trigger('change');
        $('#role_id').val(data.role_id || '').trigger('change');
        $('#is_final_step').prop('checked', !!data.is_final_step);
        $('#is_special_case').prop('checked', !!data.is_special_case);
        (data.fields || []).forEach(addFieldRow);
        $('#fields-empty').toggle((data.fields || []).length === 0);
        stepsForm.find('input, textarea, select').prop('disabled', mode === 'View');
        $('#fields-container input, #fields-container select').prop('disabled', mode === 'View');
        $('#btn-add-field').toggle(mode !== 'View');
        $('#btn-save-step').toggle(mode !== 'View');
    }

    function collectStepPayload() {
        const data = {
            approval_flow_id: $('#approval_flow_id').val(),
            stage_id: $('#stage_id').val(),
            name: $('#name_step').val(),
            step_order: $('#step_order').val(),
            user_id: $('#user_id').val() || '',
            role_id: $('#role_id').val() || '',
            is_final_step: $('#is_final_step').is(':checked') ? 1 : 0,
            is_special_case: $('#is_special_case').is(':checked') ? 1 : 0,
            fields: []
        };
        $('#fields-container .field-row').each(function () {
            const label = $(this).find('.field-label').val();
            if (!label) return;
            data.fields.push({
                label: label,
                type: $(this).find('.field-type').val(),
                is_required: $(this).find('.field-required').is(':checked') ? 1 : 0,
                options: $(this).find('.field-options').val() || ''
            });
        });
        return data;
    }

    /* ---------------- child-row renderers ---------------- */
    function stagesChild(flowId, flowName) {
        return `
            <div class="p-2 bg-light rounded">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Stages of <strong>${flowName}</strong></h6>
                    <button class="btn btn-primary btn-sm rounded-pill px-3 btn-add-stage" data-flow-id="${flowId}">
                        <i class="ti ti-plus"></i>&nbsp; Add Stage
                    </button>
                </div>
                <table class="table table-sm table-bordered w-100 stages-table" id="stages-table-${flowId}">
                    <thead><tr>
                        <th width="4%"></th><th>#</th><th>Order</th><th>Stage</th><th>Rule</th><th>Steps</th><th width="15%">Action</th>
                    </tr></thead>
                </table>
            </div>`;
    }

    function stepsChild(stageId) {
        return `
            <div class="p-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-muted">Parallel approvers in this stage</h6>
                    <button class="btn btn-outline-primary btn-sm rounded-pill px-3 btn-add-step" data-stage-id="${stageId}">
                        <i class="ti ti-plus"></i>&nbsp; Add Step
                    </button>
                </div>
                <table class="table table-sm table-bordered w-100 steps-table" id="steps-table-${stageId}">
                    <thead><tr>
                        <th>#</th><th>Name</th><th>Order</th><th>User</th><th>Role</th><th>Fields</th><th width="15%">Action</th>
                    </tr></thead>
                </table>
            </div>`;
    }

    $(function () {
        $('.select2-step').select2({ placeholder: '-- Select --', allowClear: true, width: '100%', theme: 'bootstrap-5', dropdownParent: stepsModal });

        const dt = table.DataTable({
            processing: true, serverSide: true,
            ajax: routes.flowDt,
            columns: [
                { className: 'dt-control', orderable: false, data: null, defaultContent: '' },
                { data: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'name' },
                { data: 'description' },
                { data: 'stages_count', orderable: false, searchable: false, render: d => `<span class="badge bg-secondary">${d ?? 0}</span>` },
                { data: 'status', render: d => d === 'ACTIVE' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' },
                { data: 'action', orderable: false, searchable: false },
            ]
        });

        /* expand flow -> stages */
        table.on('click', 'td.dt-control', function () {
            const tr = $(this).closest('tr');
            const row = dt.row(tr);
            if (row.child.isShown()) { row.child.hide(); tr.removeClass('shown'); return; }
            const d = row.data();
            row.child(stagesChild(d.id, d.name)).show();
            tr.addClass('shown');

            const stageTable = $(`#stages-table-${d.id}`).DataTable({
                processing: true, serverSide: true,
                ajax: `${routes.stageDt}?flow_id=${d.id}`,
                columns: [
                    { className: 'dt-control-stage', orderable: false, data: null, defaultContent: '' },
                    { data: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'stage_order' },
                    { data: 'name' },
                    { data: null, orderable: false, render: r => ruleBadge(r.rule, r.quorum_count) },
                    { data: 'steps_count', orderable: false, render: d => `<span class="badge bg-info">${d ?? 0}</span>` },
                    { data: 'action', orderable: false, searchable: false },
                ]
            });

            /* expand stage -> steps */
            $(`#stages-table-${d.id} tbody`).on('click', 'td.dt-control-stage', function () {
                const str = $(this).closest('tr');
                const srow = stageTable.row(str);
                if (srow.child.isShown()) { srow.child.hide(); str.removeClass('shown'); return; }
                const sd = srow.data();
                srow.child(stepsChild(sd.id)).show();
                str.addClass('shown');

                $(`#steps-table-${sd.id}`).DataTable({
                    processing: true, serverSide: true,
                    ajax: `${routes.stepDt}?stage_id=${sd.id}`,
                    columns: [
                        { data: 'DT_RowIndex', orderable: false, searchable: false },
                        { data: 'name' },
                        { data: 'step_order' },
                        { data: 'user.name', render: d => d || '-' },
                        { data: 'role.name', render: d => d || '-' },
                        { data: 'fields_count', orderable: false, render: d => d ? `<span class="badge bg-dark">${d}</span>` : '-' },
                        { data: 'action', orderable: false, searchable: false },
                    ]
                });
            });
        });

        /* ---- FLOW actions ---- */
        $('#btn-modal').on('click', function () { setFlowMode('Create'); flowModal.modal('show'); });

        table.on('click', '.button-action', function () {
            const id = $(this).data('id'); const action = $(this).data('action');
            if (action === 'Delete') { confirmDelete(routes.flowDestroy.replace(':id', id), () => dt.ajax.reload()); return; }
            $.get(routes.flowShow.replace(':id', id), res => {
                if (res.status) { setFlowMode(action, res.data); flowModal.modal('show'); }
            });
        });

        flowForm.on('submit', function (e) {
            e.preventDefault();
            const mode = flowForm.attr('data-mode');
            const id = $('#flow-id').val();
            const url = mode === 'Edit' ? routes.flowUpdate.replace(':id', id) : routes.flowStore;
            $.ajax({
                url, method: mode === 'Edit' ? 'PUT' : 'POST', data: flowForm.serialize(),
                success(res) {
                    if (res.status) { Swal.fire('Success', res.message, 'success'); flowModal.modal('hide'); dt.ajax.reload(); }
                    else Swal.fire('Failed', res.message, 'error');
                },
                error: showValidation
            });
        });

        /* ---- STAGE actions ---- */
        $(document).on('click', '.btn-add-stage', function () { setStageMode('Create', $(this).data('flow-id')); stageModal.modal('show'); });
        $('#rule').on('change', toggleQuorum);

        $(document).on('click', '.stage-action', function () {
            const id = $(this).data('id'); const flowId = $(this).data('flow-id'); const action = $(this).data('action');
            if (action === 'Delete') { confirmDelete(routes.stageDestroy.replace(':id', id), () => $(`#stages-table-${flowId}`).DataTable().ajax.reload()); return; }
            $.get(routes.stageShow.replace(':id', id), res => {
                if (res.status) { setStageMode(action, flowId, res.data); stageModal.modal('show'); }
            });
        });

        stageForm.on('submit', function (e) {
            e.preventDefault();
            const mode = stageForm.attr('data-mode');
            const id = $('#stage-id').val();
            const flowId = $('#stage_flow_id').val();
            const url = mode === 'Edit' ? routes.stageUpdate.replace(':id', id) : routes.stageStore;
            $.ajax({
                url, method: mode === 'Edit' ? 'PUT' : 'POST', data: stageForm.serialize(),
                success(res) {
                    if (res.status) { Swal.fire('Success', res.message, 'success'); stageModal.modal('hide'); $(`#stages-table-${flowId}`).DataTable().ajax.reload(); }
                    else Swal.fire('Failed', res.message, 'error');
                },
                error: showValidation
            });
        });

        /* ---- STEP actions ---- */
        $(document).on('click', '.btn-add-step', function () {
            const stageId = $(this).data('stage-id');
            const flowId = $(this).closest('.stages-table').attr('id').replace('stages-table-', '');
            setStepMode('Create', flowId, stageId);
            stepsModal.modal('show');
        });

        $('#btn-add-field').on('click', () => addFieldRow());
        $(document).on('click', '.btn-remove-field', function () {
            $(this).closest('.field-row').remove();
            $('#fields-empty').toggle($('#fields-container .field-row').length === 0);
        });
        $(document).on('change', '.field-type', function () { toggleFieldOptions($(this).closest('.field-row')); });

        $(document).on('click', '.steps-action', function () {
            const id = $(this).data('id'); const stageId = $(this).data('stage-id'); const action = $(this).data('action');
            if (action === 'Delete') { confirmDelete(routes.stepDestroy.replace(':id', id), () => $(`#steps-table-${stageId}`).DataTable().ajax.reload()); return; }
            $.get(routes.stepShow.replace(':id', id), res => {
                if (res.status) { setStepMode(action, res.data.approval_flow_id, stageId, res.data); stepsModal.modal('show'); }
            });
        });

        stepsForm.on('submit', function (e) {
            e.preventDefault();
            const mode = stepsForm.attr('data-mode');
            const id = $('#step-id').val();
            const stageId = $('#stage_id').val();
            const url = mode === 'Edit' ? routes.stepUpdate.replace(':id', id) : routes.stepStore;
            $.ajax({
                url, method: mode === 'Edit' ? 'PUT' : 'POST', data: collectStepPayload(),
                success(res) {
                    if (res.status) { Swal.fire('Success', res.message, 'success'); stepsModal.modal('hide'); $(`#steps-table-${stageId}`).DataTable().ajax.reload(); }
                    else Swal.fire('Failed', res.message, 'error');
                },
                error: showValidation
            });
        });
    });
</script>
@endpush
