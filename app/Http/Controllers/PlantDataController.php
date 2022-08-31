<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PlantData;

class PlantDataController extends Controller
{
    public function test(){
        dd(PlantData::all());
        
    }
}
