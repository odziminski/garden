<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use DB;

use Illuminate\Http\Request;
use App\Http\PlantsController;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function displayUserData()
    {
        $user = DB::table('users')
        ->whereId(auth()->id())
        ->first();
        return view('users')->with('user', $user);
    }

    public function editUserProfile(Request $request)
    {
        try {
            DB::table('users')
            ->whereId(auth()->id())
            ->update([
                    'email' => $request->input('email'),
                    'password' => Hash::make($request->input('password')),
                ]);
        } catch (\Exception $e) {
            $err = $e->getPrevious()->getMessage();
            echo $err;
        }
        return redirect()->route('users');
    }
}
