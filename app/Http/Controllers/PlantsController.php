<?php

namespace App\Http\Controllers;


use DB;
use Illuminate\Http\Request;

class PlantsController extends Controller
{
    protected $dates = ['created_at', 'watered_at', 'fertilized_at'];

    public function displaySinglePlant($id){
        $plants = DB::table('plants')->where('id', '=', $id)->first();
        
        return view('plants')->with('plants',$plants);


    }
    
}
