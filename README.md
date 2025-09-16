# Weather API App

Production-ready Laravel weather API and UI powered by OpenWeather.

## Quick start

1) Install dependencies
```
composer install
```
2) Copy env and set key
```
cp .env.example .env
php artisan key:generate
```
3) Set your OpenWeather key in `.env`
```
OPENWEATHER_API_KEY=your_key_here
```
4) Serve
```
php artisan serve
```

Open `http://127.0.0.1:8000` for the UI and `http://127.0.0.1:8000/docs` for API docs.

## API Overview

- Health: `GET /api/health`
- Current: `GET /api/weather/current?city=&country=&id=&lat=&lon=`
- Forecast: `GET /api/weather/forecast?city=&country=&id=&lat=&lon=&days=3`
- Search: `GET /api/weather/search?q=&country=`

Notes:
- Rate limited to 60 req/min per IP
- Responses include `X-Correlation-ID`
- Outbound calls use retries (2x), 5s timeout, and short-term caching

## Docs

- Swagger UI: `/docs`
- OpenAPI: `/openapi.json`

## Tests
```
php vendor/bin/phpunit
```

CI runs on GitHub Actions (`.github/workflows/ci.yml`).
