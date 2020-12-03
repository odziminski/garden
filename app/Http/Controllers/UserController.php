<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use DB;

use Illuminate\Http\Request;
use App\Http\PlantsController;

class UserController extends Controller
{
    public function displayUserData(){
        $user = DB::table('users')->where('id', '=', auth()->id())->first();
        return view('users')->with('user', $user);
    }

    public function displayPlants(){
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
