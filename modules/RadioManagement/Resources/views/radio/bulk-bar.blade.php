{{-- The bar of the radios ticked in the list (script: radio.bulk): how many, every radio of the filter, and what may be
     done to them. A button is there only when the role may do it ($bulkMay: Update / Delete on "Register Radio"). --}}
<div class="col-12 d-none" id="radioBulkBar">
    <div class="radio-bulk-bar d-flex flex-wrap align-items-center gap-2 px-3 py-2 rounded-3">
        <i class="ti ti-checkbox text-primary fs-3"></i>
        <span class="fw-bold" id="radioBulkCount"></span>
        <a href="#" class="small d-none" id="radioBulkAll"></a>
        <a href="#" class="small text-secondary" id="radioBulkClear">Kosongkan pilihan</a>
        <span class="ms-md-auto d-flex flex-wrap gap-2">
            @if ($bulkMay['update'])
                <button type="button" class="btn btn-sm btn-success" id="radioBulkVerify">
                    <i class="ti ti-checklist me-1"></i> Verifikasi hari ini
                </button>
                <button type="button" class="btn btn-sm btn-outline-orange" id="radioBulkArchive">
                    <i class="ti ti-archive me-1"></i> Arsipkan
                </button>
            @endif
            @if ($bulkMay['delete'])
                <button type="button" class="btn btn-sm btn-outline-danger" id="radioBulkDelete">
                    <i class="ti ti-trash me-1"></i> Hapus
                </button>
            @endif
            <button type="button" class="btn btn-sm btn-outline-green" id="radioBulkExport">
                <i class="ti ti-file-spreadsheet me-1"></i> Export terpilih
            </button>
        </span>
    </div>
</div>
