@if ($plant->needs?->need_watering)
    <form class="d-inline-block" method="POST" action="{{ route('updateWatering', $plant) }}">
        @csrf
        <button class="btn" type="submit">Watered</button>
    </form>
@elseif ($nextWatering = $plant->nextWatering())
    <h6 title="{{ $nextWatering->format('l, j-m-Y') }}">Next watering {{ $nextWatering->diffForHumans() }}</h6>
@endif

@if ($plant->needs?->need_fertilizing)
    <form class="d-inline-block" method="POST" action="{{ route('updateFertilizing', $plant) }}">
        @csrf
        <button class="btn" type="submit">Fertilized</button>
    </form>
@elseif ($nextFertilizing = $plant->nextFertilizing())
    <h6 title="{{ $nextFertilizing->format('l, j-m-Y') }}">Next fertilizing {{ $nextFertilizing->diffForHumans() }}</h6>
@endif
