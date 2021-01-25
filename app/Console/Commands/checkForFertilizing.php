<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class checkForFertilizing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checkForFertilizing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will check if the plant in DB needs to be fertilized';

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
        UPDATE plants SET need_fertilizing = 1 WHERE 
        DATE_ADD(plants.fertilized_at, INTERVAL plants.fertilizing_frequency DAY) <= CURRENT_TIMESTAMP()
        ');
    }
}
