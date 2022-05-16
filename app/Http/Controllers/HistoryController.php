<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use App\Http\Requests\StorePlantRequest;
use Illuminate\Support\Facades\URL;
use App\Models\History;
use Cloudinary;
use Illuminate\Support\Facades\Http;
use App\Model;

class HistoryController extends Controller
{
    public function test(){
        return History::all();

    } 
}
