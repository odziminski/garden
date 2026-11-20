<details class="col-12" @if (old('fertilizer', $feedingEntry?->fertilizer) || old('dose', $feedingEntry?->dose)) open @endif>
    <summary class="fw-semibold">Fertilizer and dose (optional)</summary>
    <div class="row g-3 mt-1">
        <div class="col-sm-6">
            <label class="form-label" for="{{ $fieldPrefix }}-fertilizer">Fertilizer</label>
            <input class="form-control" id="{{ $fieldPrefix }}-fertilizer" name="fertilizer" maxlength="120"
                   value="{{ is_string(old('fertilizer', $feedingEntry?->fertilizer)) ? old('fertilizer', $feedingEntry?->fertilizer) : '' }}">
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="{{ $fieldPrefix }}-dose">Dose used</label>
            <input class="form-control" id="{{ $fieldPrefix }}-dose" name="dose" maxlength="80"
                   value="{{ is_string(old('dose', $feedingEntry?->dose)) ? old('dose', $feedingEntry?->dose) : '' }}">
        </div>
    </div>
</details>
