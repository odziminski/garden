@php($journalToday = now($plant->careTimezone())->toDateString())

<section id="photo-journal" class="bloom-panel my-4" aria-labelledby="photo-journal-title">
    <h2 id="photo-journal-title" class="h4 mb-3">Photo journal</h2>

    @if (session('photoJournalStatus'))
        <p class="alert alert-success" role="status">{{ session('photoJournalStatus') }}</p>
    @endif

    @error('delete', 'photoJournal')
        <p class="alert alert-danger" role="alert">{{ $message }}</p>
    @enderror

    <form action="{{ route('plants.photo-journal.store', $plant) }}" method="POST" enctype="multipart/form-data" class="row g-3 mb-4">
        @csrf
        <div class="col-md-5">
            <label class="form-label" for="journal-taken-on">Date taken</label>
            <input id="journal-taken-on" class="form-control @error('taken_on', 'photoJournal') is-invalid @enderror"
                   type="date" name="taken_on" required min="1000-01-01" max="{{ $journalToday }}"
                   value="{{ is_string(old('taken_on')) ? old('taken_on') : $journalToday }}"
                   @error('taken_on', 'photoJournal') aria-invalid="true" aria-describedby="journal-date-error" @enderror>
            @error('taken_on', 'photoJournal')
                <p class="invalid-feedback" id="journal-date-error">{{ $message }}</p>
            @enderror
        </div>
        <div class="col-md-7">
            <label class="form-label" for="journal-photo">Photo</label>
            <input id="journal-photo" class="form-control @error('photo', 'photoJournal') is-invalid @enderror"
                   type="file" name="photo" required accept="image/jpeg,image/png,image/webp"
                   aria-describedby="journal-photo-help @error('photo', 'photoJournal') journal-photo-error @enderror"
                   @error('photo', 'photoJournal') aria-invalid="true" @enderror>
            <p class="form-text mb-0" id="journal-photo-help">JPEG, PNG, or WebP up to 5 MB.</p>
            @error('photo', 'photoJournal')
                <p class="invalid-feedback" id="journal-photo-error">{{ $message }}</p>
            @enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="journal-note">Note <span class="text-muted">(optional)</span></label>
            <textarea id="journal-note" class="form-control @error('note', 'photoJournal') is-invalid @enderror"
                      name="note" rows="2" maxlength="500"
                      @error('note', 'photoJournal') aria-invalid="true" aria-describedby="journal-note-error" @enderror>{{ $errors->photoJournal->any() && is_string(old('note')) ? old('note') : '' }}</textarea>
            @error('note', 'photoJournal')
                <p class="invalid-feedback" id="journal-note-error">{{ $message }}</p>
            @enderror
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Add photo</button>
        </div>
    </form>

    @if ($photoJournalEntries->isEmpty() && $photoJournalEntries->onFirstPage())
        <p class="text-muted mb-0">No photos yet.</p>
    @endif

    <div class="row row-cols-1 row-cols-sm-2 g-3">
        @foreach ($photoJournalEntries as $entry)
            <article class="col">
                <div class="h-100 border rounded overflow-hidden">
                    <div class="ratio ratio-4x3">
                        <img src="{{ route('plants.photo-journal.show', [$plant, $entry]) }}"
                             alt="{{ $plant->name }} on {{ $entry->taken_on->format('F j, Y') }}"
                             class="object-fit-cover" loading="lazy" referrerpolicy="no-referrer">
                    </div>
                    <div class="p-3">
                        <time class="fw-semibold" datetime="{{ $entry->taken_on->toDateString() }}">
                            {{ $entry->taken_on->format('M j, Y') }}
                        </time>
                        @if ($entry->note !== null)
                            <p class="text-break mb-0">{{ $entry->note }}</p>
                        @endif
                        <form method="POST" action="{{ route('plants.photo-journal.destroy', [$plant, $entry]) }}" class="mt-2">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                    aria-label="Remove photo from {{ $entry->taken_on->format('F j, Y') }}">Remove</button>
                        </form>
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    @if ($photoJournalEntries->hasPages())
        <nav class="d-flex gap-2 mt-3" aria-label="Photo journal pages">
            @if ($photoJournalEntries->previousPageUrl())
                <a class="btn btn-outline-secondary btn-sm" href="{{ $photoJournalEntries->previousPageUrl() }}">Newer photos</a>
            @endif
            @if ($photoJournalEntries->nextPageUrl())
                <a class="btn btn-outline-secondary btn-sm" href="{{ $photoJournalEntries->nextPageUrl() }}">Older photos</a>
            @endif
        </nav>
    @endif
</section>
