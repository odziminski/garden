<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Plant extends Model
{
    
    use HasFactory, Notifiable;
    protected $table = "plants";

    public function users()
    {
        return $this->belongsTo(User::class);
    }
}
