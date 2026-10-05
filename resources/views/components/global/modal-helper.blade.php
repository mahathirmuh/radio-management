{{--
data => [
    'title' => '',
    'description' => '',
    'image' => '',
    'image-position' => '',
]
--}}

@props([
    'page' => '',
    'data' => [],
])

@if ($data != null && count($data) > 0)
    @foreach ($data as $key => $item)
        <div id="modal-helper-{{ $key + 1 }}" class="modal fade" aria-hidden="true" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $item['title'] }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        @if ($item['image_position'] == 'top')
                            <img src="{{ $item['image'] }}" alt="" class="img-fluid mb-3"
                                style="max-height: 300px">
                            @if (str_contains($item['description'], '<'))
                                <p class="fs-5 mb-3"> {!! $item['description'] !!} </p>
                            @else
                                <p class="fs-5 mb-3">{{ $item['description'] }}</p>
                            @endif
                        @else
                            @if (str_contains($item['description'], '<'))
                                <p class="fs-5 mb-3"> {!! $item['description'] !!} </p>
                            @else
                                <p class="fs-5 mb-3">{{ $item['description'] }}</p>
                            @endif
                            <img src="{{ $item['image'] }}" alt="" class="img-fluid mb-3"
                                style="max-height: 300px">
                        @endif
                    </div>
                    <div class="modal-footer d-flex justify-content-between align-items-center">
                        @if ($key == 0)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="" id="dont_show">
                                <label class="form-check-label" for="dont_show">
                                    Don't show this again
                                </label>
                            </div>
                        @else
                            <span class="text-muted fs-6">Step {{ $key + 1 }} of {{ count($data) }}</span>
                        @endif
                        <div class="d-flex justify-content-end align-items-center gap-2">
                            @if ($key < 1)
                                <button class="btn btn-sm px-3 py-1 rounded-2" data-bs-dismiss="modal">Close</button>
                            @else
                                <button class="btn btn-sm px-3 py-1 rounded-2"
                                    data-bs-target="#modal-helper-{{ $key }}" data-bs-toggle="modal"
                                    data-bs-dismiss="modal">Previous</button>
                            @endif

                            @if ($key < count($data) - 1)
                                <button class="btn btn-sm px-3 py-1 rounded-2 btn-facebook"
                                    data-bs-target="#modal-helper-{{ $key + 2 }}" data-bs-toggle="modal"
                                    data-bs-dismiss="modal">Next</button>
                            @else
                                <button class="btn btn-sm px-3 py-1 rounded-2 btn-facebook"
                                    data-bs-dismiss="modal">Understand</button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endif


@push('javascript')
    <script>
        $(document).ready(function() {
            if (localStorage.getItem('dont-show-helper-{{ $page }}') != 1) {
                showHelper();
            } else {
                $('#dont_show').prop('checked', true);
            }

            $('#dont_show').on('change', function() {
                if ($(this).is(':checked')) {
                    localStorage.setItem('dont-show-helper-{{ $page }}', 1);
                } else {
                    localStorage.removeItem('dont-show-helper-{{ $page }}');
                }
            })
        })

        function showHelper() {
            $('#modal-helper-1').modal('show');
        }
    </script>
@endpush
