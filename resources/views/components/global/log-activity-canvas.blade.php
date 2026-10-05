@props([
    'id' => 'log-activity-canvas',
    'name' => 'Log Activity',
])

<div class="offcanvas offcanvas-end" tabindex="-1" id="{{ $id }}" aria-labelledby="{{ $id }}-label">
    <div class="p-4">

        <a role="button" data-bs-dismiss="offcanvas">
            <div class="row bg-gray-500 rounded">
                <div class="col-12 d-flex justify-content-start align-items-center gap-3 p-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-left">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M15 6l-6 6l6 6" />
                    </svg>
                    <span class="fs-2">
                        <b>{{ $name }}</b>
                    </span>
                </div>
            </div>
        </a>
    </div>
    <div class="offcanvas-body">
        <ul class="timeline">
            {{ $slot }}
        </ul>
    </div>
</div>
