@php
    $observationErrors = $errors->getBag('plantObservation');
    $observationInput = fn ($field, $default = '') => $observationErrors->any() && is_string(old($field))
        ? old($field)
        : $default;
    $today = now($plant->careTimezone())->toDateString();
    $repotting = \App\Enums\PlantObservationCategory::Repotting->value;
@endphp

<section class="bloom-panel my-4" id="observations" aria-labelledby="observations-title">
    <h2 class="h4 mb-3" id="observations-title">Observations</h2>
    @if (session('plantObservationStatus'))
        <p class="alert alert-success" role="status">{{ session('plantObservationStatus') }}</p>
    @endif
    @if ($observationErrors->any())
        <ul class="alert alert-danger list-unstyled" role="alert">
            @foreach ($observationErrors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form class="row g-3 mb-4" method="POST"
          action="{{ route('plants.observations.store', [$plant, 'photo_choice_page' => $canManage && !$observationPhotos->onFirstPage() ? $observationPhotos->currentPage() : null]) }}">
        @csrf
        <div class="col-sm-6">
            <label class="form-label" for="observation-category">Category</label>
            <select class="form-select" id="observation-category" name="category" required>
                @foreach (\App\Enums\PlantObservationCategory::cases() as $category)
                    <option value="{{ $category->value }}" @selected($observationInput('category') === $category->value)>{{ $category->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="observation-date">Observation date</label>
            <input class="form-control" id="observation-date" name="observed_on" type="date"
                   min="1000-01-01" max="{{ $today }}" value="{{ $observationInput('observed_on', $today) }}" required>
        </div>
        <div class="col-12">
            <label class="form-label" for="observation-notes">Notes</label>
            <textarea class="form-control" id="observation-notes" name="notes" rows="2"
                      maxlength="1000" required>{{ $observationInput('notes') }}</textarea>
        </div>
        <div class="col-12">
            <details @if ($observationInput('category') === $repotting || $observationErrors->has('container_size') || $observationErrors->has('substrate')) open @endif>
                <summary>Repotting details (optional)</summary>
                <div class="row g-3 mt-1">
                    <div class="col-sm-5">
                        <label class="form-label" for="observation-container-size">Container size</label>
                        <input class="form-control" id="observation-container-size" name="container_size" type="text"
                               maxlength="100" value="{{ $observationInput('container_size') }}">
                    </div>
                    <div class="col-sm-7">
                        <label class="form-label" for="observation-substrate">Substrate</label>
                        <input class="form-control" id="observation-substrate" name="substrate" type="text"
                               maxlength="500" value="{{ $observationInput('substrate') }}">
                    </div>
                </div>
            </details>
        </div>
        @if ($canManage)
            @if ($observationPhotos->isNotEmpty())
                <div class="col-12">
                    <label class="form-label" for="observation-photo">Private journal photo (optional)</label>
                    <select class="form-select" id="observation-photo" name="photo_entry_id">
                        <option value="">No photo</option>
                        @foreach ($observationPhotos as $photo)
                            <option value="{{ $photo->id }}" @selected($observationInput('photo_entry_id') === (string) $photo->id)>
                                {{ $photo->taken_on->format('M j, Y') }}
                                @if ($photo->note)
                                    · {{ \Illuminate\Support\Str::limit($photo->note, 70) }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($observationPhotos->hasPages())
                <nav class="d-flex flex-wrap gap-2" aria-label="Journal photo choices">
                    @if ($observationPhotos->isEmpty())
                        <a href="{{ $observationPhotos->url(1) }}">Newest photo choices</a>
                    @elseif ($observationPhotos->previousPageUrl())
                        <a href="{{ $observationPhotos->previousPageUrl() }}">Newer photo choices</a>
                    @endif
                    @if ($observationPhotos->nextPageUrl())
                        <a href="{{ $observationPhotos->nextPageUrl() }}">Older photo choices</a>
                    @endif
                </nav>
            @endif
            <div class="col-12">
                <details @if ($observationInput('follow_up_in_days') || $observationInput('follow_up_name') || $observationInput('follow_up_due_on') || $observationErrors->hasAny(['follow_up_in_days', 'follow_up_name', 'follow_up_due_on'])) open @endif>
                    <summary>Schedule a follow-up (optional)</summary>
                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label class="form-label" for="observation-follow-up-interval">Quick follow-up</label>
                            <select class="form-select" id="observation-follow-up-interval" name="follow_up_in_days"
                                    aria-describedby="observation-follow-up-help">
                                <option value="">Choose a custom date or skip</option>
                                @foreach ([3, 7, 14] as $days)
                                    <option value="{{ $days }}" @selected($observationInput('follow_up_in_days') === (string) $days)>In {{ $days }} days</option>
                                @endforeach
                            </select>
                            <p class="form-text mb-0" id="observation-follow-up-help">A quick follow-up suggests a task name.</p>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="observation-follow-up-name">Task name</label>
                            <input class="form-control" id="observation-follow-up-name" name="follow_up_name" type="text"
                                   maxlength="120" value="{{ $observationInput('follow_up_name') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="observation-follow-up-date">Custom due date</label>
                            <input class="form-control" id="observation-follow-up-date" name="follow_up_due_on" type="date"
                                   min="{{ $today }}" max="9999-12-31" value="{{ $observationInput('follow_up_due_on') }}">
                        </div>
                    </div>
                </details>
            </div>
        @endif
        <div class="col-12">
            <button class="btn btn-primary" type="submit">Add observation</button>
        </div>
    </form>

    @if ($observations->isEmpty())
        <p class="text-muted mb-0">{{ $observations->onFirstPage() ? 'No observations yet.' : 'No observations on this page.' }}</p>
    @else
        <ol class="list-group list-group-flush">
            @foreach ($observations as $entry)
                <li class="list-group-item px-0">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <strong>{{ $entry->category->label() }}</strong>
                        <time class="text-muted" datetime="{{ $entry->observed_on }}">
                            {{ \Carbon\CarbonImmutable::parse($entry->observed_on, 'UTC')->format('M j, Y') }}
                        </time>
                    </div>
                    @if ($entry->actor)
                        <p class="small text-muted text-break mb-0">Recorded by {{ $entry->actor->email }}</p>
                    @endif
                    <p class="text-break mb-0">{{ $entry->notes }}</p>
                    @if ($entry->container_size !== null || $entry->substrate !== null)
                        <dl class="row small mb-0 mt-2">
                            @if ($entry->container_size !== null)
                                <dt class="col-sm-3">Container size</dt>
                                <dd class="col-sm-9 text-break">{{ $entry->container_size }}</dd>
                            @endif
                            @if ($entry->substrate !== null)
                                <dt class="col-sm-3">Substrate</dt>
                                <dd class="col-sm-9 text-break">{{ $entry->substrate }}</dd>
                            @endif
                        </dl>
                    @endif
                    @if ($canManage && $entry->photo_entry_id)
                        <p class="small mb-0 mt-2">
                            <a href="{{ route('plants.photo-journal.show', [$plant, $entry->photo_entry_id]) }}">View attached photo</a>
                        </p>
                    @endif
                    @if ($entry->followUpTask)
                        @php($followUp = $entry->followUpTask)
                        <p class="small text-break mb-0 mt-2">
                            <strong>Follow-up:</strong>
                            @if ($followUp->isActive())
                                <a href="#custom-task-{{ $followUp->id }}">{{ $followUp->name }}</a>
                                · Due <time datetime="{{ $followUp->effectiveDueOn() }}">{{ \Carbon\CarbonImmutable::parse($followUp->effectiveDueOn(), 'UTC')->format('M j, Y') }}</time>
                            @else
                                {{ $followUp->name }} · {{ $followUp->archived_at ? 'Archived' : 'Completed' }}
                            @endif
                        </p>
                    @endif
                    @if ($entry->canBeCorrectedBy(auth()->user(), $plant))
                        <a class="btn btn-outline-secondary btn-sm mt-2" href="{{ route('plants.observations.edit', [$plant, $entry]) }}">Correct observation</a>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    @if ($observations->hasPages())
        <nav class="d-flex gap-2 mt-3" aria-label="Observation pages">
            @if ($observations->isEmpty())
                <a class="btn btn-outline-secondary btn-sm" href="{{ $observations->url(1) }}">Newest observations</a>
            @elseif ($observations->previousPageUrl())
                <a class="btn btn-outline-secondary btn-sm" href="{{ $observations->previousPageUrl() }}">Newer observations</a>
            @endif
            @if ($observations->nextPageUrl())
                <a class="btn btn-outline-secondary btn-sm" href="{{ $observations->nextPageUrl() }}">Older observations</a>
            @endif
        </nav>
    @endif
</section>
