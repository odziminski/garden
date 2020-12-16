<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use App\Http\Requests\StorePlantRequest;

class PlantsController extends Controller
{
    protected $dates = ['created_at', 'watered_at', 'fertilized_at'];

    public function displaySinglePlant()
    {
        $plants = DB::table('plants')
        ->where('id', '=', auth()->id())
        ->first();
        return view('plants')->with('plants',$plants);
    }


    public function store(StorePlantRequest $request)
    {
       $wateringFrequency = $request->input('watering_frequency');
       $fertilizingFrequency = $request->input('fertilizing_frequency');
    
        try 
        { 
            $query = DB::table('plants')->insertGetId([
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
        $now = new Carbon();

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
    

}