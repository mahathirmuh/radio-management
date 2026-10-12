{{--
    Where a signature is drawn for E-Sign Master: a page of its own, shown in a frame of the dialog on the E-Sign Master page.
    It is the pad of the checkout form (checkout-signature), around a box of the shape of "PROCESSED BY" on that form, so that the
    signature is as big in that box as it was drawn here. There are two ways to a signature: drawing it on the pad, or giving a
    file with it (dropped on the box, or chosen), of which the ink is taken and the paper left out. This page sends nothing and
    keeps nothing: the page around the frame reads the picture out of the box when "Simpan" is pressed there.
--}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Tanda tangan ICT Person</title>
    <style>
        html,
        body {
            margin: 0;
            background: #fff;
            font: 13px/1.4 "Segoe UI", Arial, sans-serif;
            color: #212529;
        }

        /* The box is in the middle of the frame, as wide as the frame lets it be. */
        .sheet {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            min-height: 100vh;
            padding: 16px;
        }

        .hint {
            margin: 0 0 12px;
            max-width: 600px;
            font-size: 13px;
            color: #6c757d;
            text-align: center;
        }

        .grid {
            border-collapse: collapse;
        }

        /* The shape of the box "PROCESSED BY" of the form (about two to one): the signature is fitted into that box, whatever size it is drawn at here. */
        .grid td.sign {
            /* As wide as the frame is, and no taller than leaves room for the words above and the button below: nothing scrolls. */
            --box: min(600px, calc(100vw - 32px), calc((100vh - 190px) * 76 / 36));
            width: var(--box);
            height: calc(var(--box) * 36 / 76);
            padding: 0;
            border: 2px dashed #adb5bd;
            border-radius: 6px;
            background: #fff;
        }

        /* Signed: the frame of the box is a plain line, as on the form. */
        .grid td.sign.signed {
            border: 1px solid #212529;
            border-radius: 0;
        }

        @include('RadioManagement::radio.checkout-signature-css')

        .sig-open {
            font-size: 16px;
            color: #495057;
        }

        /* On the form the box lights up yellow, like a field of the paper; here it is a place to click or to drop a file on. */
        .sig-open:hover,
        .sig-open:focus-visible {
            background-color: rgba(32, 107, 196, .06);
            outline: 2px solid #206bc4;
            outline-offset: -2px;
        }

        .sig-open small {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #868e96;
        }

        .signed .sig-open small {
            color: transparent;
        }

        /* A file is being dragged over the page: the box says that it takes it. */
        .grid td.sign.dropping {
            border-color: #206bc4;
            background: #e7f1ff;
        }

        .upload {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 12px 0 0;
            font-size: 13px;
            color: #6c757d;
        }

        .upload label {
            display: inline-flex;
            align-items: center;
            min-height: 34px;
            padding: 0 14px;
            border: 1px solid #206bc4;
            border-radius: 6px;
            background: #fff;
            color: #206bc4;
            cursor: pointer;
        }

        .upload label:hover,
        .upload input:focus-visible + label {
            background: #206bc4;
            color: #fff;
        }

        /* The input is there for the keyboard and the screen reader, and not to be seen: its label is the button. */
        .upload input {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
            overflow: hidden;
        }

        .upload-note {
            margin: 8px 0 0;
            max-width: 600px;
            font-size: 12px;
            color: #c92a2a;
            text-align: center;
        }

        .upload-note[hidden] {
            display: none;
        }

        /* The button that takes the signature out again is bigger here than on the form, where it must not cover the paper. */
        .sig-remove {
            top: 6px;
            right: 6px;
            width: 26px;
            height: 26px;
            font-size: 17px;
        }
    </style>
</head>

<body>
    <main class="sheet">
        <p class="hint">Dua cara: <b>klik kotak</b> untuk tanda tangan di layar (pena, jari, atau mouse), atau <b>seret &amp; lepas / unggah file</b> gambar tanda tangan.</p>
        <table class="grid">
            <tr>
                <td class="sign" id="signCell" data-today="{{ $today }}">
                    <button type="button" class="sig-open" id="signOpen" aria-label="Tanda tangan ICT Person di layar">Klik untuk tanda tangan<small>atau seret &amp; lepas file gambar tanda tangan ke sini</small></button>
                    <button type="button" class="sig-remove" id="signRemove" aria-label="Hapus tanda tangan" title="Hapus tanda tangan" hidden>&times;</button>
                </td>
            </tr>
        </table>
        <div class="upload">
            <span>atau</span>
            <input type="file" id="signFile" accept="image/png,image/jpeg">
            <label for="signFile">Unggah file tanda tangan</label>
            <span>PNG atau JPG, tinta gelap di latar putih</span>
        </div>
        <p class="upload-note" id="uploadNote" role="alert" hidden></p>
    </main>

    @include('RadioManagement::radio.checkout-signature', ['signWho' => 'ICT Person'])

    {{-- The second way: a file with the signature (a scan, a photo, a picture from another program), dropped on the box or chosen. --}}
    <script>
        (function() {
            const cell = document.getElementById('signCell');
            const input = document.getElementById('signFile');
            const note = document.getElementById('uploadNote');
            const remove = document.getElementById('signRemove');
            const open = document.getElementById('signOpen');
            if (!cell || !input) return;

            /** The longer side of the picture that is kept, as the pad makes it. */
            const PICTURE = 1200;
            const MOST_BYTES = 5 * 1024 * 1024;
            /** How much darker than white a point has to be to count as ink (of 255), and from where on it is solid ink. */
            const PAPER = 40;
            const SOLID = 160;

            const say = text => {
                note.textContent = text;
                note.hidden = text === '';
            };

            /** Puts the picture into the box, as the pad does: before the buttons, with the button that takes it out again. */
            function place(url) {
                let image = cell.querySelector('.sig-img');
                if (!image) {
                    image = document.createElement('img');
                    image.className = 'sig-img';
                    image.alt = 'Tanda tangan ICT Person';
                    cell.insertBefore(image, cell.firstChild);
                }
                image.src = url;
                cell.classList.add('signed');
                remove.hidden = false;
                open.setAttribute('aria-label', 'Ganti tanda tangan ICT Person');
            }

            /**
             * The signature of a picture: its dark points as ink on nothing (the paper it was written on, white or light, is left
             * out, so that the lines of the form show through around it), cut to where the ink is. Null when there is no ink.
             */
            function inkOf(picture) {
                const scale = Math.min(1, PICTURE / Math.max(picture.naturalWidth, picture.naturalHeight));
                const width = Math.max(1, Math.round(picture.naturalWidth * scale));
                const height = Math.max(1, Math.round(picture.naturalHeight * scale));
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const context = canvas.getContext('2d', { willReadFrequently: true });
                context.drawImage(picture, 0, 0, width, height);
                const pixels = context.getImageData(0, 0, width, height);
                const data = pixels.data;
                let [left, top, right, bottom] = [width, height, -1, -1];
                for (let y = 0; y < height; y++) {
                    for (let x = 0; x < width; x++) {
                        const i = (y * width + x) * 4;
                        // A point that is see-through already is paper; one that is half so is that much lighter.
                        const solid = data[i + 3] / 255;
                        const light = (0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2]) * solid + 255 * (1 - solid);
                        const dark = 255 - light;
                        const ink = dark <= PAPER ? 0 : Math.min(255, Math.round((dark - PAPER) * 255 / (SOLID - PAPER)));
                        data[i] = data[i + 1] = data[i + 2] = 16;
                        data[i + 3] = ink;
                        if (ink > 96) {
                            if (x < left) left = x;
                            if (x > right) right = x;
                            if (y < top) top = y;
                            if (y > bottom) bottom = y;
                        }
                    }
                }
                if (right - left < 8 || bottom - top < 4) return null;
                context.putImageData(pixels, 0, 0);

                const margin = Math.round(Math.max(right - left, bottom - top) * 0.04);
                const out = document.createElement('canvas');
                out.width = right - left + 1 + 2 * margin;
                out.height = bottom - top + 1 + 2 * margin;
                out.getContext('2d').drawImage(canvas, left, top, right - left + 1, bottom - top + 1, margin, margin, right - left + 1, bottom - top + 1);

                return out.toDataURL('image/png');
            }

            /** Which file is the one that counts: reading a picture takes a moment, and what an earlier file comes to is not shown over a later one. */
            let latest = 0;

            function take(file) {
                const mine = ++latest;
                say('');
                if (!file) return;
                if (!/^image\/(png|jpeg)$/.test(file.type)) {
                    say('File harus berupa gambar PNG atau JPG.');
                    return;
                }
                if (file.size > MOST_BYTES) {
                    say('File terlalu besar (paling besar 5 MB).');
                    return;
                }
                const reader = new FileReader();
                reader.onerror = () => {
                    if (mine === latest) say('File itu tidak bisa dibaca.');
                };
                reader.onload = () => {
                    if (mine !== latest) return;
                    const picture = document.createElement('img');
                    picture.onerror = () => {
                        if (mine === latest) say('File itu bukan gambar yang bisa dibaca.');
                    };
                    picture.onload = () => {
                        if (mine !== latest) return;
                        const url = inkOf(picture);
                        if (url === null) {
                            say('Tidak ada tanda tangan yang terlihat di gambar itu. Tintanya harus gelap di atas latar putih atau terang.');
                            return;
                        }
                        place(url);
                    };
                    picture.src = reader.result;
                };
                reader.readAsDataURL(file);
            }

            input.addEventListener('change', () => {
                take(input.files && input.files[0]);
                // The same file can be chosen again after it was taken out.
                input.value = '';
            });

            // A file that is dragged over the page is for the box: nowhere on the page does the browser open it instead.
            let over = 0;
            const carriesFile = event => !!event.dataTransfer && Array.from(event.dataTransfer.types || []).includes('Files');
            document.addEventListener('dragenter', event => {
                if (!carriesFile(event)) return;
                event.preventDefault();
                over++;
                cell.classList.add('dropping');
            });
            document.addEventListener('dragover', event => {
                if (carriesFile(event)) event.preventDefault();
            });
            document.addEventListener('dragleave', () => {
                over = Math.max(0, over - 1);
                if (over === 0) cell.classList.remove('dropping');
            });
            document.addEventListener('drop', event => {
                event.preventDefault();
                over = 0;
                cell.classList.remove('dropping');
                // Not while the pad is open: what is being drawn there is what the person is doing.
                const pad = document.getElementById('signOverlay');
                if (pad && !pad.hidden) return;
                take(event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0]);
            });
            // Taken out, or drawn on the pad instead: a file that is still being read is not put in afterwards.
            remove.addEventListener('click', () => {
                latest++;
                say('');
            });
            open.addEventListener('click', () => {
                latest++;
                say('');
            });
        })();
    </script>
</body>

</html>
