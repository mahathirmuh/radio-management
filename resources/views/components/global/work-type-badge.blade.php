@props(['code' => 'PL'])

@php
    $color = 'green';
    $label = '';

    switch ($code) {
        case 'PL':
            $color = 'green';
            $label = 'Planned';
            break;
        case 'SP':
            $color = 'yellow';
            $label = 'Shutdown Planning';
            break;
        case 'UP':
            $color = 'red';
            $label = 'Unplanned';
            break;
        case 'CM':
            $color = 'blue';
            $label = 'Condition Monitoring';
            break;
        case 'PM':
            $color = 'purple';
            $label = 'Preventive Maintenance';
            break;
        case 'WA':
            $color = 'github';
            $label = 'Warranty';
            break;
        case 'WI':
            $color = 'dribbble';
            $label = 'Work From PM Inspections';
            break;
        default:
            $color = 'green';
            break;
    }
@endphp

<span class="status fs-5 status-{{ $color }}">
    {{ $label }}
</span>
