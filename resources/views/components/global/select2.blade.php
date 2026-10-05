@props([
    'name' => '',
    'placeholder' => '',
    'value' => null,
    'data' => null,
    'data_value' => '',
    'data_label' => '',
    'multiple' => false,
    'custom_data' => [],
    'group_class' => null,
    'modal' => '',
    'disabled' => 'false',
    'full_width' => false,
    'tagged' => false,
])

@php
    $params = request()->get($name);
@endphp

@if ($multiple)
    @push('style')
        <style type="text/css">
            .select2-checkbox {
                margin-right: 0.5rem;
            }

            .form-select {
                /* max-height: 32px; */
            }

            .select2-container--bootstrap-5 .select2-selection {
                min-height: 2rem !important;
                padding: 6px 18px 6px 12px;
                border-radius: 0.5rem !important;
            }

            .select2-container--bootstrap-5 .select2-selection--multiple .select2-search {
                padding-top: 4px;
                padding-bottom: 4px;
            }

            .select2.select2-container.select2-container--bootstrap-5 {
                width: 100% !important;
            }

            .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered .select2-selection__choice {
                white-space: nowrap;
            }

            .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered {
                overflow-x: scroll;
                scrollbar-width: none;
            }

            .select2-container--bootstrap-5.select2-container--open.select2-container--below .select2-selection {}
        </style>
    @endpush
@endif

<div class="form-group  {{ $group_class ?? 'w-100' }}" style="max-width: {{ $full_width ? '100%' : '200px' }};">
    <select {!! $attributes->merge([
        'class' => 'form-select select2',
    ]) !!} id="{{ $name }}" name="{{ $multiple ? $name . '[]' : $name }}"
        {{ $multiple ? 'multiple' : '' }} {{ $disabled == 'true' ? 'disabled' : '' }}>
        @if ($data !== null)

            @if (!$multiple)
                <option value="" disabled selected></option>
            @endif

            @foreach ($data as $item)
                <option value="{{ $item[$data_value] }}"
                    @if ($multiple) {{ in_array($item[$data_value], $params ?? []) ? 'selected' : '' }}
                @else
                    {{ $item[$data_value] == $params ?? '' ? 'selected' : '' }} @endif
                    {!! join(
                        ' ',
                        array_map(function ($val) use ($item) {
                            return 'data-' . $val . '="' . $item[$val] . '"';
                        }, $custom_data),
                    ) !!}>

                    @if (is_array($data_label))
                        @foreach ($data_label as $key => $label)
                            @if ($key == 0)
                                {{ $item[$label] }}
                            @else
                                - {{ $item[$label] }}
                            @endif
                        @endforeach
                    @elseif (is_string($data_label))
                        {{ $item[$data_label] }}
                    @endif
                </option>
            @endforeach
        @endif
    </select>
    <div class="invalid-feedback">
        {{ $errors->first($name) }}
    </div>
</div>


@push('javascript')
    <script>
        // Usage
        $('#{{ $name }}').select2({
            placeholder: '{{ $placeholder }}',
            closeOnSelect: false,
            @if ($multiple)
                templateResult: formatState,
            @else
                closeOnSelect: true,
            @endif
            allowHtml: true,
            theme: 'bootstrap-5',
            @if ($tagged)
                tags: true,
            @endif
            @if ($modal != '')
                dropdownParent: $("#{{ $modal }}"),
            @endif

        });

        $('#{{ $name }}').on('select2:select', function(e) {
            updateCheckboxState('#{{ $name }}');
        });
        $('#{{ $name }}').on('select2:unselect', function(e) {
            updateCheckboxState('#{{ $name }}');
        });

        function updateCheckboxState(id) {
            var selectedOptions = $(id + '.select2-results__option[aria-selected=true]');
            selectedOptions.each(function() {
                var $checkbox = $(this).find('.select2-checkbox');
                $checkbox.prop('checked', true);
            });
            var unselectedOptions = $(id + '.select2-results__option[aria-selected=false]');
            unselectedOptions.each(function() {
                var $checkbox = $(this).find('.select2-checkbox');
                $checkbox.prop('checked', false);
            });
        }

        function formatState(state) {

            if (!state.id) {
                return state.text;
            }
            var $state = $(
                '<div class="d-flex align-items-center justify-content-start"><input type="checkbox" class="select2-checkbox" />' +
                state.text.trim() + '</div>');
            $state.find('.select2-checkbox').prop('checked', state.selected);

            $state.on('click', function() {
                $(this).find('input[type="checkbox"].select2-checkbox').prop('checked', state.selected);
            });

            return $state;
        }

        $('#{{ $name }}').on('change', function(e) {

            if ($(this).val() != null && $(this).val().length > 0) {
                $(this).parent().find(
                    '.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered'
                ).css('display', 'flex');
            } else {
                $(this).parent().find(
                    '.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered'
                ).css('display', 'none');
            }
        });

        $('#{{ $name }}').trigger('change');
    </script>
@endpush
