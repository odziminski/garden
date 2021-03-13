<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use App\Http\Requests\StorePlantRequest;
use Illuminate\Support\Facades\URL;
use App\Models\Plant;
use Cloudinary;
use Illuminate\Support\Facades\Http;

class PlantsController extends Controller
{
    protected $dates = ['created_at', 'watered_at', 'fertilized_at'];

    public function getDateForHumans($date)
    {
        return $dateForHumans = Carbon::parse($date)->diffForHumans();
    }

    public function getNextCareDate($date,$interval)
    {
        return $nextCare = Carbon::parse($date)->addDays($interval);
    }

    public function getRandomPlant()
    {
        $randomPlant = Plant::where('user_id',auth()->id())
        ->inRandomOrder()
        ->get()
        ->first();

        $randomPlant->watered_at = self::getDateForHumans($randomPlant->watered_at); 
        $randomPlant->fertilized_at = self::getDateForHumans($randomPlant->fertilized_at); 
        
        $nextWatering = self::getNextCareDate($randomPlant->watered_at,$randomPlant->watering_frequency)->diffForHumans();
        $nextFertilizing = self::getNextCareDate($randomPlant->fertilized_at,$randomPlant->fertilizing_frequency)->diffForHumans();


        return view('welcome')->with([
            'plant' => $randomPlant,
            'nextWatering' => $nextWatering,
            'nextFertilizing' => $nextFertilizing,
        ]);

    }

    

    public function displaySinglePlant($id)
    {
        $plant = Plant::find($id);

        if ($plant->user_id == auth()->id()) {
            $nextWatering = self::getNextCareDate($plant->watered_at,$plant->watering_frequency);
            $lateForWatering = Carbon::parse(Carbon::now())
            ->diffInDays($nextWatering);
            $nextWatering = $nextWatering->format('l, j-m-Y ');
                
            $nextFertilizing = self::getNextCareDate($plant->fertilized_at,$plant->fertilizing_frequency);

            $lateForFertilizing = Carbon::parse(Carbon::now())
            ->diffInDays($nextFertilizing);
            $nextFertilizing = $nextFertilizing->format('l, j-m-Y ');

            $trefleData = $this->getTrefleData($plant->species);
            return view('plants')->with([
            'plant' => $plant,
            'nextWatering' => $nextWatering,
            'nextFertilizing' => $nextFertilizing,
            'lateForWatering' => $lateForWatering,
            'lateForFertilizing' => $lateForFertilizing,
            'trefleData' => $trefleData,
            ]);
        } else {
            return back();
        }
    }

    public function store(StorePlantRequest $request)
    {
        $wateringFrequency = $request->input('watering_frequency');
        $fertilizingFrequency = $request->input('fertilizing_frequency');
        
        if ($request->hasFile('avatar')) {
            $uploadedFileUrl = ($request->file('avatar')->storeOnCloudinary('user_uploads'))->getSecurePath();
        } else {
            $uploadedFileUrl = $this->getTrefleData($request->input('species'));
            if(!empty($uploadedFileUrl))
            {
                $uploadedFileUrl = $uploadedFileUrl['image_url'];
            } else {
                $uploadedFileUrl = asset("images/plant.png");
            }

        }
        try {
            $plant = Plant::create([
                'avatar' => $uploadedFileUrl,
                'user_id' => auth()->user()->id,
                'name' => $request->input('name'),
                'species' => $request->input('species'),
                'created_at' => Carbon::now(),
                'watered_at' => Carbon::now(),
                'watering_frequency' => $wateringFrequency,
                'fertilized_at' => Carbon::now(),
                'fertilizing_frequency' => $fertilizingFrequency
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
        $plants = Plant::where('user_id', auth()->id())
        ->orderBy('watered_at', 'asc')
        ->get();

        foreach ($plants as $plant) {
            $plant->watered_at = self::getDateForHumans($plant->watered_at);
            $plant->fertilized_at = self::getDateForHumans($plant->fertilized_at);
        }
        return view('browse')->with('plants', $plants);
    }
    
    public function updateWatering($id)
    {
        $now = Carbon::now();
        try {
            Plant::where('id', $id)
            ->update([
                'need_watering' => 0,
                'watered_at' => $now,
            ]);
        } catch (\Exception $e) {
            $err = $e->getPrevious()->getMessage();
            echo $err;
        }
        return redirect()->route('plants', $id);
    }

    public function updateFertilizing($id)
    {
        $now = Carbon::now();
        try {
            Plant::where('id', $id)
            ->update([
                'need_fertilizing' => 0,
                'fertilized_at' => $now,
            ]);
        } catch (\Exception $e) {
            $err = $e->getPrevious()->getMessage();
            echo $err;
        }
        return redirect()->route('plants', $id);
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
        $plant = Plant::find($id)->delete();
        return redirect()->route('browse');
    }

    public function getTrefleData($species)
    {
        $species = rawurlencode($species);
        $token = env('TREFLE_TOKEN');

        $response = Http::get('https://trefle.io/api/v1/species/search?token='.$token.'&q='.$species)->json();
        if ($response['data']) {
            return $data = $response['data'][0];
        } else {
            return $data = [];
        }
    }
}
