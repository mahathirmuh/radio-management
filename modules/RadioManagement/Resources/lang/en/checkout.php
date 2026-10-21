<?php

// What the screen says around the printed IT Equipment Checkout Form (radio.checkout-form; the form itself is the
// company's, in English, and is not translated), and the pad to sign on the screen (radio.checkout-signature, also on
// the page of E-Sign Master).

return [
    'hint' => 'Click a field to change it before printing, and click words such as "HT" or "RIG" to strike them out (click again to undo). Click the signature box of RESPONSIBLE USER to sign on the screen (pen, finger or mouse). Changes are for this printout only and are not saved.',
    'too_long' => 'The form is longer than one A4 page: when printed it is shrunk to <b id="fitScale"></b>% so that it stays on one page. Shorten the fields to keep it readable.',
    'sign_open' => 'Click to sign',
    'sign_remove' => 'Remove the signature',

    'pad' => [
        'title' => 'Signature of :who',
        'help' => 'Sign inside the box with a pen, a finger or a mouse.',
        'box' => 'Signature box',
        'not_used' => 'The signature has not been used yet. Press "Use signature" or "Cancel" first.',
        'undo' => 'Undo',
        'clear' => 'Clear',
        'cancel' => 'Cancel',
        'apply' => 'Use signature',
        'picture' => 'Signature of :who',
        'change' => 'Change the signature of :who',
        'sign' => 'Sign for :who on the screen',
    ],
];
