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
        ->where('user_id', '=', auth()->id())->get();
        foreach ($plants as $plant) {
            $plant->watered_at = Carbon::parse($plant->watered_at)
            ->diffForHumans(Carbon::now(). ' ago');
        }
        return view('welcome')->with('plants',$plants);

    }
    


}
