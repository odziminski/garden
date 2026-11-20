<?php

namespace App\Mail;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;

class DailyCareDigest extends Mailable
{
    public function __construct(public Collection $plants)
    {
    }

    public function build(): self
    {
        return $this->subject('Your daily plant care reminders')->markdown('plants.care-digest');
    }
}
