<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use App\Http\Requests\StorePlantRequest;
use Illuminate\Support\Facades\URL;
use App\Models\Needs;
use Cloudinary;
use Illuminate\Support\Facades\Http;
use App\Model;

class NeedsController extends Controller
{
    public function test(){
        return Needs::all();

    } 

    public function updateWatering($id)
    {
        $now = Carbon::now();
        try {
            Needs::where('plant_id', $id)
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
            Needs::where('plant_id', $id)
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
    
}
