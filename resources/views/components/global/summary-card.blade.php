@props([
    'value_id',
    'value',
    // The number itself (the value is it as the page writes it), when it may count up (rmMotion.countUp).
    'count' => null,
    'unit',
    'title',
    'icon' => '',
    'color' => '',
    'size' => 'md',
    'justify_content' => 'start',
    'rounded' => true,
])

@php
    // A stat tile of the design layer (radio-ui.css .rm-kpi): an icon in a tinted square, the name, the number.
    $tone = match ($color) {
        'brand', 'blue' => 'blue',
        'green', 'lime' => 'green',
        'orange' => 'orange',
        'red', 'pink' => 'red',
        'yellow' => 'yellow',
        'purple' => 'purple',
        default => 'gray',
    };
@endphp

<div {{ $attributes->merge(['class' => 'rm-kpi h-100 rm-kpi-' . $size]) }}>
    <span class="rm-kpi-icon tone-{{ $tone }}">{{ $icon ?? '' }}</span>
    <span class="rm-kpi-text">
        <span class="rm-kpi-label">{{ $title ?? '' }}</span>
        <span class="rm-kpi-value" @isset($value_id) id="{{ $value_id }}" @endisset @if ($count !== null) data-count="{{ (int) $count }}" @endif>{{ $value ?? '' }}</span>
        @isset($trend)
            <span class="rm-kpi-trend" @isset($value_id) id="{{ $value_id }}-trend" @endisset>{{ $trend }}</span>
        @endisset
    </span>
</div>
