{{-- A box's facts of an asset (AssetPage::facts): a name, then the value (a link, a badge for a status), a second line under it;
     without a value its 'empty' words, else a dash. --}}
<dl class="rm-facts">
    @foreach ($facts as $fact)
        <div>
            <dt>{{ $fact['label'] }}</dt>
            <dd @class(['snipe-mono' => $fact['mono'] ?? false])>
                @if ($fact['value'] === null)
                    <span class="snipe-muted">{{ $fact['empty'] ?? '—' }}</span>
                @elseif (isset($fact['tone']))
                    <span class="rm-badge {{ $fact['tone'] }}">{{ $fact['value'] }}</span>
                @elseif (! empty($fact['link']))
                    <a class="snipe-link" href="{{ $fact['link'] }}"
                        @unless ($fact['inside'] ?? false) target="_blank" rel="noopener noreferrer" @endunless>{{ $fact['value'] }}</a>
                @else
                    {{ $fact['value'] }}
                @endif
                @if (! empty($fact['sub']))
                    <div class="snipe-sub">{{ $fact['sub'] }}</div>
                @endif
            </dd>
        </div>
    @endforeach
</dl>
