<x-mail::message>
# Your plants need care

@foreach ($plants as $plant)
<p>
    <strong>{{ $plant->name }}</strong>
    @if ($plant->needs->need_watering && $plant->needs->need_fertilizing)
        needs watering and fertilizing.
    @elseif ($plant->needs->need_watering)
        needs watering.
    @else
        needs fertilizing.
    @endif
</p>
@endforeach

<x-mail::button :url="route('care.today')">
Open Today
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
