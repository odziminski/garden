@if ($errors->any())
    <ul class="alert alert-danger" role="alert">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif

<label class="form-label" for="plant-name">Plant name</label>
<div class="input-group mb-3">
    @php
        $enteredName = old('name', $plant?->name);
    @endphp
    <input class="form-control" id="plant-name" name="name" value="{{ is_string($enteredName) ? $enteredName : '' }}"
           maxlength="64" required>
    <button class="btn btn-outline-secondary" type="button" data-plant-name>Random name</button>
</div>

<details class="mb-4" @if ($errors->hasAny(['room', 'species_name', 'care_instructions'])) open @endif>
    <summary class="fw-semibold">Location and care notes</summary>
    <div class="row g-3 mt-1">
        <div class="col-sm-6">
            <label class="form-label" for="plant-room">Room or location</label>
            <input class="form-control" id="plant-room" name="room" maxlength="80"
                   value="{{ is_string(old('room', $plant?->room)) ? old('room', $plant?->room) : '' }}">
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="plant-species">Species (optional)</label>
            <input class="form-control" id="plant-species" name="species_name" maxlength="120"
                   value="{{ is_string(old('species_name', $plant?->species_name)) ? old('species_name', $plant?->species_name) : '' }}">
        </div>
        <div class="col-12">
            <label class="form-label" for="plant-instructions">What to check</label>
            <textarea class="form-control" id="plant-instructions" name="care_instructions" maxlength="600" rows="2"
                      placeholder="For example: compare the weight of the pot">{{ is_string(old('care_instructions', $plant?->care_instructions)) ? old('care_instructions', $plant?->care_instructions) : '' }}</textarea>
        </div>
    </div>
</details>

<div class="mb-3" data-plant-photo>
    <label class="form-label" for="plant-avatar">Plant photo (optional)</label>
    <input class="form-control" id="plant-avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
    <input type="hidden" name="webcamAvatar">
    <div class="mt-2" data-camera-controls hidden>
        <button class="btn" type="button" data-camera-start>Open camera</button>
        <video class="img-fluid" data-camera-video autoplay muted playsinline></video>
        <button class="btn" type="button" data-camera-snapshot hidden>Take photo</button>
        <button class="btn" type="button" data-camera-close hidden>Close camera</button>
        <img class="img-fluid" data-camera-preview alt="Captured plant photo" hidden>
        <button class="btn" type="button" data-camera-remove hidden>Remove captured photo</button>
        <p class="text-danger" data-camera-error role="status"></p>
    </div>
</div>

@if ($plant)
<details class="mb-4" @if ($errors->hasAny(\App\Models\Needs::SCHEDULE_FIELDS)) open @endif>
    <summary class="fw-semibold">Advanced reminders</summary>
    <div class="row g-3 mb-3 mt-1">
        @foreach (\App\Enums\CareType::cases() as $careType)
            @continue($careType === \App\Enums\CareType::Watering && $plant->checkSchedule?->enabled)
            @php
                $field = $careType->frequencyColumn();
                $watering = $careType === \App\Enums\CareType::Watering;
                $default = $plant->needs?->$field;
                $enteredInterval = old($field, $default ?: null);
            @endphp
            <div class="col-sm-6">
                <label class="form-label" for="{{ $field }}">{{ $watering ? 'Existing watering reminder' : 'Review feeding' }} interval (days)</label>
                <input class="form-control" id="{{ $field }}" type="number" name="{{ $field }}"
                       min="1" max="3650" value="{{ is_scalar($enteredInterval) ? $enteredInterval : '' }}"
                       aria-describedby="{{ $field }}-help">
                <p class="form-text mb-0" id="{{ $field }}-help">
                    Leave blank to turn off this reminder.
                </p>
            </div>
        @endforeach
    </div>

    @php
        $hemisphere = old('seasonal_hemisphere', $plant?->needs?->seasonal_hemisphere ?? 'north');
        $seasonalFields = ['summer_watering_frequency', 'winter_watering_frequency', 'summer_fertilizing_frequency', 'winter_fertilizing_frequency'];
        $showSeasonal = $hemisphere === 'south' || $errors->hasAny([...$seasonalFields, 'seasonal_hemisphere'])
            || collect($seasonalFields)->contains(fn ($field) => old($field, $plant?->needs?->$field) !== null);
    @endphp
    <details @if ($showSeasonal) open @endif>
        <summary class="fw-semibold">Seasonal options</summary>
        <p class="text-muted mt-2">Summer is April through September in the Northern Hemisphere and October through March in the Southern Hemisphere. Leave an override blank to use the regular interval.</p>
        <label class="form-label" for="seasonal-hemisphere">Hemisphere</label>
        <select class="form-select mb-3" id="seasonal-hemisphere" name="seasonal_hemisphere">
            <option value="north" @selected($hemisphere !== 'south')>Northern</option>
            <option value="south" @selected($hemisphere === 'south')>Southern</option>
        </select>
        @foreach (\App\Enums\CareType::cases() as $careType)
            @continue($careType === \App\Enums\CareType::Watering && $plant->checkSchedule?->enabled)
            <h3 class="h6 mt-3">{{ $careType->label() }}</h3>
            <div class="row g-3 mb-3">
            @foreach (['Summer' => 'summer_', 'Winter' => 'winter_'] as $label => $prefix)
                @php
                    $field = $prefix.$careType->frequencyColumn();
                    $enteredInterval = old($field, $plant?->needs?->$field);
                @endphp
                <div class="col-sm-4">
                    <label class="form-label" for="{{ $field }}">{{ $label }} (days)</label>
                    <input class="form-control" id="{{ $field }}" type="number" name="{{ $field }}"
                           min="1" max="3650" value="{{ is_scalar($enteredInterval) ? $enteredInterval : '' }}">
                </div>
            @endforeach
            </div>
        @endforeach
    </details>
</details>
@else
<details class="mb-4" @if ($errors->hasAny(['first_check_on', 'check_interval_days']) || old('first_check_on') || old('check_interval_days')) open @endif>
    <summary class="fw-semibold">Soil check reminder (optional)</summary>
    <div class="row g-3 mt-1">
        <div class="col-sm-6">
            <label class="form-label" for="first-check-on">First soil check</label>
            <input class="form-control" id="first-check-on" name="first_check_on" type="date"
                   value="{{ is_string(old('first_check_on')) ? old('first_check_on') : '' }}">
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="check-interval-days">Repeat every (days)</label>
            <input class="form-control" id="check-interval-days" name="check_interval_days" type="number"
                   min="1" max="3650" value="{{ is_scalar(old('check_interval_days')) ? old('check_interval_days') : '' }}">
        </div>
    </div>
    <p class="form-text mb-0">Enter a date for a one-time check, or an interval to check regularly. This reminds you to inspect the soil.</p>
</details>
@endif
