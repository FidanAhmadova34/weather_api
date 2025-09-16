<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherApiTest extends TestCase
{
    public function test_current_uses_geocoding_and_returns_200(): void
    {
        Http::fake([
            'api.openweathermap.org/geo/1.0/direct*' => Http::response([
                ['name' => 'Baku', 'lat' => 40.4093, 'lon' => 49.8671, 'country' => 'AZ']
            ], 200),
            'api.openweathermap.org/data/2.5/weather*' => Http::response([
                'name' => 'Baku', 'weather' => [['main' => 'Clear', 'description' => 'clear sky', 'icon' => '01d']],
                'main' => ['temp' => 25, 'humidity' => 50],
                'wind' => ['speed' => 3.2]
            ], 200),
        ]);

        $res = $this->get('/api/weather/current?city=Baku&country=AZ');
        $res->assertStatus(200)->assertJsonFragment(['name' => 'Baku']);
    }

    public function test_search_min_length(): void
    {
        $res = $this->get('/api/weather/search?q=Ba');
        $res->assertStatus(422);
    }
}


