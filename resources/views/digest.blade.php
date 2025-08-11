@php
    $components = $components ?? [];
    $subject = $subject ?? 'Your Notification Digest';
@endphp

@component('mail::message')
# {{ $subject }}

@foreach($components as $c)
    @switch($c['type'])
        @case('heading')
            @if(($c['level'] ?? 2) === 1)
                # {{ $c['text'] }}
            @elseif(($c['level'] ?? 2) === 2)
                ## {{ $c['text'] }}
            @elseif(($c['level'] ?? 2) === 3)
                ### {{ $c['text'] }}
            @else
                #### {{ $c['text'] }}
            @endif
            @break

        @case('line')
            {{ $c['text'] }}

            @break

        @case('panel')
            @component('mail::panel')
                {{ $c['text'] }}
            @endcomponent
            @break

        @case('button')
            @component('mail::button', ['url' => $c['url'], 'color' => $c['color'] ?? 'primary'])
                {{ $c['text'] }}
            @endcomponent
            @break

        @case('list')
            @foreach(($c['items'] ?? []) as $item)
            - {{ $item }}
            @endforeach

            @break

        @case('separator')
            ---

            @break
    @endswitch
@endforeach

Thanks,
{{ config('app.name') }}
@endcomponent


