{{-- The bar of the radios ticked in the list (script: radio.bulk): how many, every radio of the filter, and what may be
     done to them. A button is there only when the role may do it ($bulkMay: Update / Delete on "Register Radio"). --}}
<div class="rm-bulk d-none" id="radioBulkBar">
    <i class="ti ti-circle-check" aria-hidden="true"></i>
    <span class="rm-bulk-count" id="radioBulkCount"></span>
    <a href="#" class="rm-bulk-all d-none" id="radioBulkAll"></a>
    <a href="#" class="rm-bulk-clear" id="radioBulkClear">{{ __('RadioManagement::register.bulk.clear') }}</a>
    <span class="rm-bulk-actions">
        @if ($bulkMay['update'])
            <button type="button" class="btn-rm sm success" id="radioBulkVerify">{{ __('RadioManagement::register.bulk.verify_today') }}</button>
            <button type="button" class="btn-rm sm secondary" id="radioBulkArchive">{{ __('RadioManagement::register.bulk.archive') }}</button>
        @endif
        @if ($bulkMay['delete'])
            <button type="button" class="btn-rm sm destructive" id="radioBulkDelete">{{ __('RadioManagement::register.bulk.delete') }}</button>
        @endif
        <button type="button" class="btn-rm sm secondary" id="radioBulkExport">{{ __('RadioManagement::register.bulk.export') }}</button>
    </span>
</div>
