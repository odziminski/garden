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
        $plants = DB::table('plants')->where('id', '=', $id)->first();
        return view('plants')->with('plants',$plants);
    }

    public function store(StorePlantRequest $request)
    {
        try 
        { 
            $query = DB::table('plants')->insertGetId([
                'user_id' => auth()->user()->id,
                'name' => $request->input('name'),
                'created_at' => Carbon::now(),
                'watered_at' => Carbon::now(),
                'watering_frequency' => $request->input('watering_frequency'),
                'fertilized_at' => Carbon::now(),
                'fertilizing_frequency' => $request->input('fertilizing_frequency')
            ]);
        }
        catch ( \Exception $e ){
            $err = $e->getPrevious()->getMessage();
            echo ($err);
        }
        
        $query ? $message = "Plant added successfully" : $message = "Error while adding new plant";

        if ($query){
            return redirect()->route('welcome')->with('message',$message);
        }else{
            return redirect()->route('add-plant')->with('message',$message);
        }
    }
    
}
