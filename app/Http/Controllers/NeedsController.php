<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Needs;
use App\Models\History;
use App\Models\Plant;

class NeedsController extends Controller
{
    public function test(){
        return Needs::all();

    } 

    public function updateWatering($id)
    {
        
            $plant = Plant::find($id);

            $needs = Needs::where('plant_id', $id)->first();
                $needs->update([
                    'need_watering' => 0,
                ]);

            $history = History::where('plant_id', $id)->first();
                $history->update([
                    'watered_at' => Carbon::now(),
                ]);

                $plant->watered_at = $history->watered_at;
                $plant->watering_frequency = $needs->watering_frequency;
       
        return Plant::getNextCareDate($plant->watered_at, $plant->watering_frequency)->format('l, j-m-Y ');
        print Plant::getNextCareDate($plant->watered_at, $plant->watering_frequency)->format('l, j-m-Y ');
        
    }

    public function updateFertilizing($id)
    {
        $plant = Plant::find($id);

        $needs = Needs::where('plant_id', $id)->first();
            $needs->update([
                'need_fertilizing' => 0,
            ]);

        $history = History::where('plant_id', $id)->first();
            $history->update([
                'fertilized_at' => Carbon::now(),
            ]);

            $plant->fertilized_at = $history->fertilized_at;
            $plant->fertilizing_frequency = $needs->fertilizing_frequency;
   
    return Plant::getNextCareDate($plant->fertilized_at, $plant->fertilizing_frequency)->format('l, j-m-Y ');
    
    }
    
}
