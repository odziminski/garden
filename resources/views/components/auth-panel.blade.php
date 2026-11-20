@props(['heading', 'description' => null])

<div class="container">
    <section class="bloom-auth" aria-labelledby="auth-heading">
        <div class="bloom-auth-content">
            <h1 id="auth-heading">{{ $heading }}</h1>
            @if ($description)
                <p class="text-body-secondary mb-4">{{ $description }}</p>
            @endif

            {{ $slot }}
        </div>
        <div class="bloom-auth-media" aria-hidden="true"></div>
    </section>
</div>
