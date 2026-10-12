{{--
    Signing the form on the screen. The box of RESPONSIBLE USER opens a pad; what is drawn on it (with a pen, a finger or a mouse)
    goes into the box as a picture that is printed with the form. Nothing is saved and nothing is sent anywhere: the picture
    lives in this page until it is closed. (The box of the ICT person is filled from E-Sign Master, or signed on paper.)

    $signWho: whose signature it is, in the words of the pad ("Responsible User" unless the page says otherwise: the page where a
    signature is registered for E-Sign Master includes this pad too).
--}}
@php($signWho = $signWho ?? 'Responsible User')
<div class="sig-overlay no-print" id="signOverlay" role="dialog" aria-modal="true" aria-labelledby="signTitle" hidden>
    <div class="sig-panel" tabindex="-1">
        <h2 id="signTitle">Tanda tangan {{ $signWho }}</h2>
        <p>Tanda tangan di dalam kotak dengan pena, jari, atau mouse.</p>
        <div class="sig-surface">
            <canvas id="signCanvas" aria-label="Kotak tanda tangan"></canvas>
            <span class="sig-line"></span>
        </div>
        <p class="sig-note" id="signNote" role="alert" hidden>Tanda tangan belum dipakai. Tekan "Pakai tanda tangan" atau "Batal" dulu.</p>
        <div class="sig-actions">
            <span>
                <button type="button" id="signUndo" disabled>Urungkan</button>
                <button type="button" id="signClear" disabled>Hapus</button>
            </span>
            <span>
                <button type="button" id="signCancel">Batal</button>
                <button type="button" class="primary" id="signApply" disabled>Pakai tanda tangan</button>
            </span>
        </div>
    </div>
</div>

<script>
    (function() {
        const cell = document.getElementById('signCell');
        const overlay = document.getElementById('signOverlay');
        if (!cell || !overlay) return;

        const sheet = document.querySelector('main.sheet');
        const panel = overlay.querySelector('.sig-panel');
        const canvas = document.getElementById('signCanvas');
        const open = document.getElementById('signOpen');
        const remove = document.getElementById('signRemove');
        const undo = document.getElementById('signUndo');
        const clear = document.getElementById('signClear');
        const cancel = document.getElementById('signCancel');
        const apply = document.getElementById('signApply');
        const note = document.getElementById('signNote');
        const context = canvas.getContext('2d');

        /** Whose signature it is, for the names of the box and the picture. */
        const WHO = @json($signWho);
        const INK = '#101010';
        /** The pen is this wide on the pad, as a share of the pad's width; a pen that presses harder draws wider, a lighter touch narrower. */
        const PEN = 0.005;
        /** What is drawn is a signature when it is at least this long (as a share of the pad's width): a tap, or a speck, is none. */
        const LEAST = 0.06;
        /** The picture has this many pixels along its longer side, and its ink is as thick as a pen of 0.4 mm in a box of 38 mm. */
        const PICTURE = 1200;
        const INK_SHARE = 0.4 / 38;
        /** The picture is shown 1 mm inside the box on every side (the CSS of .sig-img), in CSS pixels. */
        const INSET = 2 * 96 / 25.4;
        /** A finger that touches down within this many milliseconds of a pen (even one that only hovers) is a hand resting on the screen. */
        const PALM = 2500;

        // A stroke is what a pen draws from touching down to lifting: points { x, y, p }, x and y in widths of the pad (so that the
        // signature can be drawn again at any size), p the pressure from 0 to 1 (a mouse or a finger has none: 0.5).
        let strokes = [];
        let drawing = null;
        /** When a pen was last seen, by the time of its events. */
        let penAt = -Infinity;
        let padWidth = 0;
        let ratio = 2.4;
        let dated = false;

        const weight = pressure => 0.5 + pressure;

        /**
         * Draws a stroke from its point number $from on, as curves that pass through the middles between the points (a polygon of
         * the points would show every jitter of a mouse). While the pen is down the last bit is left to the next point; $final draws it.
         */
        function paintStroke(target, stroke, frame, from, final) {
            const points = stroke.points;
            const at = i => ({ x: frame.left + points[i].x * frame.scale, y: frame.top + points[i].y * frame.scale });
            const middle = (a, b) => ({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 });
            target.strokeStyle = INK;
            target.fillStyle = INK;
            target.lineCap = 'round';
            target.lineJoin = 'round';

            if (points.length === 1) {
                const dot = at(0);
                target.beginPath();
                target.arc(dot.x, dot.y, frame.pen * weight(points[0].p) / 2, 0, 2 * Math.PI);
                target.fill();
                return;
            }
            for (let i = Math.max(1, from); i < points.length; i++) {
                const before = at(i - 1);
                const start = i === 1 ? before : middle(at(i - 2), before);
                const end = final && i === points.length - 1 ? at(i) : middle(before, at(i));
                target.beginPath();
                target.moveTo(start.x, start.y);
                target.quadraticCurveTo(before.x, before.y, end.x, end.y);
                target.lineWidth = frame.pen * weight(points[i - 1].p);
                target.stroke();
            }
        }

        const frame = () => ({ scale: padWidth, left: 0, top: 0, pen: PEN * padWidth });

        function paintAll() {
            context.clearRect(0, 0, padWidth, padWidth / ratio);
            strokes.forEach(stroke => paintStroke(context, stroke, frame(), 0, !drawing || drawing.stroke !== stroke));
        }

        /** The corners of the ink in widths of the pad; null when there is none. */
        function extent() {
            const points = strokes.flatMap(stroke => stroke.points);
            if (!points.length) return null;
            const box = points.reduce((all, point) => ({
                left: Math.min(all.left, point.x), top: Math.min(all.top, point.y),
                right: Math.max(all.right, point.x), bottom: Math.max(all.bottom, point.y),
            }), { left: Infinity, top: Infinity, right: -Infinity, bottom: -Infinity });

            return { ...box, width: box.right - box.left, height: box.bottom - box.top };
        }

        /** How much ink there is, as a length in widths of the pad. */
        function inkLength() {
            let length = 0;
            strokes.forEach(stroke => stroke.points.forEach((point, i) => {
                if (i) length += Math.hypot(point.x - stroke.points[i - 1].x, point.y - stroke.points[i - 1].y);
            }));

            return length;
        }

        function update() {
            apply.disabled = inkLength() < LEAST;
            undo.disabled = clear.disabled = strokes.length === 0;
            // A button that turns itself off does not keep the key: it goes to the pad.
            if ((document.activeElement === undo || document.activeElement === clear) && undo.disabled) panel.focus();
        }

        /**
         * The pad is as big as the window allows and has the shape of the box it is for, so that a signature is as big in the box
         * as it is on the pad. Its drawing area has more pixels than the screen shows, for the sake of the paper.
         */
        function size() {
            const box = cell.getBoundingClientRect();
            ratio = Math.min(3, Math.max(1.4, box.width / Math.max(1, box.height)));
            padWidth = Math.round(Math.max(260, Math.min(window.innerWidth - 56, 900, (window.innerHeight - 210) * ratio)));
            const height = Math.round(padWidth / ratio);
            const density = Math.max(2, window.devicePixelRatio || 1);
            canvas.style.width = padWidth + 'px';
            canvas.style.height = height + 'px';
            canvas.width = Math.round(padWidth * density);
            canvas.height = Math.round(height * density);
            context.setTransform(density, 0, 0, density, 0, 0);
            paintAll();
        }

        const clamp = (value, least, most) => Math.min(most, Math.max(least, value));

        function point(event) {
            const box = canvas.getBoundingClientRect();

            return {
                x: clamp((event.clientX - box.left) / padWidth, 0, 1),
                y: clamp((event.clientY - box.top) / padWidth, 0, 1 / ratio),
                // Only a pen knows how hard it presses.
                p: event.pointerType === 'pen' && event.pressure > 0 ? event.pressure : 0.5,
            };
        }

        const palm = event => event.pointerType === 'touch' && event.timeStamp - penAt < PALM;

        canvas.addEventListener('pointerdown', event => {
            if (event.pointerType === 'pen') penAt = event.timeStamp;
            // A stroke whose pointer lost its hold without a word (the up never came) is as far as it got: it does not keep the pad from drawing.
            if (drawing && typeof canvas.hasPointerCapture === 'function' && !canvas.hasPointerCapture(drawing.id)) lift({ pointerId: drawing.id });
            // One pointer draws at a time. The barrel button of a pen, its eraser end and the right button of a mouse draw nothing,
            // and while a pen is near a hand that rests on the screen draws nothing either.
            if (drawing || event.button !== 0 || palm(event)) return;
            event.preventDefault();
            try {
                canvas.setPointerCapture(event.pointerId);
            } catch (error) {
                // The pointer is gone already: there is nothing to follow.
            }
            const stroke = { points: [point(event)] };
            strokes.push(stroke);
            drawing = { id: event.pointerId, stroke };
            paintStroke(context, stroke, frame(), 0, false);
            update();
        });

        canvas.addEventListener('pointermove', event => {
            if (event.pointerType === 'pen') penAt = event.timeStamp;
            if (!drawing || event.pointerId !== drawing.id) return;
            event.preventDefault();
            const from = drawing.stroke.points.length;
            // A pen reports many more positions than there are events: the ones in between are what makes a curve smooth.
            const moves = typeof event.getCoalescedEvents === 'function' ? event.getCoalescedEvents() : [];
            (moves.length ? moves : [event]).forEach(move => drawing.stroke.points.push(point(move)));
            paintStroke(context, drawing.stroke, frame(), from, false);
            update();
        });

        const lift = event => {
            if (!drawing || event.pointerId !== drawing.id) return;
            paintStroke(context, drawing.stroke, frame(), drawing.stroke.points.length - 1, true);
            drawing = null;
            update();
        };
        canvas.addEventListener('pointerup', lift);
        canvas.addEventListener('lostpointercapture', lift);
        // What the system cancels (a palm, a gesture of the driver) is no ink: that stroke goes.
        canvas.addEventListener('pointercancel', event => {
            if (!drawing || event.pointerId !== drawing.id) return;
            strokes = strokes.filter(stroke => stroke !== drawing.stroke);
            drawing = null;
            paintAll();
            update();
        });
        // The barrel button of a pen, or pressing and holding it, asks for a menu.
        canvas.addEventListener('contextmenu', event => event.preventDefault());

        undo.addEventListener('click', () => {
            strokes.pop();
            paintAll();
            update();
        });
        clear.addEventListener('click', () => {
            strokes = [];
            paintAll();
            update();
        });

        /** The signature as a picture: the ink only, on nothing, fitted to the box and as thick as a pen on paper. */
        function picture() {
            const box = extent();
            if (box === null || inkLength() < LEAST) return null;
            const margin = Math.max(box.width, box.height) * 0.04;
            const width = box.width + 2 * margin;
            const height = box.height + 2 * margin;
            const scale = PICTURE / Math.max(width, height);
            const out = document.createElement('canvas');
            out.width = Math.max(1, Math.round(width * scale));
            out.height = Math.max(1, Math.round(height * scale));
            // The picture is fitted into the box, 1 mm inside it: how many screen pixels one of its pixels takes up tells how wide the pen must be.
            const room = cell.getBoundingClientRect();
            const boxWidth = Math.max(1, room.width - INSET);
            const boxHeight = Math.max(1, room.height - INSET);
            const shown = Math.min(boxWidth / out.width, boxHeight / out.height);
            const pen = shown > 0 ? boxWidth * INK_SHARE / shown : PICTURE * 0.01;
            const target = out.getContext('2d');
            const where = { scale, left: (margin - box.left) * scale, top: (margin - box.top) * scale, pen };
            strokes.forEach(stroke => paintStroke(target, stroke, where, 0, true));

            return out.toDataURL('image/png');
        }

        const dateField = () => document.querySelector('[data-field="user_date"]');

        function place(url) {
            let image = cell.querySelector('.sig-img');
            if (!image) {
                image = document.createElement('img');
                image.className = 'sig-img';
                image.alt = 'Tanda tangan ' + WHO;
                cell.insertBefore(image, cell.firstChild);
            }
            image.src = url;
            cell.classList.add('signed');
            remove.hidden = false;
            open.setAttribute('aria-label', 'Ganti tanda tangan ' + WHO);
            // Signed today, unless a date was written in already.
            const date = dateField();
            if (date && date.textContent.trim() === '') {
                date.textContent = cell.getAttribute('data-today');
                dated = true;
            }
        }

        function show() {
            strokes = [];
            drawing = null;
            penAt = -Infinity;
            note.hidden = true;
            overlay.hidden = false;
            // Nothing behind the pad can be clicked, typed in or reached with the Tab key, and the page does not scroll.
            sheet.inert = true;
            document.documentElement.style.overflow = 'hidden';
            size();
            update();
            panel.focus();
        }

        function close() {
            drawing = null;
            note.hidden = true;
            overlay.hidden = true;
            sheet.inert = false;
            document.documentElement.style.overflow = '';
            open.focus();
        }

        open.addEventListener('click', show);
        cancel.addEventListener('click', close);
        apply.addEventListener('click', () => {
            const url = picture();
            if (url === null) return;
            place(url);
            close();
        });
        remove.addEventListener('click', () => {
            const image = cell.querySelector('.sig-img');
            if (image) image.remove();
            cell.classList.remove('signed');
            remove.hidden = true;
            open.setAttribute('aria-label', 'Tanda tangan ' + WHO + ' di layar');
            // The date that signing wrote is taken back, one that was written or changed by hand is left.
            const date = dateField();
            if (dated && date && date.textContent === cell.getAttribute('data-today')) date.textContent = '';
            dated = false;
            // The button that was pressed is gone: the key goes to the box.
            open.focus();
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !overlay.hidden) {
                event.preventDefault();
                close();
            }
        });
        window.addEventListener('resize', () => {
            if (!overlay.hidden) size();
        });
    })();
</script>
