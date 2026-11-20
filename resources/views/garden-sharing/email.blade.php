<x-mail::message>
# Garden invitation

{{ $ownerEmail }} invited you to help care for their plants on Bloomify.

<x-mail::button :url="$inviteUrl">
Review invitation
</x-mail::button>

Sign in with this email address and verify it before accepting. The link expires in seven days and can be used once.
</x-mail::message>
