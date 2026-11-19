<x-mail::message>
# {{ $plant->name }} needs care

Check your garden for watering and fertilizing reminders.

<img src="{{ $plant->avatarUrl() }}" width="250" alt="Plant photo">

<x-mail::button :url="route('plants', $plant)">
View plant
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
