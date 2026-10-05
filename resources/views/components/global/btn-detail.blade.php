@props([
    'label' => '',
    'icon' => '',
    'disabled' => false,
    'size' => 'md',
    'color' => 'brand',
    'iconPosition' => 'left',
    'outline' => false,
    'href' => null, // Tambahkan prop href
])

@php
    $sizeClass = match ($size) {
        'xs' => 'px-1 py-0.5',
        'sm' => 'px-2 py-1',
        'lg' => 'px-4 py-3',
        'xl' => 'px-5 py-4',
        default => 'px-3 py-2',
    };

    $baseClass = 'd-inline-flex items-center justify-center gap-2 btn btn-sm ' . $sizeClass . ' rounded-2 ';

    $colorClass = $disabled
        ? 'disabled'
        : ($outline
            ? 'btn-outline-' . $color
            : 'mdka-btn-' . $color . '-500 text-white border-0');

    $iconClass =
        ($icon ? ' btn-icon' : '') .
        ($iconPosition == 'left' ? ' flex-row-reverse' : '') .
        ($iconPosition == 'right' ? ' flex-row' : '') .
        ($disabled ? ' disabled' : '');

    $finalClass = $baseClass . $colorClass . $iconClass;

    $commonAttributes = $attributes->merge([
        'class' => $finalClass,
        'style' => 'z-index: 1',
        'role' => '',
        'data-bs-toggle' => '',
        'data-bs-target' => '',
        'onclick' => '',
    ]);
@endphp

@if ($href)
    <a {{ $commonAttributes->merge(['href' => $href]) }}>
        {{ $label }}
        {!! $icon !!}
    </a>
@else
    <button type="button" {{ $commonAttributes->merge(['disabled' => $disabled]) }}>
        {{ $label }}
        {!! $icon !!}
    </button>
@endif
