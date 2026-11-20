<x-mail::message>
# Plant checks and care tasks for today

@foreach ($plants as $plant)
<p>
    <strong>{{ $plant->name }}</strong>
</p>
@if ($plant->soil_check_due || $plant->needs?->need_watering || $plant->needs?->need_fertilizing)
<ul>
@if ($plant->soil_check_due || $plant->needs?->need_watering)
<li>Check soil and decide whether to water.</li>
@endif
@if ($plant->needs?->need_fertilizing)
<li>Review whether it is time to feed.</li>
@endif
</ul>
@endif
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
