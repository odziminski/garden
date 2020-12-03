<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use App\Http\Requests\StorePlantRequest;

class PlantsController extends Controller
{
    protected $dates = ['created_at', 'watered_at', 'fertilized_at'];

    public function displaySinglePlant($id){
        $plants = DB::table('plants')
        ->where('id', '=', $id)
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

    public function checkForWatering()
    {
        $all = DB::table('plants')
        ->select('*')
        ->get();  

        foreach ($all as $result)
        {
            $now = Carbon::now();
            $nextWatering = (new Carbon ($result->watered_at))
            ->addDays($result->watering_frequency);            
            $daysPast = $nextWatering->diffInDays($now);

            $query = DB::table('plants')
            ->where('watering_frequency', '>=', $daysPast)
            ->get();
            
            if ($query->isEmpty())
            {
               return true;
            }
            else
            {
                return false;
            }
        }
    }
}
