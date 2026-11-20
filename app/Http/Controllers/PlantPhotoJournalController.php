<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlantPhotoEntryRequest;
use App\Models\Plant;
use App\Models\PlantPhotoEntry;
use App\Services\PlantJournalPhotos;
use App\Services\PlantPhotoCleanup;
use App\Services\PlantPhotoStorageException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlantPhotoJournalController extends Controller
{
    public function store(StorePlantPhotoEntryRequest $request, Plant $plant, PlantJournalPhotos $photos, PlantPhotoCleanup $cleanup): RedirectResponse
    {
        $returnToJournal = to_route('plants', $plant)->withFragment('photo-journal');
        if (!$request->user()->hasVerifiedEmail()) {
            return $returnToJournal
                ->withErrors(['photo' => 'Verify your email before uploading plant photos.'], 'photoJournal')
                ->withInput($request->safe()->only(['taken_on', 'note']));
        }

        $publicId = 'plant_journal/'.Str::uuid();
        try {
            $format = $photos->upload($request->file('photo'), $publicId);
        } catch (PlantPhotoStorageException $exception) {
            return $returnToJournal
                ->withErrors(['photo' => $exception->getMessage()], 'photoJournal')
                ->withInput($request->safe()->only(['taken_on', 'note']));
        }

        DB::transaction(function () use ($request, $plant, $publicId, $format, $cleanup) {
            $lockedPlant = $request->user()->plants()->lockForUpdate()->findOrFail($plant->getKey());

            PlantPhotoEntry::query()->create([
                'plant_id' => $lockedPlant->getKey(),
                'taken_on' => $request->validated('taken_on'),
                'note' => $request->validated('note'),
                'public_id' => $publicId,
                'format' => $format,
            ]);
            $cleanup->keepUpload($publicId);
        }, 3);

        return $returnToJournal->with('photoJournalStatus', 'Photo added to the journal.');
    }

    public function show(Plant $plant, string $entry, PlantJournalPhotos $photos): RedirectResponse
    {
        $photo = PlantPhotoEntry::query()->forPlant($plant)->findOrFail($entry);

        try {
            $url = $photos->downloadUrl($photo->public_id, $photo->format, now());
        } catch (PlantPhotoStorageException $exception) {
            abort(503, $exception->getMessage());
        }

        return redirect()->away($url, 302, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function destroy(Request $request, Plant $plant, string $entry, PlantPhotoCleanup $cleanup): RedirectResponse
    {
        $returnToJournal = to_route('plants', $plant)->withFragment('photo-journal');

        DB::transaction(function () use ($request, $plant, $entry, $cleanup) {
            $lockedPlant = $request->user()->plants()->lockForUpdate()->findOrFail($plant->getKey());
            $photo = PlantPhotoEntry::query()->forPlant($lockedPlant)->lockForUpdate()->findOrFail($entry);
            $cleanup->enqueue($photo->public_id);
            $photo->delete();
        }, 3);

        return $returnToJournal->with('photoJournalStatus', 'Photo removed from the journal.');
    }
}
