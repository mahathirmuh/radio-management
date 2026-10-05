<div class="modal fade" id="steps-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Approval Step</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="steps-form" data-mode="Create">
                <div class="modal-body">
                    <input type="hidden" id="step-id">
                    <input type="hidden" id="approval_flow_id" name="approval_flow_id">
                    <input type="hidden" id="stage_id" name="stage_id">

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="name_step" class="form-label">Step Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name_step" name="name" required>
                            <div id="name-error" class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="step_order" class="form-label">Order in Stage <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="step_order" name="step_order" min="1" required>
                            <div id="step_order-error" class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="user_id" class="form-label">Approver User</label>
                            <select class="form-select select2-step" id="user_id" name="user_id">
                                <option value=""></option>
                                @foreach($users ?? [] as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                            <div id="user_id-error" class="invalid-feedback"></div>
                            <small class="text-muted">Either select a user or a role</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="role_id" class="form-label">Approver Role</label>
                            <select class="form-select select2-step" id="role_id" name="role_id">
                                <option value=""></option>
                                @foreach($roles ?? [] as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                            <div id="role_id-error" class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="d-flex gap-4 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_final_step" name="is_final_step" value="1">
                            <label class="form-check-label" for="is_final_step">Is Final Step</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_special_case" name="is_special_case" value="1">
                            <label class="form-check-label" for="is_special_case">Is Special Case</label>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0"><strong>Custom Fields</strong> <small class="text-muted">(what this approver must fill / attach)</small></label>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-field">
                            <i class="ti ti-plus"></i> Add Field
                        </button>
                    </div>
                    <div id="fields-container"></div>
                    <div id="fields-empty" class="text-muted small">No custom fields. This approver only needs to approve/reject.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" id="btn-save-step" class="btn btn-primary">
                        <i class="ti ti-device-floppy"></i>&nbsp; Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Template for one custom-field row (cloned by JS) --}}
<template id="field-row-template">
    <div class="field-row border rounded p-2 mb-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label mb-0 small">Label</label>
                <input type="text" class="form-control form-control-sm field-label" placeholder="e.g. Supporting Document">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-0 small">Type</label>
                <select class="form-select form-select-sm field-type">
                    <option value="text">Text</option>
                    <option value="textarea">Textarea</option>
                    <option value="number">Number</option>
                    <option value="date">Date</option>
                    <option value="select">Dropdown</option>
                    <option value="checkbox">Checkbox</option>
                    <option value="file">File Upload</option>
                </select>
            </div>
            <div class="col-md-3 field-options-wrapper d-none">
                <label class="form-label mb-0 small">Options (comma separated)</label>
                <input type="text" class="form-control form-control-sm field-options" placeholder="Low, Medium, High">
            </div>
            <div class="col-md-1">
                <div class="form-check mt-3">
                    <input class="form-check-input field-required" type="checkbox">
                    <label class="form-check-label small">Req</label>
                </div>
            </div>
            <div class="col-md-1 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-field"><i class="ti ti-trash"></i></button>
            </div>
        </div>
    </div>
</template>
