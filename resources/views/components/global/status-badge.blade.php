@props(['value' => '', 'count' => 3])

@php
    if ($count === 3) {
        switch ($value) {
            case 0:
                $color = 'green';
                $label = 'Completed';
                break;
            case 1:
                $color = 'yellow';
                $label = 'In Progress';
                break;
            case 2:
                $color = 'red';
                $label = 'Failed';
                break;
        }
    }

    if ($count === 4) {
        switch ($value) {
            case 0:
                $color = 'gray';
                $label = 'Not Generated';
                break;
            case 1:
                $color = 'yellow';
                $label = 'In Progress';
                break;
            case 2:
                $color = 'green';
                $label = 'Completed';
                break;
            case 3:
                $color = 'red';
                $label = 'Failed';
                break;
        }
    }
@endphp

@if (isset($color) && isset($label))
    <span class="status fs-5 status-{{ $color }}">
        {{ $label }}
    </span>
@endif
