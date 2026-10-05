@props(['type' => '', 'value' => ''])

@php
    switch ($type) {
        case 'ton':
            $color = 'facebook';
            break;
        case 'kl':
            $color = 'green';
            break;
        case 'series':
            $color = 'red';
        case 'feet':
            break;
            $color = 'yellow';
            break;
        default:
            $color = 'gray';
            break;
    }
@endphp

@if (isset($color))
    <span class="badge badge-outline text-{{ $color }} px-3">
        {{ $value }}
    </span>
@endif
