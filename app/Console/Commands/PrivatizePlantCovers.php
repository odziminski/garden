<?php

namespace App\Console\Commands;

use App\Models\Plant;
use App\Services\PlantJournalPhotos;
use App\Services\PlantPhoto;
use App\Services\PlantPhotoStorageException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrivatizePlantCovers extends Command
{
    protected $signature = 'photos:privatize-covers';

    protected $description = 'Copy legacy public Cloudinary covers to authenticated storage and queue the originals for deletion';

    public function handle(PlantPhoto $covers, PlantJournalPhotos $storage): int
    {
        $failed = false;
        $plants = Plant::query()->whereNull('cover_public_id')
            ->whereNotIn('avatar', ['', 'plant.png'])
            ->where('avatar', 'not like', '%/images/plant.png')->lazyById();

        foreach ($plants as $plant) {
            $legacy = $covers->legacyCover($plant->avatar);
            if ($legacy === null) {
                $this->warn("Plant {$plant->id} has an unrecognized legacy cover.");
                $failed = true;

                continue;
            }

            try {
                $publicId = 'plant_covers/'.Str::uuid();
                $format = $storage->upload($plant->avatar, $publicId, $legacy['cloud']);

                DB::transaction(function () use ($plant, $covers, $publicId, $format) {
                    $current = Plant::query()->lockForUpdate()->find($plant->id);
                    if ($current !== null && $current->cover_public_id === null && $current->avatar === $plant->avatar) {
                        $covers->discardCover($current);
                        $covers->attachCover($current, $publicId, $format, identify: false);
                    }
                }, 3);
            } catch (PlantPhotoStorageException $exception) {
                $this->warn("Plant {$plant->id} cover could not be migrated. ".$exception->getMessage());
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
