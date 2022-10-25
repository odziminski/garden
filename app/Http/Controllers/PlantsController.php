<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlantRequest;
use App\Models\History;
use App\Models\Needs;
use App\Models\Plant;
use App\Models\PlantData;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;


class PlantsController extends Controller
{
    protected $dates = ['created_at'];


    public function getRandomPlant()
    {
//        $randomPlant = Plant::where('user_id', auth()->id())
//            ->inRandomOrder()
//            ->get()
//            ->first();

        $randomPlant = Plant::with('history', 'needs', 'plantData')
            ->get()
            ->where('id', 741)
            ->first();

        if ($randomPlant) {
            $randomPlant->watered_at = Plant::getDateForHumans($randomPlant->watered_at);
            $randomPlant->fertilized_at = Plant::getDateForHumans($randomPlant->fertilized_at);
            $nextWatering = Plant::getNextCareDate($randomPlant->watered_at, $randomPlant->watering_frequency)->diffForHumans();
            $nextFertilizing = Plant::getNextCareDate($randomPlant->fertilized_at, $randomPlant->fertilizing_frequency)->diffForHumans();

            return view('welcome')->with([
                'plant' => $randomPlant,
                'nextWatering' => $nextWatering,
                'nextFertilizing' => $nextFertilizing,
            ]);
        } else {
            return view('welcome');
        }
    }

    public function displaySinglePlant($id)
    {
        $plant = Plant::with('history', 'needs', 'plantData')
            ->get()
            ->where('id', $id)
            ->first();

        if ($plant->user_id == auth()->id()) {
            $nextWatering = Plant::getNextCareDate($plant->history->watered_at, $plant->needs->watering_frequency);
            $lateForWatering = Carbon::parse($nextWatering)
                ->diffInDays(Carbon::now(), false);

            $nextFertilizing = Plant::getNextCareDate($plant->history->fertilized_at, $plant->needs->fertilizing_frequency);

            $lateForFertilizing = Carbon::parse($nextFertilizing)
                ->diffInDays(Carbon::now(), false);


            return view('plants')->with([
                'plant' => $plant,
                'nextWatering' => $nextWatering,
                'nextFertilizing' => $nextFertilizing,
                'lateForWatering' => $lateForWatering,
                'lateForFertilizing' => $lateForFertilizing,
            ]);
        } else {
            return back();
        }
    }

    public function store(StorePlantRequest $request)
    {
        $noImage = false;
        if ($request->webcamAvatar) {
            $uploadedFileUrl = (self::prepareWebcamAvatar($request->webcamAvatar)->storeOnCloudinary('user_uploads'))->getSecurePath();
            $plantIdData = self::identifyPlant($request->webcamAvatar, true);
        } else if ($request->hasFile('avatar')) {
            $plantIdData = self::identifyPlant($request->file('avatar'), false);
            $uploadedFileUrl = ($request->file('avatar')->storeOnCloudinary('user_uploads'))->getSecurePath();
        } else {
            if (!empty($uploadedFileUrl))
                $uploadedFileUrl = $uploadedFileUrl['image_url'];
            else {
                $uploadedFileUrl = asset("images/plant.png");
                $noImage = true;
            }
        }

        try {
            $now = Carbon::now();
            $plant = Plant::create([
                'avatar' => $uploadedFileUrl,
                'user_id' => auth()->user()->id,
                'name' => $request->input('name'),
                'created_at' => $now,

            ]);
            History::create([
                'plant_id' => $plant->id,
                'watered_at' => $now,
                'fertilized_at' => $now
            ]);

            Needs::create([
                'plant_id' => $plant->id,
                'watering_frequency' => $request->input('watering_frequency'),
                'fertilizing_frequency' => $request->input('fertilizing_frequency')
            ]);
            if (!$noImage) {
                PlantData::create([
                    'plant_id' => $plant->id,
                    'plant_name' => $plantIdData->plant_name,
                    'common_name' => $plantIdData->common_name,
                    'wikipedia_url' => $plantIdData->wikipedia_url,
                    'wikipedia_description' => $plantIdData->wikipedia_description,
                    'taxonomy_class' => $plantIdData->taxonomy_class,
                    'taxonomy_family' => $plantIdData->taxonomy_family,
                    'taxonomy_genus' => $plantIdData->taxonomy_genus,
                    'taxonomy_kingdom' => $plantIdData->taxonomy_kingdom,
                    'taxonomy_order' => $plantIdData->taxonomy_order,
                    'taxonomy_phylum' => $plantIdData->taxonomy_phylum,
                ]);
            }
        } catch (QueryException $e) {
            $err = $e->getPrevious()->getMessage();
            echo $err;
        }
        $plant ? $message = "Plant added successfully" : $message = "Error while adding new plant";

        if ($plant) {
            return redirect()->route('browse')->with('message', $message);
        } else {
            return redirect()->route('add-plant')->with('message', $message);
        }
    }

    public function displayPlants()
    {
        $plants = Plant::with('history', 'needs', 'plantData')
            ->get()
            ->where('user_id', auth()->id())
            ->sortByDesc('history.watered_at');
        $nextWatering = "";
        $nextFertilizing = "";
        foreach ($plants as $plant) {
            if (isset($plant->history->watered_at)) {
                $plant->watered_at = Plant::getDateForHumans($plant->history->watered_at);
            }
            if (isset($plant->history->fertilized_at)) {
                $plant->fertilized_at = Plant::getDateForHumans($plant->history->fertilized_at);
            }
            if (isset($plant->needs->need_watering)) {
                $plant->need_watering = $plant->needs->need_watering;
                $nextWatering = Plant::getNextCareDate($plant->watered_at, $plant->watering_frequency)->diffForHumans();

            }
            if (isset($plant->needs->need_fertilizing)) {
                $plant->need_fertilizing = $plant->needs->need_fertilizing;
                $nextFertilizing = Plant::getNextCareDate($plant->fertilized_at, $plant->fertilizing_frequency)->diffForHumans();

            }
        }
//        return view('browse')->with('plants', [$plants,$nextWatering,$nextFertilizing]);
        return view('browse')->with([
            'plants' => $plants,
            'nextWatering' => $nextWatering,
            'nextFertilizing' => $nextFertilizing,
        ]);
    }


    public function displayEditPlant($id)
    {
        $plant = Plant::with('history', 'needs')
            ->get()
            ->where('id', $id)
            ->first();

        return view('edit-plant')->with('plant', $plant);
    }

    public function editPlant($id, StorePlantRequest $request)
    {
        $wateringFrequency = $request->input('watering_frequency');
        $fertilizingFrequency = $request->input('fertilizing_frequency');

        if ($request->webcamAvatar) {
            $uploadedFileUrl = (self::prepareWebcamAvatar($request->webcamAvatar)->storeOnCloudinary('user_uploads'))->getSecurePath();
            $plantIdData = self::identifyPlant($request->webcamAvatar, true);
        } else if ($request->hasFile('avatar')) {
            $plantIdData = self::identifyPlant($request->file('avatar'), false);
            $uploadedFileUrl = ($request->file('avatar')->storeOnCloudinary('user_uploads'))->getSecurePath();
        } else {
            $uploadedFileUrl = Plant::where('id', $id)->value('avatar');
        }

        try {
            $plant = Plant::findOrFail($id);
            $plant->update([
                'avatar' => $uploadedFileUrl,
                'name' => $request->input('name')
            ]);

            $needs = Needs::where('plant_id', $plant->id)->first();
            $needs->update([
                'watering_frequency' => $request->input('watering_frequency'),
                'fertilizing_frequency' => $request->input('fertilizing_frequency')
            ]);
        } catch (\Exception $e) {
            $err = $e->getPrevious()->getMessage();
            echo $err;
        }

        return redirect()->route('browse');
    }

    public function deletePlant($id)
    {
        Plant::find($id)->delete();
        Needs::where('plant_id', $id)->delete();
        History::where('plant_id', $id)->delete();
        PlantData::where('plant_id', $id)->delete();
        return redirect()->route('browse');
    }


    public function prepareWebcamAvatar($webcamImage)
    {
        $fileData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $webcamImage));

        $tmpFilePath = sys_get_temp_dir() . '/' . Str::uuid()->toString();
        file_put_contents($tmpFilePath, $fileData);

        $tmpFile = new File($tmpFilePath);

        $file = new UploadedFile(
            $tmpFile->getPathname(),
            $tmpFile->getFilename(),
            $tmpFile->getMimeType(),
            0,
            true
        );
        return $file;
    }

    public function identifyPlant($base64Image, $alreadyEncoded)
    {
        if (!$alreadyEncoded) {
            $base64Image = base64_encode(file_get_contents($base64Image));
        }

        $apiURL = 'https://api.plant.id/v2/identify';


        $header = [
            "Content-Type" => "application/json",
        ];

        $params = [
            "api_key" => env('PLANTID_API_KEY'),
            "images" => [$base64Image],
            "modifiers" => ["crops_simple", "similar_images"],
            "plant_language" => "en",
            "plant_details" => [
                "common_names",
                "url",
                "name_authority",
                "wiki_description",
                "taxonomy",
                "synonyms"
            ]
        ];

        $response = Http::withHeaders($header)->post($apiURL, $params);
        $responseBody = json_decode($response->getBody());

        return (object)([
            'plant_name' => $responseBody->suggestions[0]->plant_name,
            'common_name' => $responseBody->suggestions[0]->plant_details->common_names[0],
            'taxonomy_class' => $responseBody->suggestions[0]->plant_details->taxonomy->class,
            'taxonomy_family' => $responseBody->suggestions[0]->plant_details->taxonomy->family,
            'taxonomy_genus' => $responseBody->suggestions[0]->plant_details->taxonomy->genus,
            'taxonomy_kingdom' => $responseBody->suggestions[0]->plant_details->taxonomy->kingdom,
            'taxonomy_order' => $responseBody->suggestions[0]->plant_details->taxonomy->order,
            'taxonomy_phylum' => $responseBody->suggestions[0]->plant_details->taxonomy->phylum,
            'wikipedia_url' => $responseBody->suggestions[0]->plant_details->url,
            'wikipedia_description' => $responseBody->suggestions[0]->plant_details->wiki_description->value,
        ]);
    }
}
