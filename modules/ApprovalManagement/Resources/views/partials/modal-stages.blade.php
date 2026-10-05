<div class="modal fade" id="stage-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Stage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="stage-form" data-mode="Create">
                <div class="modal-body">
                    <input type="hidden" id="stage-id">
                    <input type="hidden" id="stage_flow_id" name="approval_flow_id">

                    <div class="mb-3">
                        <label for="stage_name" class="form-label">Stage Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="stage_name" name="name" required>
                        <div id="name-error" class="invalid-feedback"></div>
                    </div>

                    <div class="mb-3">
                        <label for="stage_order" class="form-label">Stage Order <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="stage_order" name="stage_order" min="1" required>
                        <small class="text-muted">Stages run sequentially from the smallest order.</small>
                        <div id="stage_order-error" class="invalid-feedback"></div>
                    </div>

                    <div class="mb-3">
                        <label for="rule" class="form-label">Approval Rule <span class="text-danger">*</span></label>
                        <select class="form-select" id="rule" name="rule" required>
                            <option value="ALL">ALL — every approver in this stage must approve</option>
                            <option value="ANY">ANY — one approver is enough</option>
                            <option value="QUORUM">QUORUM — at least N approvers</option>
                        </select>
                        <div id="rule-error" class="invalid-feedback"></div>
                    </div>

                    <div class="mb-3 d-none" id="quorum-wrapper">
                        <label for="quorum_count" class="form-label">Quorum Count <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="quorum_count" name="quorum_count" min="1">
                        <div id="quorum_count-error" class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" id="btn-save-stage" class="btn btn-primary">
                        <i class="ti ti-device-floppy"></i>&nbsp; Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
