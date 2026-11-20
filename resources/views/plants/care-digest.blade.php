<x-mail::message>
# Your plants need care

@foreach ($plants as $plant)
<p>
    <strong>{{ $plant->name }}</strong>
    @if ($plant->needs?->need_watering && $plant->needs?->need_fertilizing)
        needs watering and fertilizing.
    @elseif ($plant->needs?->need_watering)
        needs watering.
    @elseif ($plant->needs?->need_fertilizing)
        needs fertilizing.
    @endif
</p>
@if ($plant->relationLoaded('careTasks') && $plant->careTasks->isNotEmpty())
<ul>
@foreach ($plant->careTasks as $task)
<li>{{ $task->name }}</li>
@endforeach
</ul>
@endif
@endforeach

<x-mail::button :url="route('care.today')">
Open Today
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
