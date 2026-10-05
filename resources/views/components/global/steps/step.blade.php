@props([
    'number' => 1,
    'label' => 'Step 1',
    'active' => false,
    'passed' => false,
])
@push('style')
    <style type="text/css">
        .mcg-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .mcg-step-number {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 12px;
            font-weight: bold;
            margin-right: 6px;
            border: 4px solid #DBEAFE;
            color: #666666;
        }

        .mcg-step-label {
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
            color: #666666;
        }

        .mcg-step.passed .mcg-step-number {
            background-color: #10B981;
            color: white;
        }

        .mcg-step.passed .mcg-step-label {
            color: #10B981;
        }

        .mcg-step.active .mcg-step-number {
            background-color: #3B82F6;
            color: white;

        }

        .mcg-step.active .mcg-step-label {
            color: #3B82F6;
        }
    </style>
@endpush

<div class="mcg-step {{ $passed ? 'passed' : '' }} {{ $active && !$passed ? 'active' : '' }}">
    <div class="mcg-step-number">
        @if ($passed)
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="icon icon-tabler icons-tabler-outline icon-tabler-check">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M5 12l5 5l10 -10" />
            </svg>
        @else
            {{ $number }}
        @endif
    </div>
    <div class="mcg-step-label">{{ $label }}</div>
</div>
