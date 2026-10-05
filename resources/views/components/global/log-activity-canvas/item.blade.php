@props([
    'index' => 0,
    'user' => '',
    'activity' => '',
    'document' => '',
    'time' => '',
    'note' => '',
])
<li class="timeline-event">
    <div class="timeline-event-icon {{ $index == 0 ? 'bg-green' : 'bg-red' }} text-white fw-bold rounded-pill max-w-1"
        style="width: 2rem; height: 2rem; left: 0.3rem; top: 0.25rem">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            class=" icon-tabler icons-tabler-outline icon-tabler-sun">
            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
            <path d="M12 12m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" />
            <path d="M3 12h1m8 -9v1m8 8h1m-9 8v1m-6.4 -15.4l.7 .7m12.1 -.7l-.7 .7m0 11.4l.7 .7m-12.1 -.7l-.7 .7" />
        </svg>
    </div>
    <div class="card timeline-event-card border-0 shadow-none">
        <div class="card-body p-0">
            <h4><span class="text-facebook">{{ $user }} </span> {{ $activity }}
                <span class="text-facebook">{{ $document }} </span> •
                {{ \Carbon\Carbon::parse($time)->diffForHumans() }}
            </h4>
            @if ($note)
                <div class="card border-0 rounded">
                    <div class="card-body p-2 bg-gray-500">
                        {{ $note }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</li>
