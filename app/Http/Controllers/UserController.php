<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use DB;

use Illuminate\Http\Request;
use App\Http\PlantsController;

class UserController extends Controller
{
    public function displayUserData()
    {
        $user = DB::table('users')->where('id', '=', auth()->id())->first();
        return view('users')->with('user', $user);
    }

    public function editUserProfile()
    {

    }
    
    
  


}
