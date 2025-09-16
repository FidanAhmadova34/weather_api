<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class City extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'city_name',
        'country_code',
    ];
    
    public function weathers(){
        return $this->hasMany(Weather::class);
    }
}

