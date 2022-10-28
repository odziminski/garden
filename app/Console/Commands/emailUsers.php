<?php

namespace App\Console\Commands;

use App\Mail\takeCareOfPlant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class emailUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:care';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Email users which plants require a care.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return boolean
     */
    public function handle(): bool
    {
        $emails = DB::table('plants')
            ->join('users', 'plants.user_id', '=', 'users.id')
            ->join('needs', 'plants.id', '=', 'needs.plant_id')
            ->select('users.email', 'plants.id', 'plants.name', 'plants.avatar')
            ->where(function ($query) {
                $query->where('needs.need_watering', 1)
                    ->orWhere('needs.need_fertilizing', 1);
            })->get();

        foreach ($emails as $email) {
            Mail::to($email)->send(new takeCareOfPlant($email));
        }
        return true;
    }
}
