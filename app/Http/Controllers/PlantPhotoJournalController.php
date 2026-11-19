<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlantPhotoEntryRequest;
use App\Models\Plant;
use App\Models\PlantPhotoEntry;
use App\Services\PlantJournalPhotos;
use App\Services\PlantPhotoStorageException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PlantPhotoJournalController extends Controller
{
    public function store(StorePlantPhotoEntryRequest $request, Plant $plant, PlantJournalPhotos $photos): RedirectResponse
    {
        $returnToJournal = to_route('plants', $plant)->withFragment('photo-journal');
        $publicId = 'plant_journal/'.Str::uuid();

        try {
            $format = $photos->upload($request->file('photo'), $publicId);
        } catch (PlantPhotoStorageException $exception) {
            return $returnToJournal
                ->withErrors(['photo' => $exception->getMessage()], 'photoJournal')
                ->withInput($request->safe()->only(['taken_on', 'note']));
        }

        try {
            DB::transaction(function () use ($request, $plant, $publicId, $format) {
                $lockedPlant = $request->user()->plants()->lockForUpdate()->findOrFail($plant->getKey());

                PlantPhotoEntry::query()->create([
                    'plant_id' => $lockedPlant->getKey(),
                    'taken_on' => $request->validated('taken_on'),
                    'note' => $request->validated('note'),
                    'public_id' => $publicId,
                    'format' => $format,
                ]);
            }, 3);
        } catch (Throwable $exception) {
            $photos->discard($publicId);

            throw $exception;
        }

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

        return redirect()->away($url, 302, ['Cache-Control' => 'private, no-store']);
    }

    public function destroy(Plant $plant, string $entry, PlantJournalPhotos $photos): RedirectResponse
    {
        $returnToJournal = to_route('plants', $plant)->withFragment('photo-journal');

        try {
            DB::transaction(function () use ($plant, $entry, $photos) {
                $photo = PlantPhotoEntry::query()->forPlant($plant)->lockForUpdate()->findOrFail($entry);
                $photos->delete($photo->public_id);
                $photo->delete();
            });
        } catch (PlantPhotoStorageException $exception) {
            return $returnToJournal->withErrors(['delete' => $exception->getMessage()], 'photoJournal');
        }

        return $returnToJournal->with('photoJournalStatus', 'Photo removed from the journal.');
    }
}
