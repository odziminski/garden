<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Http\Requests\StorePlantRequest;
use App\Models\Plant;
use App\Models\History;
use App\Models\Needs;
use App\Models\PlantData;
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
            $plantData = PlantData::create([
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

        // $response = Http::withHeaders($header)->post($apiURL, $params);
        // $responseBody = json_decode($response->getBody(),true);
        // $stdClass = json_decode(json_encode($booking));
        $responseBody = '{
            "id":22062232,
            "custom_id":null,
            "meta_data":{
               "latitude":null,
               "longitude":null,
               "date":"2021-06-28",
               "datetime":"2021-06-28"
            },
            "uploaded_datetime":1624881923.496076,
            "finished_datetime":1624881924.130878,
            "images":[
               {
                  "file_name":"757562b36f1942209d4c96720967dfe8.jpg",
                  "url":"https://plant.id/media/images/757562b36f1942209d4c96720967dfe8.jpg"
               }
            ],
            "suggestions":[
               {
                  "id":148452111,
                  "plant_name":"Taraxacum officinale",
                  "plant_details":{
                     "common_names":[
                        "common dandelion"
                     ],
                     "edible_parts":[
                        "flowers",
                        "leaves"
                     ],
                     "propagation_methods":[
                        "division",
                        "seeds"
                     ],
                     "synonyms":[
                        "Chondrilla taraxacum",
                        "Leontodon taraxacum",
                        "Leontodon taraxacum var. vulgare",
                        "Leontodon vulgaris",
                        "Taraxacum dens-leonis",
                        "Taraxacum officinale subsp. vulgare",
                        "Taraxacum palustre var. vulgare",
                        "Taraxacum taraxacum",
                        "Taraxacum vulgare"
                     ],
                     "taxonomy":{
                        "class":"Magnoliopsida",
                        "family":"Asteraceae",
                        "genus":"Taraxacum",
                        "kingdom":"Plantae",
                        "order":"Asterales",
                        "phylum":"Magnoliophyta"
                     },
                     "url":"https://en.wikipedia.org/wiki/Taraxacum_officinale",
                     "wiki_description":{
                        "value":"Taraxacum officinale, the common dandelion (often simply called \"dandelion\"), is a flowering herbaceous...",
                        "citation":"https://en.wikipedia.org/wiki/Taraxacum_officinale",
                        "license_name":"CC BY-SA 3.0",
                        "license_url":"https://creativecommons.org/licenses/by-sa/3.0/"
                     },
                     "wiki_image":{
                        "value":"https://plant...f.jpg",
                        "citation":"P\u00f6ll\u00f6",
                        "license_name":"CC BY 3.0",
                        "license_url":"https://creativecommons.org/licenses/by/3.0/"
                     },
                     "scientific_name":"Taraxacum officinale",
                     "structured_name":{
                        "genus":"taraxacum",
                        "species":"officinale"
                     }
                  },
                  "probability":0.8093659420386023,
                  "confirmed":false,
                  "similar_images":[
                     {
                        "id":"4be03de02e22a9a9c834052eb0eb39bb",
                        "similarity":0.6665844130453076,
                        "url":"https://plant...0eb39bb.jpg",
                        "url_small":"https://plant...0eb39bb.small.jpg"
                     },
                     {
                        "id":"fd921e3d368b2135c1e480bcda448e25",
                        "similarity":0.38957578260133574,
                        "url":"https://plant...a448e25.jpg",
                        "url_small":"https://plant...a448e25.small.jpg"
                     }
                  ]
               },
               {
                  "id":148452112,
                  "plant_name":"Taraxacum erythrospermum",
                  "plant_details":{
                     "common_names":[
                        "red-seeded dandelion"
                     ],
                     "edible_parts":[
                        "flowers",
                        "leaves"
                     ],
                     "propagation_methods":null,
                     "synonyms":[
                        "Leontodon erythrospermum",
                        "Leontodon erythrospermus",
                        "Leontodon laevigatus",
                        "Leontodon taraxacum var. laevigatus",
                        "Taraxacum austriacum",
                        "Taraxacum austriacum var. denubium",
                        "Taraxacum austriacum var. punctatum",
                        "Taraxacum laevigatum",
                        "Taraxacum laevigatum f. scapifolium",
                        "Taraxacum laevigatum var. erythrospermum",
                        "Taraxacum officinale var. erythrospermum",
                        "Taraxacum officinale var. laevigatum",
                        "Taraxacum punctatum",
                        "Taraxacum scanicum",
                        "Taraxacum taraxacum var. laevigatum",
                        "Taraxacum tauricum"
                     ],
                     "taxonomy":{
                        "class":"Magnoliopsida",
                        "family":"Asteraceae",
                        "genus":"Taraxacum",
                        "kingdom":"Plantae",
                        "order":"Asterales",
                        "phylum":"Magnoliophyta"
                     },
                     "url":"https://en.wikipedia.org/wiki/Taraxacum_erythrospermum",
                     "wiki_description":{
                        "value":"Taraxacum erythrospermum, known by the common name red-seeded dandelion, is a species of dandelion...",
                        "citation":"https://en.wikipedia.org/wiki/Taraxacum_erythrospermum",
                        "license_name":"CC BY-SA 3.0",
                        "license_url":"https://creativecommons.org/licenses/by-sa/3.0/"
                     },
                     "wiki_image":{
                        "value":"https://plant...4.jpg",
                        "citation":"Javier martin",
                        "license_name":"CC0",
                        "license_url":"https://creativecommons.org/publicdomain/zero/1.0/"
                     },
                     "scientific_name":"Taraxacum erythrospermum",
                     "structured_name":{
                        "genus":"taraxacum",
                        "species":"erythrospermum"
                     }
                  },
                  "probability":0.11519229401471831,
                  "confirmed":false,
                  "similar_images":[
                     {
                        "id":"5fda47388d59246a34107f83de074229",
                        "similarity":0.8107533517457877,
                        "url":"https://plant...e074229.jpg",
                        "url_small":"https://plant...e074229.small.jpg",
                        "citation":"Ueli Schmid",
                        "license_name":"CC BY-SA 4.0",
                        "license_url":"https://creativecommons.org/licenses/by-sa/4.0/"
                     },
                     {
                        "id":"0122dbe429a419f9f661f9caabd39837",
                        "similarity":0.47305731789173033,
                        "url":"https://plant...bd39837.jpg",
                        "url_small":"https://plant...bd39837.small.jpg",
                        "citation":"Spencer",
                        "license_name":"CC BY 4.0",
                        "license_url":"https://creativecommons.org/licenses/by/4.0/"
                     }
                  ]
               },
               {
                  "id":148452113,
                  "plant_name":"Taraxacum",
                  "plant_details":{
                     "common_names":[
                        "dandelions"
                     ],
                     "edible_parts":null,
                     "propagation_methods":null,
                     "synonyms":[
                        "Caramanica"
                     ],
                     "taxonomy":{
                        "class":"Magnoliopsida",
                        "family":"Asteraceae",
                        "genus":"Taraxacum",
                        "kingdom":"Plantae",
                        "order":"Asterales",
                        "phylum":"Magnoliophyta"
                     },
                     "url":"https://en.wikipedia.org/wiki/Taraxacum",
                     "wiki_description":{
                        "value":"Taraxacum () is a large genus of flowering plants in the family Asteraceae, which consists of...",
                        "citation":"https://en.wikipedia.org/wiki/Taraxacum",
                        "license_name":"CC BY-SA 3.0",
                        "license_url":"https://creativecommons.org/licenses/by-sa/3.0/"
                     },
                     "wiki_image":{
                        "value":"https://plant...b.jpg",
                        "citation":"Markus Bernet",
                        "license_name":"CC BY-SA 2.0",
                        "license_url":"https://creativecommons.org/licenses/by-sa/2.0/"
                     },
                     "scientific_name":"Taraxacum",
                     "structured_name":{
                        "genus":"taraxacum"
                     }
                  },
                  "probability":0.02598454748305346,
                  "confirmed":false,
                  "similar_images":[
                     {
                        "id":"af773b4e5b50f1a8285ade5fb34d3468",
                        "similarity":0.7365460851577837,
                        "url":"https://plant...34d3468.jpg",
                        "url_small":"https://plant...34d3468.small.jpg"
                     },
                     {
                        "id":"66f444eca0c1c53e788fefbe965c371e",
                        "similarity":0.6438476565933042,
                        "url":"https://plant...65c371e.jpg",
                        "url_small":"https://plant...65c371e.small.jpg"
                     }
                  ]
               }
            ],
            "modifiers":[
               "crops_medium",
               "similar_images"
            ],
            "secret":"...",
            "fail_cause":null,
            "countable":true,
            "feedback":null,
            "is_plant_probability":0.9976788443333332,
            "is_plant":true
         }';

        $responseBody = json_decode($responseBody);
        //  dd($responseBody->suggestions[0]->plant_name);
        return (object)([
            'plant_name' => $responseBody->suggestions[0]->plant_name,
            'common_name' => $responseBody->suggestions[0]->plant_details->common_names[0],
            'taxonomy_class' => $responseBody->suggestions[0]->plant_details->taxonomy->class,
            'taxonomy_family' =>  $responseBody->suggestions[0]->plant_details->taxonomy->family,
            'taxonomy_genus' =>  $responseBody->suggestions[0]->plant_details->taxonomy->genus,
            'taxonomy_kingdom' =>  $responseBody->suggestions[0]->plant_details->taxonomy->kingdom,
            'taxonomy_order' =>  $responseBody->suggestions[0]->plant_details->taxonomy->order,
            'taxonomy_phylum' =>  $responseBody->suggestions[0]->plant_details->taxonomy->phylum,
            'wikipedia_url' => $responseBody->suggestions[0]->plant_details->url,
            'wikipedia_description' => $responseBody->suggestions[0]->plant_details->wiki_description->value,
        ]);
    }
}
