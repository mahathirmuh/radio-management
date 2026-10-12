{{--
    The style of signing on the screen: the box that is signed in, the picture in it, and the pad. Included inside the <style> of a page
    that has the pad (checkout-signature): the form, and the page where a signature is registered for E-Sign Master.
--}}
        /* ---- signing on the screen: the box of RESPONSIBLE USER opens a pad, and what is drawn goes into the box as a picture */
        .grid td.sign {
            position: relative;
        }

        .sig-open {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            border: 0;
            background: transparent;
            font: 9px/1.2 "Segoe UI", Arial, sans-serif;
            color: #adb5bd;
            cursor: pointer;
        }

        .signed .sig-open {
            color: transparent;
        }

        .sig-open:hover,
        .sig-open:focus-visible {
            background-color: rgba(255, 224, 138, .35);
            outline: 1px dashed #bd6147;
            outline-offset: -2px;
        }

        .sig-img {
            position: absolute;
            inset: 1mm;
            width: calc(100% - 2mm);
            height: calc(100% - 2mm);
            object-fit: contain;
        }

        .sig-remove {
            position: absolute;
            top: 1px;
            right: 1px;
            z-index: 2;
            width: 18px;
            height: 18px;
            padding: 0;
            border: 1px solid #ced4da;
            border-radius: 50%;
            background: #fff;
            color: #495057;
            font: 13px/1 "Segoe UI", Arial, sans-serif;
            cursor: pointer;
        }

        .sig-remove[hidden] {
            display: none;
        }

        .sig-overlay {
            position: fixed;
            inset: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(33, 37, 41, .55);
            font: 14px/1.4 "Segoe UI", Arial, sans-serif;
        }

        .sig-overlay[hidden] {
            display: none;
        }

        .sig-panel {
            padding: 14px 16px 16px;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 8px 30px rgba(0, 0, 0, .35);
            outline: 0;
        }

        .sig-panel h2 {
            margin: 0;
            font-size: 16px;
        }

        .sig-panel p {
            margin: 2px 0 10px;
            font-size: 12px;
            color: #6c757d;
        }

        .sig-surface {
            position: relative;
            border: 2px solid #ced4da;
            border-radius: 6px;
            background: #fff;
            line-height: 0;
        }

        .sig-surface canvas {
            display: block;
            touch-action: none;
            -webkit-user-select: none;
            user-select: none;
            cursor: crosshair;
        }

        /* The line to sign on is part of the pad's page, never of the picture. */
        .sig-line {
            position: absolute;
            left: 4%;
            right: 4%;
            bottom: 24%;
            border-bottom: 1px dashed #adb5bd;
            pointer-events: none;
        }

        .sig-note {
            margin: 8px 0 0;
            font-size: 12px;
            color: #c92a2a;
        }

        .sig-note[hidden] {
            display: none;
        }

        .sig-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 8px;
            margin-top: 12px;
        }

        .sig-actions button {
            min-height: 40px;
            padding: 0 16px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            background: #fff;
            color: #212529;
            font: inherit;
            cursor: pointer;
        }

        .sig-actions button.primary {
            border-color: #206bc4;
            background: #206bc4;
            color: #fff;
        }

        .sig-actions button:disabled {
            opacity: .45;
            cursor: default;
        }
