<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Weather extends Model
{
    use HasFactory;
    protected $fillable = [
        'city_id',
        'user_id',
        'temperature',
        'weather_description',
        'humidity',
        'wind_speed',
    ];
    protected $casts = [
        'temperature' => 'float',
        'humidity' => 'integer',
        'wind_speed' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    public function city(){
        return $this->belongsTo(City::class);
    }
    public function user(){
        return $this->belongsTo(User::class);
    }

}
