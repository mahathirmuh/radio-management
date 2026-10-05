@props([
    'value_id',
    'value',
    'unit',
    'title',
    'icon' => '',
    'color' => '',
    'size' => 'md',
    'justify_content' => 'start',
    'rounded' => true,
])

@php
    switch ($color) {
        case 'red':
            $text_color = 'mdka-text-red-600';
            $bg_color = 'mdka-bg-red-100';
            $clip_color = 'mdka-bg-red-300';
            break;
        case 'blue':
            $text_color = 'mdka-text-blue-500';
            $bg_color = 'mdka-bg-blue-50';
            $clip_color = 'mdka-bg-blue-200';
            break;
        case 'green':
            $text_color = 'mdka-text-green-600';
            $bg_color = 'mdka-bg-green-100';
            $clip_color = 'mdka-bg-green-300';
            break;
        case 'yellow':
            $text_color = 'mdka-text-yellow-500';
            $bg_color = 'mdka-bg-yellow-50';
            $clip_color = 'mdka-bg-yellow-200';
            break;
        case 'purple':
            $text_color = 'mdka-text-purple-600';
            $bg_color = 'mdka-bg-purple-100';
            $clip_color = 'mdka-bg-purple-300';
            break;
        case 'pink':
            $text_color = 'mdka-text-pink-600';
            $bg_color = 'mdka-bg-pink-100';
            $clip_color = 'mdka-bg-pink-300';
            break;
        case 'orange':
            $text_color = 'mdka-text-orange-600';
            $bg_color = 'mdka-bg-orange-100';
            $clip_color = 'mdka-bg-orange-300';
            break;
        case 'lime':
            $text_color = 'mdka-text-lime-600';
            $bg_color = 'mdka-bg-lime-100';
            $clip_color = 'mdka-bg-lime-300';
            break;
        case 'brand':
            $text_color = 'mdka-text-brand-600';
            $bg_color = 'mdka-bg-brand-100';
            $clip_color = 'mdka-bg-brand-300';
            break;
        default:
            $text_color = 'mdka-text-gray-600';
            $bg_color = 'mdka-bg-gray-100';
            $clip_color = 'mdka-bg-gray-300';
            break;
    }

    switch ($size) {
        case 'sm':
            $value_size = '1em';
            $title_size = '0.8em';
            $card_padding = 'p-2';
            break;

        case 'md':
            $value_size = '1.5em';
            $title_size = '1em';
            $card_padding = 'p-3';
            break;

        case 'lg':
            $value_size = '2em';
            $title_size = '1.5em';
            $card_padding = 'p-3';
            break;

        default:
            $value_size = '1.5em';
            $title_size = '1em';
            $card_padding = 'p-3';
            break;
    }
@endphp


@push('style')
    <style type="text/css">
        .summary-icon {
            position: relative;
            width: 50px;
            height: 50px;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .summary-icon-clip {
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 25%;
            right: -5px;
            bottom: -5px;
        }
    </style>
@endpush

<div {{ $attributes->merge(['class' => 'card h-100 rounded-3', 'style' => '']) }}>
    <div class="card-body {{ $card_padding }}">
        <div class="d-flex h-100 w-100 justify-content-{{ $justify_content }} align-items-center gap-4">
            <div class="summary-icon rounded-3 {{ $text_color ?? '' }} {{ $bg_color ?? '' }}">
                {{ $icon ?? '' }}
                <span class="summary-icon-clip {{ $clip_color ?? '' }}"></span>
            </div>
            <div class="d-flex flex-column position-relative">
                <span class="fw-medium text-nowrap" style="font-size: {{ $title_size }}">{{ $title ?? '' }}</span>
                <div class="d-flex align-items-center justify-content-start gap-2">
                    <span id="{{ $value_id ?? '' }}" class="fw-bolder position-relative"
                        style="font-size: {{ $value_size }}">
                        {{ $value ?? '' }}
                    </span>

                    <span id="{{ $value_id ?? '' }}-trend" class="position-relative">
                        {{ $trend ?? '' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
