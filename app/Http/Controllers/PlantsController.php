<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use App\Http\Requests\StorePlantRequest;
use Illuminate\Support\Facades\URL;
use App\Models\Plant;
use App\Models\History;
use App\Models\Needs;
use Cloudinary;
use Illuminate\Support\Facades\Http;

class PlantsController extends Controller
{
    protected $dates = ['created_at'];

    public function getDateForHumans($date): string
    {
        return Carbon::parse($date)->diffForHumans();
    }

    public function getNextCareDate($date, $interval): Carbon
    {
        return Carbon::parse($date)->addDays($interval);
    }

    public function getRandomPlant()
    {
        $randomPlant = Plant::where('user_id', auth()->id())
            ->inRandomOrder()
            ->get()
            ->first();

        if ($randomPlant) {
            $randomPlant->watered_at = self::getDateForHumans($randomPlant->watered_at);
            $randomPlant->fertilized_at = self::getDateForHumans($randomPlant->fertilized_at);
            $nextWatering = self::getNextCareDate($randomPlant->watered_at, $randomPlant->watering_frequency)->diffForHumans();
            $nextFertilizing = self::getNextCareDate($randomPlant->fertilized_at, $randomPlant->fertilizing_frequency)->diffForHumans();
            $trefleData = self::getTrefleData($randomPlant->species);
            return view('welcome')->with([
                'plant' => $randomPlant,
                'nextWatering' => $nextWatering,
                'nextFertilizing' => $nextFertilizing,
                'trefleData' => $trefleData,
            ]);

        } else {
            return view('welcome');
        }


    }

    public function displaySinglePlant($id)
    {
        $plant = Plant::with('history','needs')
        ->get()
        ->where('id', $id)
        ->first();
        // dd($plant);
        if ($plant->user_id == auth()->id()) {
            $nextWatering = self::getNextCareDate($plant->history->watered_at, $plant->needs->watering_frequency);
            $lateForWatering = Carbon::parse($nextWatering)
                ->diffInDays(Carbon::now(),false);
            $nextWatering = $nextWatering->format('l, j-m-Y ');

            $nextFertilizing = self::getNextCareDate($plant->history->fertilized_at, $plant->needs->fertilizing_frequency);

            $lateForFertilizing = Carbon::parse($nextFertilizing)
                ->diffInDays(Carbon::now(),false);
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
        if ($request->hasFile('avatar')) {
            $uploadedFileUrl = ($request->file('avatar')->storeOnCloudinary('user_uploads'))->getSecurePath();
        } else {
            // $uploadedFileUrl = $this->getTrefleData($request->input('species'));
            if (!empty($uploadedFileUrl)) {
                $uploadedFileUrl = $uploadedFileUrl['image_url'];
            } else {
                $uploadedFileUrl = asset("images/plant.png");
            }

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
        $plants = Plant::with('history','needs')
        ->get()
        ->where('user_id', auth()->id())
        ->sortByDesc('history.watered_at');
        foreach ($plants as $plant) {
            if(isset($plant->history->watered_at)){
                $plant->watered_at = self::getDateForHumans($plant->history->watered_at);
            }
            if(isset($plant->history->fertilized_at)){
                $plant->fertilized_at = self::getDateForHumans($plant->history->fertilized_at);
            }
            if (isset($plant->needs->need_watering)){
                $plant->need_watering = $plant->needs->need_watering;
            }
            if (isset($plant->needs->need_fertilizing)){
                $plant->need_fertilizing = $plant->needs->need_fertilizing;
            }
        }
        return view('browse')->with('plants',$plants);
    }

    

    public function displayEditPlant($id)
    {
        $plant = Plant::find($id);
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
        Needs::where('plant_id',$id)->delete();
        History::where('plant_id',$id)->delete();
        
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
}
