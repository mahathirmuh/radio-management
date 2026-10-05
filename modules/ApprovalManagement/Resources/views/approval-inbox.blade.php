@extends('main.index')

@push('style')
<link rel="stylesheet" href="{{ asset('assets/libs/select2/select2.min.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/libs/select2/select2-bootstrap.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/libs/datatables/datatables.min.css') }}" />
@endpush

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">My Approval Inbox</h5>
            <small class="text-muted">Items waiting on you (assigned, by role, delegated, or taken over)</small>
        </div>
        <div class="card-body">
            <table id="inbox-table" class="table table-striped table-bordered w-100">
                <thead><tr>
                    <th>#</th><th>Subject</th><th>Flow</th><th>Current Stage</th><th width="12%">Action</th>
                </tr></thead>
            </table>
        </div>
    </div>
</div>

{{-- Review modal --}}
<div class="modal fade" id="review-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Review Approval <span id="rv-status" class="badge bg-info ms-2"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rv-approval-id">
                <div class="row">
                    <div class="col-md-7">
                        <h6 class="text-muted">Progress</h6>
                        <div id="rv-timeline"></div>
                    </div>
                    <div class="col-md-5">
                        <h6 class="text-muted">History</h6>
                        <ul class="list-group list-group-flush small" id="rv-history" style="max-height:220px;overflow:auto"></ul>
                    </div>
                </div>

                {{-- Action panel (only if user can act) --}}
                <div id="rv-action-panel" class="border rounded p-3 mt-3 d-none">
                    <h6 class="mb-3">Your Decision</h6>
                    <div id="rv-fields"></div>
                    <div class="mb-2">
                        <label class="form-label">Note</label>
                        <textarea id="rv-note" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success rv-act" data-action="approve"><i class="ti ti-check"></i> Approve</button>
                        <button class="btn btn-danger rv-act" data-action="reject"><i class="ti ti-x"></i> Reject</button>
                        <button class="btn btn-warning rv-act" data-action="revise"><i class="ti ti-rotate"></i> Revise</button>
                    </div>
                </div>
                <div id="rv-noact" class="alert alert-secondary mt-3 d-none">You have no pending action on this approval.</div>

                {{-- Advanced actions --}}
                <div class="accordion mt-3" id="rv-advanced">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adv-body">
                                Advanced: insert approver / delegate / takeover
                            </button>
                        </h2>
                        <div id="adv-body" class="accordion-collapse collapse" data-bs-parent="#rv-advanced">
                            <div class="accordion-body">
                                {{-- Insert ad-hoc --}}
                                <div class="mb-3">
                                    <label class="form-label"><strong>Insert ad-hoc approver</strong></label>
                                    <div class="row g-2">
                                        <div class="col-md-4"><input id="ins-name" class="form-control form-control-sm" placeholder="Label e.g. Legal review"></div>
                                        <div class="col-md-4">
                                            <select id="ins-user" class="form-select form-select-sm select2-inbox"><option value="">-- User --</option>
                                                @foreach($users ?? [] as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <select id="ins-position" class="form-select form-select-sm">
                                                <option value="new_stage_after_current">As a new stage after current</option>
                                                <option value="into_current_stage">Into current stage (parallel)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary mt-2" id="btn-insert-step"><i class="ti ti-plus"></i> Insert</button>
                                </div>
                                <hr>
                                {{-- Delegate --}}
                                <div class="mb-3">
                                    <label class="form-label"><strong>Delegate to</strong></label>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <select id="del-user" class="form-select form-select-sm select2-inbox"><option value="">-- User --</option>
                                                @foreach($users ?? [] as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6"><button class="btn btn-sm btn-outline-secondary" id="btn-delegate"><i class="ti ti-share"></i> Delegate</button></div>
                                    </div>
                                </div>
                                <hr>
                                {{-- Takeover --}}
                                <button class="btn btn-sm btn-outline-dark" id="btn-takeover"><i class="ti ti-user-check"></i> Take over this approval</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('javascript')
<script src="{{ asset('assets/libs/select2/select2.min.js') }}"></script>
<script src="{{ asset('assets/libs/datatables/datatables.min.js') }}"></script>
<script>
    const inboxRoutes = {
        dt:         "{{ route('api.approval-management.inbox.datatable') }}",
        show:       "{{ route('api.approval-management.inbox.show', [':id']) }}",
        act:        "{{ route('api.approval-management.inbox.act', [':id']) }}",
        insertStep: "{{ route('api.approval-management.inbox.insert-step', [':id']) }}",
        delegate:   "{{ route('api.approval-management.inbox.delegate', [':id']) }}",
        takeover:   "{{ route('api.approval-management.inbox.takeover', [':id']) }}",
    };
    const reviewModal = $('#review-modal');
    let currentSchema = [];

    const statusColor = { PENDING: 'bg-info', APPROVED: 'bg-success', REJECTED: 'bg-danger', REVISED: 'bg-warning text-dark', DRAFT: 'bg-secondary', ACTIVE: 'bg-primary', SKIPPED: 'bg-secondary', DELEGATED: 'bg-secondary' };

    function badge(status) { return `<span class="badge ${statusColor[status] || 'bg-secondary'}">${status}</span>`; }

    function renderTimeline(approval) {
        let html = '';
        (approval.stage_instances || []).forEach(st => {
            html += `<div class="mb-2">
                <div class="d-flex justify-content-between">
                    <strong>#${st.stage_order} ${st.name}</strong>
                    <span>${badge(st.status)} <small class="text-muted">${st.rule}${st.rule === 'QUORUM' ? ' (' + st.quorum_count + ')' : ''}</small></span>
                </div>
                <ul class="list-unstyled ms-3 mb-0">`;
            (st.step_instances || []).forEach(step => {
                const who = (step.user && step.user.name) || (step.role && step.role.name) || step.name || 'Approver';
                html += `<li><i class="ti ti-point"></i> ${who} ${badge(step.status)}${step.is_adhoc ? ' <span class="badge bg-light text-dark">ad-hoc</span>' : ''}</li>`;
            });
            html += `</ul></div>`;
        });
        return html;
    }

    function renderHistory(approval) {
        let html = '';
        (approval.histories || []).forEach(h => {
            html += `<li class="list-group-item px-0">
                <strong>${h.action}</strong> by ${(h.user && h.user.name) || 'System'}
                ${h.note ? `<div class="text-muted">${h.note}</div>` : ''}
                <small class="text-muted">${h.created_at ? new Date(h.created_at).toLocaleString() : ''}</small>
            </li>`;
        });
        return html || '<li class="list-group-item px-0 text-muted">No history yet.</li>';
    }

    function renderFields(schema) {
        let html = '';
        (schema || []).forEach(f => {
            const req = f.is_required ? '<span class="text-danger">*</span>' : '';
            html += `<div class="mb-2"><label class="form-label">${f.label} ${req}</label>`;
            if (f.type === 'textarea') html += `<textarea class="form-control rv-field" data-key="${f.key}" data-type="textarea"></textarea>`;
            else if (f.type === 'number') html += `<input type="number" class="form-control rv-field" data-key="${f.key}" data-type="number">`;
            else if (f.type === 'date') html += `<input type="date" class="form-control rv-field" data-key="${f.key}" data-type="date">`;
            else if (f.type === 'checkbox') html += `<div class="form-check"><input type="checkbox" class="form-check-input rv-field" data-key="${f.key}" data-type="checkbox" value="1"></div>`;
            else if (f.type === 'file') html += `<input type="file" class="form-control rv-field" data-key="${f.key}" data-type="file" multiple>`;
            else if (f.type === 'select') {
                html += `<select class="form-select rv-field" data-key="${f.key}" data-type="select"><option value=""></option>`;
                (f.options || []).forEach(o => html += `<option value="${o}">${o}</option>`);
                html += `</select>`;
            } else html += `<input type="text" class="form-control rv-field" data-key="${f.key}" data-type="text">`;
            html += `</div>`;
        });
        return html || '<p class="text-muted small">No additional fields required.</p>';
    }

    function openReview(id) {
        $.get(inboxRoutes.show.replace(':id', id), res => {
            if (!res.status) { Swal.fire('Error', res.message, 'error'); return; }
            const approval = res.data.approval;
            $('#rv-approval-id').val(id);
            $('#rv-status').attr('class', 'badge ms-2 ' + (statusColor[approval.status] || 'bg-info')).text(approval.status);
            $('#rv-timeline').html(renderTimeline(approval));
            $('#rv-history').html(renderHistory(approval));

            if (res.data.can_act && res.data.my_step) {
                currentSchema = res.data.my_step.fields_schema || [];
                $('#rv-fields').html(renderFields(currentSchema));
                $('#rv-action-panel').removeClass('d-none');
                $('#rv-noact').addClass('d-none');
            } else {
                currentSchema = [];
                $('#rv-action-panel').addClass('d-none');
                $('#rv-noact').removeClass('d-none');
            }
            reviewModal.modal('show');
        });
    }

    function buildActFormData(action) {
        const fd = new FormData();
        fd.append('action', action);
        fd.append('note', $('#rv-note').val() || '');
        $('.rv-field').each(function () {
            const key = $(this).data('key');
            const type = $(this).data('type');
            if (type === 'file') {
                const files = this.files;
                for (let i = 0; i < files.length; i++) fd.append(`attachments[${key}][]`, files[i]);
            } else if (type === 'checkbox') {
                fd.append(`fields[${key}]`, $(this).is(':checked') ? '1' : '');
            } else {
                fd.append(`fields[${key}]`, $(this).val() || '');
            }
        });
        return fd;
    }

    $(function () {
        $('.select2-inbox').select2({ width: '100%', theme: 'bootstrap-5', dropdownParent: reviewModal });

        const dt = $('#inbox-table').DataTable({
            processing: true, serverSide: true,
            ajax: inboxRoutes.dt,
            columns: [
                { data: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'subject' },
                { data: 'flow_name' },
                { data: 'stage_name' },
                { data: 'action', orderable: false, searchable: false },
            ]
        });

        $('#inbox-table').on('click', '.inbox-open', function () { openReview($(this).data('id')); });

        $(document).on('click', '.rv-act', function () {
            const action = $(this).data('action');
            const id = $('#rv-approval-id').val();
            Swal.fire({
                title: 'Confirm ' + action + '?', icon: 'question', showCancelButton: true, confirmButtonText: 'Yes'
            }).then(r => {
                if (!r.isConfirmed) return;
                $.ajax({
                    url: inboxRoutes.act.replace(':id', id), method: 'POST',
                    data: buildActFormData(action), processData: false, contentType: false,
                    success(res) {
                        if (res.status) { Swal.fire('Success', res.message, 'success'); reviewModal.modal('hide'); dt.ajax.reload(); }
                        else Swal.fire('Failed', res.message, 'error');
                    },
                    error(xhr) { Swal.fire('Failed', (xhr.responseJSON && xhr.responseJSON.message) || 'Error', 'error'); }
                });
            });
        });

        function postSimple(url, data, okMsg) {
            $.ajax({
                url, method: 'POST', data,
                success(res) {
                    if (res.status) { Swal.fire('Success', res.message || okMsg, 'success'); openReview($('#rv-approval-id').val()); $('#inbox-table').DataTable().ajax.reload(); }
                    else Swal.fire('Failed', res.message, 'error');
                },
                error(xhr) { Swal.fire('Failed', (xhr.responseJSON && xhr.responseJSON.message) || 'Error', 'error'); }
            });
        }

        $('#btn-insert-step').on('click', function () {
            const id = $('#rv-approval-id').val();
            postSimple(inboxRoutes.insertStep.replace(':id', id), {
                name: $('#ins-name').val(), user_id: $('#ins-user').val(), position: $('#ins-position').val()
            }, 'Inserted');
        });

        $('#btn-delegate').on('click', function () {
            const id = $('#rv-approval-id').val();
            postSimple(inboxRoutes.delegate.replace(':id', id), { to_user_id: $('#del-user').val() }, 'Delegated');
        });

        $('#btn-takeover').on('click', function () {
            const id = $('#rv-approval-id').val();
            postSimple(inboxRoutes.takeover.replace(':id', id), {}, 'Taken over');
        });
    });
</script>
@endpush
