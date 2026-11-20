<?php

namespace App\Console\Commands;

use App\Services\PlantJournalPhotos;
use App\Services\PlantPhotoCleanup;
use Illuminate\Console\Command;

class CleanupPlantPhotos extends Command
{
    protected $signature = 'photos:cleanup {--limit=10 : Maximum number of pending assets to process}';

    protected $description = 'Remove deleted or abandoned plant photos from Cloudinary';

    public function handle(PlantPhotoCleanup $cleanup, PlantJournalPhotos $photos): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($limit === false) {
            $this->error('The limit must be a positive integer.');

            return self::INVALID;
        }

        return $cleanup->process($photos, $limit) ? self::SUCCESS : self::FAILURE;
    }
}
