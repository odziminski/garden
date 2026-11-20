@extends('layouts.app')

@section('title', 'Vacation handoff')

@section('content')
    <div class="container d-grid gap-4 py-5" style="max-width: 52rem;">
        <header class="bloom-page-heading mb-0">
            <h1>Vacation handoff</h1>
            <p class="text-muted mb-0">Give a helper temporary access to selected plants and leave instructions for each one.</p>
        </header>

        @if (session('handoff_status'))
            <div class="alert alert-success mb-0" role="status">{{ session('handoff_status') }}</div>
        @endif

        <section class="d-grid gap-3" aria-labelledby="incoming-handoffs">
            <h2 class="h4 mb-0" id="incoming-handoffs">Plants you’re helping with</h2>
            @forelse ($incoming as $handoff)
                <article class="bloom-panel">
                    <h3 class="h5 text-break mb-1">{{ $handoff->owner->email }}</h3>
                    <p class="text-muted mb-0">{{ $handoff->dateRange() }} ({{ $handoff->timezone }})</p>
                    @if ($handoff->relationLoaded('plants'))
                        <ul class="list-group list-group-flush mt-2">
                            @forelse ($handoff->plants as $plant)
                                <li class="list-group-item px-0">
                                    <h4 class="h6 mb-1">
                                        <a class="text-break" href="{{ route('plants', $plant) }}">{{ $plant->name }}</a>
                                    </h4>
                                    @if ($plant->room || $plant->species_name)
                                        <p class="text-muted mb-1">{{ implode(' · ', array_filter([$plant->room, $plant->species_name])) }}</p>
                                    @endif
                                    @if ($plant->pivot->instructions)
                                        <p class="text-break mb-1" style="white-space: pre-line;">{{ $plant->pivot->instructions }}</p>
                                    @endif
                                    @if ($plant->care_instructions)
                                        <p class="text-break mb-1" style="white-space: pre-line;">{{ $plant->care_instructions }}</p>
                                    @endif
                                    <p class="text-muted small mb-0">
                                        @if ($lastCheck = $plant->latestPlantCheck)
                                            Last checked {{ $lastCheck->checked_on }} ({{ $lastCheck->outcome->value === 'watered' ? 'watered' : 'still moist' }}) ·
                                        @endif
                                        @if ($plant->history?->watered_on || $plant->history?->watered_at)
                                            Last watered {{ $plant->history->watered_on ?? $plant->history->watered_at->setTimezone($handoff->timezone)->toDateString() }} ·
                                        @endif
                                        <a href="{{ route('plants', $plant) }}#care-history">See past watering</a>
                                        · <a href="{{ route('plants', $plant) }}#plant-checks">View plant checks</a>
                                    </p>
                                </li>
                            @empty
                                <li class="list-group-item px-0 text-muted">No selected plants are available.</li>
                            @endforelse
                        </ul>
                    @else
                        <p class="mb-0 mt-2">Scheduled. Plant instructions will appear when the handoff starts.</p>
                    @endif
                </article>
            @empty
                <p class="text-muted mb-0">You have no active or scheduled handoffs.</p>
            @endforelse
            {{ $incoming->links() }}
        </section>

        <section class="bloom-panel" aria-labelledby="create-handoff">
            <h2 class="h4" id="create-handoff">Create a handoff</h2>
            @if (! $viewer->hasVerifiedEmail())
                <p class="mb-0"><a href="{{ route('verification.notice') }}">Verify your email</a> before inviting a helper.</p>
            @elseif ($ownedPlants->isEmpty())
                <p class="mb-0"><a href="{{ route('add-plant') }}">Add a plant</a> to your garden first.</p>
            @else
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form method="POST" action="{{ route('handoff.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="handoff-email">Helper email address</label>
                        <input class="form-control" id="handoff-email" type="email" name="email" maxlength="255"
                               value="{{ is_string(old('email')) ? old('email') : '' }}" required>
                    </div>
                    <div class="row g-3 mb-3" aria-describedby="handoff-timezone">
                        <div class="col-sm">
                            <label class="form-label" for="handoff-start">Start date</label>
                            <input class="form-control" id="handoff-start" name="starts_on" type="date" min="{{ $today }}"
                                   value="{{ is_string(old('starts_on')) ? old('starts_on') : $today }}" required>
                        </div>
                        <div class="col-sm">
                            <label class="form-label" for="handoff-end">End date</label>
                            <input class="form-control" id="handoff-end" name="ends_on" type="date" min="{{ $today }}" max="9999-12-30"
                                   value="{{ is_string(old('ends_on')) ? old('ends_on') : '' }}" required>
                        </div>
                    </div>
                    <p class="form-text" id="handoff-timezone">Dates include the final day in your garden’s time zone ({{ $viewer->reminder_timezone }}).</p>

                    <fieldset class="mb-3">
                        <legend class="h5">Plants to share</legend>
                        <div class="list-group list-group-flush">
                            @foreach ($ownedPlants as $plant)
                                <div class="list-group-item px-0">
                                    <div class="form-check">
                                        <input class="form-check-input" id="handoff-plant-{{ $plant->id }}" type="checkbox"
                                               name="plant_ids[]" value="{{ $plant->id }}"
                                               @checked(in_array($plant->id, (array) old('plant_ids'), false))>
                                        <label class="form-check-label fw-semibold" for="handoff-plant-{{ $plant->id }}">{{ $plant->name }}</label>
                                    </div>
                                    <label class="form-label small mt-2" for="handoff-instructions-{{ $plant->id }}">Instructions for {{ $plant->name }} (optional)</label>
                                    <textarea class="form-control" id="handoff-instructions-{{ $plant->id }}" name="instructions[{{ $plant->id }}]"
                                              rows="2" maxlength="2000">{{ is_string(old('instructions.'.$plant->id)) ? old('instructions.'.$plant->id) : '' }}</textarea>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                    <button type="submit" class="btn btn-primary">Create invitation</button>
                </form>
            @endif
        </section>

        <section class="bloom-panel" aria-labelledby="outgoing-handoffs">
            <h2 class="h4" id="outgoing-handoffs">Handoffs you’ve created</h2>
            @if ($outgoing->isEmpty())
                <p class="mb-0">You haven’t created a handoff yet.</p>
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($outgoing as $handoff)
                        <li class="list-group-item px-0">
                            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                                <div>
                                    <h3 class="h6 text-break mb-1">{{ $handoff->email }}</h3>
                                    <p class="text-muted mb-1">{{ $handoff->dateRange() }} ({{ $handoff->timezone }})</p>
                                    <p class="small mb-0">
                                        @if ($handoff->revoked_at)
                                            Revoked
                                        @elseif ($handoff->ends_at->lte($instant))
                                            Ended
                                        @elseif (! $handoff->accepted_at)
                                            Awaiting acceptance
                                        @elseif (! $handoff->member?->hasVerifiedEmail() || $handoff->member->email !== $handoff->email)
                                            Helper’s invited address is unverified
                                        @elseif ($handoff->isActiveAt($instant))
                                            Active
                                        @else
                                            Scheduled
                                        @endif
                                    </p>
                                </div>
                                @if (! $handoff->revoked_at && $handoff->ends_at->gt($instant))
                                    <form method="POST" action="{{ route('handoff.destroy', $handoff) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger btn-sm" type="submit" aria-label="Revoke handoff for {{ $handoff->email }}">Revoke handoff</button>
                                    </form>
                                @endif
                            </div>
                            @if ($handoff->plants->isNotEmpty())
                                <details class="mt-2">
                                    <summary>Selected plants</summary>
                                    <ul class="mb-0">
                                        @foreach ($handoff->plants as $plant)
                                            <li class="text-break">
                                                {{ $plant->name }}
                                                @if ($plant->pivot->instructions)
                                                    <p class="text-muted mb-1" style="white-space: pre-line;">{{ $plant->pivot->instructions }}</p>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        </li>
                    @endforeach
                </ul>
                {{ $outgoing->links() }}
            @endif
        </section>
    </div>
@endsection
