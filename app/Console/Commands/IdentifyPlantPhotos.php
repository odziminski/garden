<?php

namespace App\Console\Commands;

use App\Services\PlantIdentification;
use Illuminate\Console\Command;

class IdentifyPlantPhotos extends Command
{
    protected $signature = 'photos:identify';

    protected $description = 'Process pending Plant.id identification requests';

    public function handle(PlantIdentification $identification): int
    {
        $identification->processPending(5);

        return self::SUCCESS;
    }
}
