@if ($errors->any())
    <ul class="alert alert-danger" role="alert">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif

<label class="form-label" for="plant-name">Plant name</label>
<div class="input-group mb-3">
    <input class="form-control" id="plant-name" name="name" value="{{ old('name', $plant?->name) }}"
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

<label class="form-label" for="watering-frequency">Watering frequency (days)</label>
<input class="form-control mb-3" id="watering-frequency" type="number" name="watering_frequency"
       min="1" max="3650" value="{{ old('watering_frequency', $plant?->needs?->watering_frequency) }}" required>

<label class="form-label" for="fertilizing-frequency">Fertilizing frequency (days)</label>
<input class="form-control mb-3" id="fertilizing-frequency" type="number" name="fertilizing_frequency"
       min="1" max="3650" value="{{ old('fertilizing_frequency', $plant?->needs?->fertilizing_frequency) }}" required>
