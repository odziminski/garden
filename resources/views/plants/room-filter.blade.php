<form action="{{ $filterRoute }}" method="GET" class="d-flex flex-wrap align-items-end gap-2 mb-3">
    @isset($search)
        <div>
            <label class="form-label" for="garden-search">Search by name or species</label>
            <input class="form-control" id="garden-search" name="q" type="search" value="{{ $search }}" maxlength="120">
        </div>
    @endisset
    @if ($rooms->isNotEmpty())
        <div>
            <label class="form-label" for="care-room">Room</label>
            <select class="form-select" id="care-room" name="room">
                <option value="">All rooms</option>
                @foreach ($rooms as $availableRoom)
                    <option value="{{ $availableRoom }}" @selected($room === $availableRoom)>{{ $availableRoom }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <button type="submit" class="btn btn-outline-secondary">Show plants</button>
</form>
