{{-- Print pop-up of the register list: the IT Equipment Checkout Form of one radio. The form is a page of its own, shown in a frame and printed from there. --}}
@push('style')
    <style>
        #radioPrintModal .radio-print-body {
            position: relative;
            height: calc(100vh - 12rem);
            min-height: 360px;
            background: #e9ecef;
        }

        #radioPrintModal .radio-print-body iframe {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }
    </style>
@endpush

{{-- Static: what is typed (and signed) in the form is lost when the pop-up closes, so a click beside it or the Esc key must not close it. --}}
<div class="modal fade rm-print" id="radioPrintModal" tabindex="-1" aria-hidden="true" aria-labelledby="radioPrintTitle"
    data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="rm-print-head">
                <div class="rm-dialog-titles">
                    <h3 class="rm-dialog-title" id="radioPrintTitle">Cetak IT Equipment Checkout Form</h3>
                    <div class="rm-print-label" id="radioPrintLabel"></div>
                </div>
                <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="Tutup"><i
                        class="ti ti-x"></i></button>
            </div>
            <div class="alert alert-danger rounded-0 border-0 mb-0 py-2 small d-none" id="radioPrintNotice" role="alert"></div>
            {{-- The frame with the form is made here for every form (see radioPrint.show). --}}
            <div class="modal-body p-0 radio-print-body" id="radioPrintBody"></div>
            <div class="rm-print-foot">
                <button type="button" class="btn-rm secondary" data-bs-dismiss="modal">
                    <i class="ti ti-x" aria-hidden="true"></i> Tutup
                </button>
                <button type="button" class="btn-rm primary" id="radioPrintButton" onclick="radioPrint.print()">
                    <i class="ti ti-printer" aria-hidden="true"></i> Cetak
                </button>
            </div>
        </div>
    </div>
</div>

@push('javascript')
    <script>
        const radioPrint = {
            /** The address of the form that is open; null while the pop-up is closed (a frame that loads then is not a form to print). */
            url: null,
            /** What had the focus when the pop-up was opened: it gets the focus back. */
            opener: null,
            /** The picture of the signature as it was when the form was last printed (null: none), so that closing does not ask about what is on paper. */
            printed: null,

            open(url, label) {
                radioPrint.url = url;
                radioPrint.opener = document.activeElement;
                radioPrint.printed = null;
                $('#radioPrintLabel').text(label || '');
                $('#radioPrintNotice').addClass('d-none').text('');
                $('#radioPrintButton').prop('disabled', true);
                radioPrint.show(url);
                $('#radioPrintModal').modal('show');
            },

            /** A new frame for every form: changing the address of one frame would put each form into the Back button's history. */
            show(url) {
                $('#radioPrintFrame').remove();
                const frame = document.createElement('iframe');
                frame.id = 'radioPrintFrame';
                frame.title = 'IT Equipment Checkout Form';
                frame.addEventListener('load', () => radioPrint.loaded(frame));
                frame.src = url;
                document.getElementById('radioPrintBody').appendChild(frame);
            },

            /**
             * The frame has loaded: printing is offered only when it holds the form. Anything else (the sign-in page after the
             * session has ended, the page of an error) is not left in the pop-up, where its own links and redirects would
             * run: the pop-up says what to do instead.
             */
            loaded(frame) {
                // Only the frame that is in the pop-up now is a form to look at: one that was replaced, or taken out when the pop-up closed, loads for no one.
                if (frame !== document.getElementById('radioPrintFrame')) return;
                let ok = false;
                try {
                    ok = !!frame.contentDocument.querySelector('main.sheet');
                } catch (error) {
                    ok = false;
                }
                $('#radioPrintButton').prop('disabled', !ok);
                if (ok) return;
                $('#radioPrintFrame').remove();
                $('#radioPrintNotice').removeClass('d-none').text(
                    'Form tidak bisa ditampilkan. Sesi Anda mungkin sudah berakhir, radio ini sudah dihapus, atau Anda tidak punya izin. Muat ulang halaman, lalu coba lagi.');
            },

            /** Whether the pad for the signature is open in the form of the frame (the form is a page of this application: its document can be read). */
            padOpen(frame) {
                try {
                    const pad = frame.contentDocument.getElementById('signOverlay');
                    return !!pad && !pad.hidden;
                } catch (error) {
                    return false;
                }
            },

            /** The picture of the signature in the form of the frame, or null. */
            signature(frame) {
                try {
                    const image = frame.contentDocument.querySelector('.sig-img');
                    return image ? image.getAttribute('src') : null;
                } catch (error) {
                    return null;
                }
            },

            /** Closing loses a signature that was not printed, and ink on the pad that was not taken. */
            unsaved() {
                const frame = document.getElementById('radioPrintFrame');
                if (!frame) return false;
                const signature = radioPrint.signature(frame);

                return radioPrint.padOpen(frame) || (signature !== null && signature !== radioPrint.printed);
            },

            print() {
                const frame = document.getElementById('radioPrintFrame');
                if (!frame) return;
                // What is being drawn on the pad is not on the form yet: it would be left off the paper.
                if (radioPrint.padOpen(frame)) {
                    $('#radioPrintNotice').removeClass('d-none').text('Tanda tangan belum dipakai. Tekan "Pakai tanda tangan" atau "Batal" di form dulu, lalu cetak.');
                    return;
                }
                $('#radioPrintNotice').addClass('d-none').text('');
                radioPrint.printed = radioPrint.signature(frame);
                frame.contentWindow.focus();
                frame.contentWindow.print();
            },

            closed() {
                radioPrint.url = null;
                $('#radioPrintFrame').remove();
                const opener = radioPrint.opener;
                radioPrint.opener = null;
                if (opener && document.contains(opener) && typeof opener.focus === 'function') opener.focus();
            },
        };

        $(function() {
            $('#radioPrintModal').on('hidden.bs.modal', radioPrint.closed);
            // Not by a slip: a signature that was not printed is gone when the pop-up closes.
            $('#radioPrintModal').on('hide.bs.modal', event => {
                if (radioPrint.unsaved() && !window.confirm('Tanda tangan yang belum dicetak akan hilang bila pop-up ditutup. Tetap tutup?')) event.preventDefault();
            });
            // Ctrl+P prints the form while the pop-up is open, and not the register behind it.
            $(document).on('keydown', event => {
                if (radioPrint.url !== null && (event.ctrlKey || event.metaKey) && !event.shiftKey && !event.altKey && String(event.key).toLowerCase() === 'p') {
                    event.preventDefault();
                    if (!$('#radioPrintButton').prop('disabled')) radioPrint.print();
                }
            });
        });
    </script>
@endpush
