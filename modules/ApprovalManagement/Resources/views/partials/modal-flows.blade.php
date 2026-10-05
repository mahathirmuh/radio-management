<div class="modal fade" id="flow-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="flow-form" method="POST">
                @csrf
                <input type="hidden" id="flow-id" name="id">
                <div class="modal-header">
                    <h5 class="modal-title">Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-control" required>
                        <div class="invalid-feedback" id="name-error"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-control"></textarea>
                        <div class="invalid-feedback" id="description-error"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select id="status" name="status" class="form-control select2" required>
                            <option></option>
                            <option value="ACTIVE">Active</option>
                            <option value="INACTIVE">Inactive</option>
                        </select>
                        <div class="invalid-feedback" id="status-error"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button id="btn-save" type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy"></i>&nbsp; Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
