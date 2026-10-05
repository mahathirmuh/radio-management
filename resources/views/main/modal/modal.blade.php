<!-- Upload Loader -->
<div id="upload-loader" class="modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body text-center p-5">
                <h3 class="mb-0">
                    Processing Your Document
                </h3>
                <img class="w-50 my-1" src="{{ asset('assets/img/property/upload-animation.gif') }}" alt="upload-gif">
                <p class="text-muted">We’re handling your upload. Don’t leave just yet to avoid losing your
                    progress.</p>
            </div>
        </div>
    </div>
</div>

<!-- Download Loader -->
<div id="download-loader" class="modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body text-center p-5">
                <h3 class="mb-0">
                    Processing Your Document
                </h3>
                <img class="w-100 my-5" src="{{ asset('assets/img/property/download-animation.gif') }}"
                    alt="upload-gif">
                <p class="text-muted">Almost there! We're fetching your data. To avoid delays, kindly remain on this
                    page.</p>
            </div>
        </div>
    </div>
</div>
