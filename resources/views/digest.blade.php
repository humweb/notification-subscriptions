@php $components = $components ?? []; $subject = $subject ?? 'Your Notification Digest'; @endphp
@component('mail::message')
# {{ $subject }}

@foreach($components as $c)
@if(($c['type'] ?? null) === 'heading')
@php $lvl = max(1, min(4, $c['level'] ?? 2)); $prefix = str_repeat('#', $lvl); @endphp
{{ $prefix }} {{ $c['text'] }}

@elseif(($c['type'] ?? null) === 'line')
{{ $c['text'] }}

@elseif(($c['type'] ?? null) === 'panel')
@component('mail::panel')
{{ $c['text'] }}
@endcomponent

@elseif(($c['type'] ?? null) === 'button')
@component('mail::button', ['url' => $c['url'], 'color' => $c['color'] ?? 'primary'])
{{ $c['text'] }}
@endcomponent

@elseif(($c['type'] ?? null) === 'list')
@foreach(($c['items'] ?? []) as $item)
- {{ $item }}
@endforeach

@elseif(($c['type'] ?? null) === 'separator')
---

@endif
@endforeach

Thanks,
{{ config('app.name') }}
@endcomponent


