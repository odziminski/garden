<?php

namespace App\Mail;

use App\Models\Plant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TakeCareOfPlant extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Plant $plant)
    {
    }

    public function build(): self
    {
        return $this->subject('Time for plant care!')
            ->markdown('plants.takecare');
    }
}
