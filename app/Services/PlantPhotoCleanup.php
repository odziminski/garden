<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PlantPhotoCleanup
{
    public function reserveUpload(string $publicId, string $provider = 'cloudinary'): void
    {
        // Give the request time to upload and commit before treating its asset as abandoned.
        DB::table('plant_photo_cleanup')->insert([
            'public_id' => $publicId,
            'provider' => $provider,
            'delivery_type' => 'authenticated',
            'available_at' => now()->addHour(),
        ]);
    }

    public function keepUpload(string $publicId): void
    {
        $cleared = DB::table('plant_photo_cleanup')->where('public_id', $publicId)
            ->where('delivery_type', 'authenticated')->whereNull('claim_token')
            ->where('available_at', '>', now())->delete();

        if ($cleared !== 1) {
            throw new PlantPhotoStorageException('The photo upload expired. Please try again.');
        }
    }

    public function enqueue(string $publicId, ?string $legacyUrl = null, string $provider = 'cloudinary'): void
    {
        DB::table('plant_photo_cleanup')->insertOrIgnore([
            'public_id' => $publicId,
            'provider' => $provider,
            'delivery_type' => $legacyUrl === null ? 'authenticated' : 'upload',
            'legacy_url' => $legacyUrl,
            'available_at' => now(),
        ]);
    }

    public function process(PlantJournalPhotos $photos, int $limit): bool
    {
        $now = now();
        $entries = DB::table('plant_photo_cleanup')->where('available_at', '<=', $now)
            ->whereNotExists(fn ($plants) => $plants->selectRaw('1')->from('plants')
                ->whereColumn('plants.avatar', 'plant_photo_cleanup.legacy_url'))
            ->orderBy('available_at')->orderBy('id')->limit($limit)->get();
        $success = true;

        foreach ($entries as $entry) {
            $token = (string) Str::uuid();
            $claim = DB::table('plant_photo_cleanup')->where('id', $entry->id)
                ->where('available_at', '<=', $now)->update(['available_at' => now()->addMinutes(5), 'claim_token' => $token]);
            if ($claim === 0) {
                continue;
            }

            try {
                $cloud = $entry->legacy_url === null ? null : explode('/', $entry->legacy_url)[3];
                $photos->delete($entry->public_id, $entry->delivery_type, $cloud, $entry->provider);
                DB::table('plant_photo_cleanup')->where('id', $entry->id)->where('claim_token', $token)->delete();
            } catch (PlantPhotoStorageException) {
                Log::warning('Plant photo cleanup will be retried.', ['cleanup_id' => $entry->id]);
                $success = false;
            }
        }

        return $success;
    }
}
