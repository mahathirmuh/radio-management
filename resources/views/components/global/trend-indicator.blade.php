@props(['base' => 1, 'value' => 0, 'type' => 'number'])

@php
    $text = '';
    $percentage = (($value - $base) / ($base != 0 ? $base : 1)) * 100;
    if ($type == 'percentage') {
        $percentage = $value - $base;
    }

    if ($percentage > 0) {
        $text =
            '<span class="text-green d-inline-flex align-items-center lh-1">' .
            number_format($percentage, 2) .
            '%<i class="ti ti-arrow-up-right"></i></span>';
    } elseif ($percentage < 0) {
        $text =
            '<span class="text-red d-inline-flex align-items-center lh-1">' .
            number_format($percentage, 2) .
            '%<i class="ti ti-arrow-down-right"></i></span>';
    }

@endphp

{!! $text !!}
