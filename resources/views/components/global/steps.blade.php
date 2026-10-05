@push('style')
    <style type="text/css">
        /* Stepper navigation */
        .mcg-steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: nowrap;
            gap: 14px;
        }

        .mcg-step-divider {
            border: 1px dashed #000000;
            width: 20em;
            /* 20 times the font-size of the parent element */
            margin-left: auto;
            margin-right: auto;
            margin-top: auto;
            margin-bottom: auto;
        }
    </style>
@endpush

<div class="mcg-steps-container">
    <div class="mcg-steps">
        {{ $slot }}
    </div>
</div>

@push('javascript')
    <script>
        // Show a divider between each step
        const steps = $('.mcg-step');
        steps.each(function(index) {
            if (index < steps.length - 1) {
                const divider = $('<hr>').addClass('mcg-step-divider');
                $(this).after(divider);
            }
        });
    </script>
@endpush
