<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WeatherController extends Controller
{
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = env('OPENWEATHER_API_KEY'); // .env faylda saxlanır
    }

    private function normalizeCountry($country)
    {
        if (!$country) return null;

        $map = [
            'turkey' => 'TR',
            'türkiye' => 'TR',
            'azerbaijan' => 'AZ',
            'azərbaycan' => 'AZ',
            'united states' => 'US',
            'usa' => 'US',
            'uk' => 'GB',
            'united kingdom' => 'GB',
        ];

        $key = mb_strtolower(trim($country));
        if (isset($map[$key])) {
            return $map[$key];
        }

        // If already two-letter code, return uppercased
        if (preg_match('/^[a-zA-Z]{2}$/', $country)) {
            return strtoupper($country);
        }

        return $country; // fallback
    }

    private function geocodeCity(?string $city, ?string $country)
    {
        if (empty($this->apiKey)) {
            return null;
        }

        $params = [
            'q' => $country ? ($city . ',' . $country) : $city,
            'limit' => 1,
            'appid' => $this->apiKey,
        ];

        $res = Http::get('https://api.openweathermap.org/geo/1.0/direct', $params);
        if ($res->failed()) {
            return null;
        }
        $arr = $res->json();
        if (!is_array($arr) || count($arr) === 0) {
            return null;
        }
        $item = $arr[0];
        return [
            'lat' => $item['lat'] ?? null,
            'lon' => $item['lon'] ?? null,
            'name' => $item['name'] ?? null,
            'country' => $item['country'] ?? null,
            'state' => $item['state'] ?? null,
        ];
    }

    private function resolveCityForCountry(?string $city, ?string $country): ?string
    {
        if (!$country) {
            return $city;
        }

        $normalizedCountryName = mb_strtolower(trim((string) $country));
        $countryCode = $this->normalizeCountry($country);

        // Known capitals fallback
        $capitals = [
            'AZ' => 'Baku',
            'TR' => 'Ankara',
            'US' => 'Washington',
            'GB' => 'London',
            'DE' => 'Berlin',
            'FR' => 'Paris',
            'IT' => 'Rome',
            'ES' => 'Madrid',
            'RU' => 'Moscow',
            'CN' => 'Beijing',
            'JP' => 'Tokyo',
            'IN' => 'New Delhi',
        ];

        // If city matches country name/code or is empty, use the capital
        if (!$city) {
            return $capitals[$countryCode] ?? $city;
        }

        $normalizedCity = mb_strtolower(trim($city));
        if ($normalizedCity === $normalizedCountryName || strtoupper($normalizedCity) === strtoupper($countryCode)) {
            return $capitals[$countryCode] ?? $city;
        }

        return $city;
    }

    // ✅ Current weather
    public function current(Request $request)
    {
        $request->validate([
            'id' => 'nullable|integer',
            'lat' => 'nullable|numeric|between:-90,90',
            'lon' => 'nullable|numeric|between:-180,180',
            'city' => 'required_without_all:id,lat,lon|string|max:255',
            'country' => 'nullable|string|max:255'
        ]);

        if (empty($this->apiKey)) {
            return response()->json([
                'error' => 'Server configuration error: OPENWEATHER_API_KEY is not set.'
            ], 500);
        }

        $city = $this->resolveCityForCountry($request->city, $request->country);
        $country = $this->normalizeCountry($request->country);

        $queryParams = [
            'appid' => $this->apiKey,
            'units' => 'metric'
        ];

        if ($request->filled('id')) {
            $queryParams['id'] = (int) $request->id;
        } elseif ($request->filled('lat') && $request->filled('lon')) {
            $queryParams['lat'] = (float) $request->lat;
            $queryParams['lon'] = (float) $request->lon;
        } else {
            // Prefer geocoding for precise matching
            $geo = $this->geocodeCity($city, $country);
            if ($geo && isset($geo['lat'], $geo['lon'])) {
                $queryParams['lat'] = $geo['lat'];
                $queryParams['lon'] = $geo['lon'];
            } else {
                $queryParams['q'] = $country ? "$city,$country" : $city;
            }
        }

        $correlationId = $this->getCorrelationId($request);
        $start = microtime(true);

        $cacheKey = 'weather_current:' . md5(json_encode($queryParams));
        $response = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($queryParams, $correlationId) {
            return Http::retry(2, 250)
                ->timeout(5)
                ->withHeaders([
                    'X-Correlation-ID' => $correlationId,
                ])
                ->get('https://api.openweathermap.org/data/2.5/weather', $queryParams);
        });

        $this->logUpstream('current', $queryParams, $start, $response->status(), $correlationId);

        if ($response->failed()) {
            $status = $response->status();
            $payload = $response->json();
            return response()->json($payload ?: ['error' => 'Unable to fetch weather data'], $status ?: 502);
        }

        // Return the OpenWeather payload as-is so the frontend can use expected fields
        return response()->json($response->json(), 200)->header('X-Correlation-ID', $correlationId);
    }

    // ✅ Forecast (default 3 gün)
    public function forecast(Request $request)
    {
        $request->validate([
            'id' => 'nullable|integer',
            'lat' => 'nullable|numeric|between:-90,90',
            'lon' => 'nullable|numeric|between:-180,180',
            'city' => 'required_without_all:id,lat,lon|string|max:255',
            'country' => 'nullable|string|max:255',
            'days' => 'nullable|integer|min:1|max:5'
        ]);

        if (empty($this->apiKey)) {
            return response()->json([
                'error' => 'Server configuration error: OPENWEATHER_API_KEY is not set.'
            ], 500);
        }

        $city = $this->resolveCityForCountry($request->city, $request->country);
        $country = $this->normalizeCountry($request->country);
        $days = $request->days ?? 3;

        $params = [
            'appid' => $this->apiKey,
            'units' => 'metric'
        ];

        if ($request->filled('id')) {
            $params['id'] = (int) $request->id;
        } elseif ($request->filled('lat') && $request->filled('lon')) {
            $params['lat'] = (float) $request->lat;
            $params['lon'] = (float) $request->lon;
        } else {
            $geo = $this->geocodeCity($city, $country);
            if ($geo && isset($geo['lat'], $geo['lon'])) {
                $params['lat'] = $geo['lat'];
                $params['lon'] = $geo['lon'];
            } else {
                $params['q'] = $country ? "$city,$country" : $city;
            }
        }

        $correlationId = $this->getCorrelationId($request);
        $start = microtime(true);
        $cacheKey = 'weather_forecast:' . md5(json_encode(['p' => $params, 'd' => $days]));
        $response = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($params, $correlationId) {
            return Http::retry(2, 250)
                ->timeout(5)
                ->withHeaders([
                    'X-Correlation-ID' => $correlationId,
                ])
                ->get('https://api.openweathermap.org/data/2.5/forecast', $params);
        });

        $this->logUpstream('forecast', $params, $start, $response->status(), $correlationId);

        if ($response->failed()) {
            $status = $response->status();
            $payload = $response->json();
            return response()->json($payload ?: ['error' => 'Unable to fetch forecast data'], $status ?: 502);
        }

        // Limit to the first $days unique dates while keeping OpenWeather structure
        $data = $response->json();
        $seenDates = [];
        $filteredList = [];
        foreach ($data['list'] as $item) {
            $date = isset($item['dt_txt']) ? explode(' ', $item['dt_txt'])[0] : null;
            if ($date !== null && !in_array($date, $seenDates, true)) {
                $seenDates[] = $date;
                $filteredList[] = $item;
                if (count($seenDates) >= $days) {
                    break;
                }
            }
        }

        $data['list'] = $filteredList;
        return response()->json($data, 200)->header('X-Correlation-ID', $correlationId);
    }

    // ✅ City search
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:3|max:255',
            'country' => 'nullable|string|max:255'
        ]);

        if (empty($this->apiKey)) {
            return response()->json([
                'error' => 'Server configuration error: OPENWEATHER_API_KEY is not set.'
            ], 500);
        }

        $country = $this->normalizeCountry($request->country);
        $directParams = [
            'q' => $country ? ($request->q . ',' . $country) : $request->q,
            'limit' => 5,
            'appid' => $this->apiKey,
        ];

        // Use OpenWeather Geocoding for search suggestions
        $correlationId = $this->getCorrelationId($request);
        $start = microtime(true);
        $cacheKey = 'weather_search:' . md5(json_encode($directParams));
        $response = Cache::remember($cacheKey, now()->addHours(6), function () use ($directParams, $correlationId) {
            return Http::retry(2, 250)
                ->timeout(5)
                ->withHeaders([
                    'X-Correlation-ID' => $correlationId,
                ])
                ->get('https://api.openweathermap.org/geo/1.0/direct', $directParams);
        });

        $this->logUpstream('search', $directParams, $start, $response->status(), $correlationId);

        if ($response->failed()) {
            $status = $response->status();
            $payload = $response->json();
            return response()->json($payload ?: ['error' => 'No cities found'], $status ?: 404);
        }

        $results = $response->json();
        // Map a concise response with ids (if available), names, country codes, and lat/lon
        $mapped = array_map(function ($item) {
            return [
                'name' => $item['name'] ?? null,
                'lat' => $item['lat'] ?? null,
                'lon' => $item['lon'] ?? null,
                'country' => $item['country'] ?? null,
                'state' => $item['state'] ?? null,
            ];
        }, is_array($results) ? $results : []);

        return response()->json($mapped, 200)->header('X-Correlation-ID', $correlationId);

    }

    private function getCorrelationId(Request $request): string
    {
        return $request->header('X-Correlation-ID') ?: (string) Str::uuid();
    }

    private function logUpstream(string $op, array $params, float $start, int $status, string $cid): void
    {
        $ms = (int) ((microtime(true) - $start) * 1000);
        Log::info('openweather_call', [
            'operation' => $op,
            'status' => $status,
            'duration_ms' => $ms,
            'cid' => $cid,
            'params' => $params,
        ]);
    }

    private function respondError(string $message, int $status, string $cid)
    {
        return response()->json([
            'error' => [
                'code' => $status,
                'message' => $message,
                'cid' => $cid,
            ]
        ], $status)->header('X-Correlation-ID', $cid);
    }

    private function toCurrentDto(array $p): array
    {
        return [
            'name' => $p['name'] ?? null,
            'country' => $p['sys']['country'] ?? null,
            'lat' => $p['coord']['lat'] ?? null,
            'lon' => $p['coord']['lon'] ?? null,
            'tempC' => $p['main']['temp'] ?? null,
            'humidity' => $p['main']['humidity'] ?? null,
            'windMs' => $p['wind']['speed'] ?? null,
            'condition' => $p['weather'][0]['description'] ?? null,
            'icon' => $p['weather'][0]['icon'] ?? null,
        ];
    }

    private function toForecastDto(array $p, int $days): array
    {
        $items = [];
        $seen = [];
        foreach (($p['list'] ?? []) as $it) {
            $date = isset($it['dt_txt']) ? explode(' ', $it['dt_txt'])[0] : null;
            if ($date && !in_array($date, $seen, true)) {
                $seen[] = $date;
                $items[] = [
                    'date' => $date,
                    'tempC' => $it['main']['temp'] ?? null,
                    'condition' => $it['weather'][0]['description'] ?? null,
                    'icon' => $it['weather'][0]['icon'] ?? null,
                ];
                if (count($seen) >= $days) break;
            }
        }
        return [
            'city' => [
                'name' => $p['city']['name'] ?? null,
                'country' => $p['city']['country'] ?? null,
                'lat' => $p['city']['coord']['lat'] ?? null,
                'lon' => $p['city']['coord']['lon'] ?? null,
            ],
            'days' => $items,
        ];
    }

    // v1 endpoints (DTO responses)
    public function currentV1(Request $request)
    {
        $res = $this->current($request);
        $cid = $res->headers->get('X-Correlation-ID') ?? (string) Str::uuid();
        if ($res->status() !== 200) {
            return $this->respondError('Unable to fetch weather data', $res->status(), $cid);
        }
        $payload = $res->getData(true);
        return response()->json($this->toCurrentDto($payload), 200)->header('X-Correlation-ID', $cid);
    }

    public function forecastV1(Request $request)
    {
        $res = $this->forecast($request);
        $cid = $res->headers->get('X-Correlation-ID') ?? (string) Str::uuid();
        if ($res->status() !== 200) {
            return $this->respondError('Unable to fetch forecast data', $res->status(), $cid);
        }
        $days = (int) ($request->days ?? 3);
        $payload = $res->getData(true);
        return response()->json($this->toForecastDto($payload, $days), 200)->header('X-Correlation-ID', $cid);
    }
    public function history()
{
    $this->authorize('view-history'); // double-check
    return response()->json(['data' => 'Historical weather data']);
}

}