<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Http\Requests\StorePlantRequest;
use App\Models\Plant;
use App\Models\History;
use App\Models\Needs;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;
use Illuminate\Support\Facades\Http;


class PlantsController extends Controller
{
    protected $dates = ['created_at'];



    public function getRandomPlant()
    {
        $randomPlant = Plant::where('user_id', auth()->id())
            ->inRandomOrder()
            ->get()
            ->first();

        if ($randomPlant) {
            $randomPlant->watered_at = Plant::getDateForHumans($randomPlant->watered_at);
            $randomPlant->fertilized_at = Plant::getDateForHumans($randomPlant->fertilized_at);
            $nextWatering = Plant::getNextCareDate($randomPlant->watered_at, $randomPlant->watering_frequency)->diffForHumans();
            $nextFertilizing = Plant::getNextCareDate($randomPlant->fertilized_at, $randomPlant->fertilizing_frequency)->diffForHumans();
            // $trefleData = Plant::getTrefleData($randomPlant->species);
            return view('welcome')->with([
                'plant' => $randomPlant,
                'nextWatering' => $nextWatering,
                'nextFertilizing' => $nextFertilizing,
                // 'trefleData' => $trefleData,
            ]);
        } else {
            return view('welcome');
        }
    }

    public function displaySinglePlant($id)
    {
        $plant = Plant::with('history', 'needs')
            ->get()
            ->where('id', $id)
            ->first();

        if ($plant->user_id == auth()->id()) {
            $nextWatering = Plant::getNextCareDate($plant->history->watered_at, $plant->needs->watering_frequency);
            $lateForWatering = Carbon::parse($nextWatering)
                ->diffInDays(Carbon::now(), false);
            $nextWatering = $nextWatering->format('l, j-m-Y ');

            $nextFertilizing = Plant::getNextCareDate($plant->history->fertilized_at, $plant->needs->fertilizing_frequency);

            $lateForFertilizing = Carbon::parse($nextFertilizing)
                ->diffInDays(Carbon::now(), false);
            $nextFertilizing = $nextFertilizing->format('l, j-m-Y ');


            // $trefleData = $this->getTrefleData($plant->species);
            return view('plants')->with([
                'plant' => $plant,
                'nextWatering' => $nextWatering,
                'nextFertilizing' => $nextFertilizing,
                'lateForWatering' => $lateForWatering,
                'lateForFertilizing' => $lateForFertilizing,
                // 'trefleData' => $trefleData,
            ]);
        } else {
            return back();
        }
    }

    public function store(StorePlantRequest $request)
    {

        if ($request->webcamAvatar) {
            $uploadedFileUrl = (self::prepareWebcamAvatar($request->webcamAvatar)->storeOnCloudinary('user_uploads'))->getSecurePath();
            $plantIdData = self::identifyPlant($request->webcamAvatar, true);
        } else if ($request->hasFile('avatar')) {
            $plantIdData = self::identifyPlant($request->file('avatar'), false);

            $uploadedFileUrl = ($request->file('avatar')->storeOnCloudinary('user_uploads'))->getSecurePath();
        } else {
            if (!empty($uploadedFileUrl)) 
                $uploadedFileUrl = $uploadedFileUrl['image_url'];
             else 
                $uploadedFileUrl = asset("images/plant.png");
         }
        try {
            dd($plantIdData);
            $now = Carbon::now();
            $plant = Plant::create([
                'avatar' => $uploadedFileUrl,
                'user_id' => auth()->user()->id,
                'name' => $request->input('name'),
                'species' => $request->input('species'),
                'created_at' => $now,

            ]);
            $history = History::create([
                'plant_id' => $plant->id,
                'watered_at' => $now,
                'fertilized_at' => $now
            ]);

            $needs = Needs::create([
                'plant_id' => $plant->id,
                'watering_frequency' => $request->input('watering_frequency'),
                'fertilizing_frequency' => $request->input('fertilizing_frequency')
            ]);
        } catch (\Exception $e) {
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
        $plants = Plant::with('history', 'needs')
            ->get()
            ->where('user_id', auth()->id())
            ->sortByDesc('history.watered_at');

        foreach ($plants as $plant) {
            if (isset($plant->history->watered_at)) {
                $plant->watered_at = Plant::getDateForHumans($plant->history->watered_at);
            }
            if (isset($plant->history->fertilized_at)) {
                $plant->fertilized_at = Plant::getDateForHumans($plant->history->fertilized_at);
            }
            if (isset($plant->needs->need_watering)) {
                $plant->need_watering = $plant->needs->need_watering;
            }
            if (isset($plant->needs->need_fertilizing)) {
                $plant->need_fertilizing = $plant->needs->need_fertilizing;
            }
        }
        return view('browse')->with('plants', $plants);
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

        if ($request->hasFile('avatar')) {
            $uploadedFileUrl = ($request->file('avatar')->storeOnCloudinary('user_uploads'))->getSecurePath();
        } else {
            $uploadedFileUrl = Plant::where('id', $id)->value('avatar');
        }

        try {
            $plant = Plant::findOrFail($id)
                ->update([
                    'avatar' => $uploadedFileUrl,
                    'name' => $request->input('name'),
                    'watering_frequency' => $wateringFrequency,
                    'fertilizing_frequency' => $fertilizingFrequency
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

        return redirect()->route('browse');
    }

    public function getTrefleData($species)
    {
        $species = rawurlencode($species);
        $token = env('TREFLE_TOKEN');

        //$response = Http::get('https://trefle.io/api/v1/species/search?token='.$token.'&q='.$species)->json();
        //        if ($response) {
        //            return $data = $response['data'][0];
        //        } else {
        //            return $data = [];
        //        }
    }

    public function prepareWebcamAvatar($webcamImage)
    {


        // decode the base64 file
        $fileData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $webcamImage));

        // save it to temporary dir first.
        $tmpFilePath = sys_get_temp_dir() . '/' . Str::uuid()->toString();
        file_put_contents($tmpFilePath, $fileData);

        // this just to help us get file info.
        $tmpFile = new File($tmpFilePath);

        $file = new UploadedFile(
            $tmpFile->getPathname(),
            $tmpFile->getFilename(),
            $tmpFile->getMimeType(),
            0,
            true // Mark it as test, since the file isn't from real HTTP POST.
        );
        return $file;
    }

    public function identifyPlant($base64Image, $alreadyEncoded)
    {
        if (!$alreadyEncoded) {
            $base64Image = base64_encode(file_get_contents($base64Image));
        }
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
        $apiURL = 'https://api.plant.id/v2/identify';


        $header = [
            "Content-Type" => "application/json"
        ];

        $response = Http::withHeaders($header)->post($apiURL, $params);
        $responseBody = json_decode($response->getBody());

        return ($responseBody);
    }
}
