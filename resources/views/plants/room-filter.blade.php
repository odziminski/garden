<form action="{{ $filterRoute }}" method="GET" class="d-flex flex-wrap align-items-end gap-2 mb-3">
    <div>
        <label class="form-label" for="care-room">Room</label>
        <select class="form-select" id="care-room" name="room">
            <option value="">All rooms</option>
            @foreach ($rooms as $availableRoom)
                <option value="{{ $availableRoom }}" @selected($room === $availableRoom)>{{ $availableRoom }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="btn btn-outline-secondary">Show plants</button>
</form>
