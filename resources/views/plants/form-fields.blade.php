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
           minlength="3" maxlength="64" required>
    <button class="btn btn-outline-secondary" type="button" data-plant-name>Random name</button>
</div>

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

<fieldset class="mb-4">
    <legend class="h5">Care schedule</legend>
    <p class="text-muted">Summer runs April through September. Winter runs October through March. Leave a seasonal interval blank to use the default for that type of care.</p>

    @foreach (\App\Enums\CareType::cases() as $careType)
        <h3 class="h6 mt-3">{{ $careType->label() }}</h3>
        <div class="row g-3 mb-3">
            @foreach (['Default' => '', 'Summer' => 'summer_', 'Winter' => 'winter_'] as $label => $prefix)
                @php
                    $field = $prefix.$careType->frequencyColumn();
                    $enteredInterval = old($field, $plant?->needs?->$field);
                @endphp
                <div class="col-sm-4">
                    <label class="form-label" for="{{ $field }}">{{ $label }} (days)</label>
                    <input class="form-control" id="{{ $field }}" type="number" name="{{ $field }}"
                           min="1" max="3650" value="{{ is_scalar($enteredInterval) ? $enteredInterval : '' }}"
                           @required($prefix === '')>
                </div>
            @endforeach
        </div>
    @endforeach
</fieldset>
