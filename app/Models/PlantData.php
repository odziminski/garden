<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class PlantData extends Model
{
    use HasFactory;

    protected $table = "plant_data";
    
    public $primaryKey  = 'id';
    public $timestamps = false;

    protected $fillable = [
        
            'plant_id',
            'plant_name',
            'common_name',
            'wikipedia_url',
            'wikipedia_description',
            'taxonomy_class',
            'taxonomy_family',
            'taxonomy_genus',
            'taxonomy_kingdom',
            'taxonomy_order',
            'taxonomy_phylum'
        
    ];

    
}
