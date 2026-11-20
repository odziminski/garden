@extends('layouts.app')

@section('content')
    <div class="container">
        <section class="bloom-hero" aria-labelledby="welcome-heading">
            <div class="bloom-hero-copy">
                <h1 id="welcome-heading">Good things<br> <em>grow with care.</em></h1>
                <p>See which plants to check, record what you did, and share care when you travel.</p>
                <div class="bloom-actions">
                    @auth
                        @if ($plant)
                            <a class="btn btn-primary" href="{{ route('browse') }}">Open your garden <span aria-hidden="true">→</span></a>
                            @if (Route::has('care.today'))
                                <a class="btn btn-outline-secondary" href="{{ route('care.today') }}">Today’s care</a>
                            @endif
                        @else
                            <a class="btn btn-primary" href="{{ route('add-plant') }}">Add your first plant <span aria-hidden="true">→</span></a>
                        @endif
                    @else
                        <a class="btn btn-primary" href="{{ route('register') }}">Start your garden <span aria-hidden="true">→</span></a>
                        <a class="btn btn-outline-secondary" href="{{ route('login') }}">Log in</a>
                    @endauth
                </div>
            </div>
            <figure class="bloom-hero-media">
                <img src="{{ asset('images/monstera.webp') }}" alt="Sunlit monstera in a terracotta pot" width="1000" height="1250" fetchpriority="high">
                <figcaption><i>Monstera deliciosa</i></figcaption>
            </figure>
        </section>

        @if ($plant)
            <section class="bloom-spotlight bloom-panel" aria-labelledby="spotlight-heading">
                <div class="bloom-spotlight-image">
                    <img src="{{ $plant->avatarUrl() }}" alt="" width="144" height="144" loading="lazy">
                </div>
                <div class="bloom-spotlight-name">
                    <h2 id="spotlight-heading">{{ $plant->name }}</h2>
                    @if ($plant->plantData)
                        <p class="text-body-secondary">{{ $plant->plantData->plant_name }}</p>
                    @endif
                    <a href="{{ route('plants', $plant) }}">View plant <span aria-hidden="true">→</span></a>
                </div>
                <div class="bloom-spotlight-care">
                    <div>
                        <span class="text-body-secondary">Next soil check</span>
                        <strong>{{ $plant->checkSchedule?->next_check_on ?? $nextWatering?->diffForHumans() ?? 'No reminder' }}</strong>
                        @if (!$plant->checkSchedule?->isScheduled() && $nextWatering)
                            <div class="progress mt-2" role="progressbar" aria-label="Watering interval elapsed"
                                 aria-valuenow="{{ $wateringPercentage }}" aria-valuemin="0" aria-valuemax="100">
                                <div @class(['progress-bar', 'bg-danger' => $wateringPercentage === 100])
                                     style="width: {{ $wateringPercentage }}%"></div>
                            </div>
                        @endif
                    </div>
                    <div>
                        <span class="text-body-secondary">Next feeding review</span>
                        <strong>{{ $nextFertilizing?->diffForHumans() ?? 'No schedule' }}</strong>
                        @if ($nextFertilizing)
                            <div class="progress mt-2" role="progressbar" aria-label="Fertilizing interval elapsed"
                                 aria-valuenow="{{ $fertilizingPercentage }}" aria-valuemin="0" aria-valuemax="100">
                                <div @class(['progress-bar', 'bg-danger' => $fertilizingPercentage === 100])
                                     style="width: {{ $fertilizingPercentage }}%"></div>
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @elseif (auth()->guest())
            <section class="bloom-feature-grid" aria-label="Garden tools">
                <article>
                    <span class="bloom-feature-icon" aria-hidden="true">◇</span>
                    <div>
                        <h2>Your plant collection</h2>
                        <p>Keep plants organized by room and leave a note on what to check.</p>
                    </div>
                </article>
                <article>
                    <span class="bloom-feature-icon bloom-feature-icon--water" aria-hidden="true">◔</span>
                    <div>
                        <h2>Daily checks</h2>
                        <p>Check the soil and record which plants you watered.</p>
                    </div>
                </article>
                <article>
                    <span class="bloom-feature-icon bloom-feature-icon--journal" aria-hidden="true">≡</span>
                    <div>
                        <h2>Plant journal</h2>
                        <p>Save photos and notes as your plants grow.</p>
                    </div>
                </article>
            </section>
        @endif
    </div>
@endsection
