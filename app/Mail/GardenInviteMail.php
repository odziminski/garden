<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class GardenInviteMail extends Mailable
{
    public function __construct(public string $ownerEmail, public string $inviteUrl) {}

    public function build(): self
    {
        return $this->subject('You’re invited to a garden on Bloomify')->markdown('garden-sharing.email');
    }
}
