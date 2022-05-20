<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class checkForWatering extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checkForWatering';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will check if the plant in DB needs watering';

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
     * @return int
     */
    public function handle()
    {
        DB::connection()
        ->getPdo()
        ->exec('
        UPDATE needs INNER JOIN history ON history.plant_id = needs.plant_id SET need_watering = 1 WHERE needs.plant_id = history.plant_id AND
        DATE_ADD(history.watered_at, INTERVAL needs.watering_frequency DAY) <= CURRENT_TIMESTAMP()
        ');
    }


}
