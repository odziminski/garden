<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use App\Http\Requests\StorePlantRequest;
use Illuminate\Support\Facades\URL;
use Cloudinary;

class PlantsController extends Controller
{
    protected $dates = ['created_at', 'watered_at', 'fertilized_at'];

    public function displaySinglePlant($id)
    {
         $plant = DB::table('plants')
                ->where('id', '=', $id)
                ->first();
        if ($plant->user_id == auth()->id())
        {
            $nextWatering = Carbon::parse($plant->watered_at)
            ->addDays($plant->watering_frequency)
            ->format('l, j-m-Y ');
            $nextFertilizing = Carbon::parse($plant->fertilized_at)
            ->addDays($plant->fertilizing_frequency)
            ->format('l, j-m-Y ');
        
        return view('plants')->with([
            'plant' => $plant,
            'nextWatering' => $nextWatering,
            'nextFertilizing' => $nextFertilizing,
            ]);
        } else
        {
            return back();
        }
    }

    public function store(StorePlantRequest $request)
    {
       $wateringFrequency = $request->input('watering_frequency');
       $fertilizingFrequency = $request->input('fertilizing_frequency');
        
       if ($request->hasFile('avatar'))
       {
           $filename = Carbon::now()->format('Y-m-d_h-i-s') . '.' . $request->file('avatar')->getClientOriginalExtension();
           $uploadedFileUrl = Cloudinary::upload($request->file('avatar')->getRealPath())->getSecurePath();
       }  else $filename = 'plant.png';

        try 
        { 
            $query = DB::table('plants')->insertGetId([
                'avatar' => $uploadedFileUrl,
                'user_id' => auth()->user()->id,
                'name' => $request->input('name'),
                'created_at' => Carbon::now(),
                'watered_at' => Carbon::now(),
                'watering_frequency' => $wateringFrequency,
                'fertilized_at' => Carbon::now(),
                'fertilizing_frequency' => $fertilizingFrequency
            ]);
        }
        catch ( \Exception $e )
        {
            $err = $e->getPrevious()->getMessage();
            echo ($err);
        }
        $query ? $message = "Plant added successfully" : $message = "Error while adding new plant";

        if ($query) 
         return redirect()->route('browse')->with('message',$message);
        else 
         return redirect()->route('add-plant')->with('message',$message);  
    }

    public function displayPlants()
    {
        $plants = DB::table('plants')
        ->where('user_id', '=', auth()->id())
        ->orderBy('watered_at', 'desc')
        ->get();

        foreach ($plants as $plant)
        {
            $plant->watered_at = Carbon::parse($plant->watered_at)
            ->diffForHumans();
            $plant->fertilized_at = Carbon::parse($plant->fertilized_at)
            ->diffForHumans();
        }
         return view('browse')->with('plants',$plants);
       
    }
    
    public function updateWatering($id)
    {
        $now = Carbon::now();
        try 
        { 
            $update = DB::table('plants')
            ->whereId($id)
            ->update([
                'need_watering' => 0,
                'watered_at' => $now,
            ]);
        }
        catch ( \Exception $e )
        {
            $err = $e->getPrevious()->getMessage();
            echo ($err);
        }
        return back();
    }

    public function updateFertilizing($id)
    {
        $now = Carbon::now();
        try 
        { 
            $update = DB::table('plants')
            ->whereId($id)
            ->update([
                'need_fertilizing' => 0,
                'fertilized_at' => $now,
            ]);
        }
        catch ( \Exception $e )
        {
            $err = $e->getPrevious()->getMessage();
            echo ($err);
        }
        return back();
    }
}