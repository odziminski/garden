<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class History extends Model
{
    use HasFactory;

    protected $table = "history";
    protected $dates = ['watered_at', 'fertilized_at'];

    public $primaryKey  = 'id';
    public $timestamps = false;

    protected $fillable = [
        'plant_id',
        'watered_at',
        'fertilized_at'
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }
    
}
