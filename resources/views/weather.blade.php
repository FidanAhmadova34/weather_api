<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Weather App</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js -->
    <script src="//unpkg.com/alpinejs" defer></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body x-data="weatherApp()"
      :class="{
          'bg-gradient-to-r from-yellow-400 to-orange-500': weather && weather.weather[0].main === 'Clear',
          'bg-gradient-to-r from-gray-400 to-gray-700': weather && weather.weather[0].main === 'Clouds',
          'bg-gradient-to-r from-blue-400 to-indigo-600': weather && weather.weather[0].main === 'Rain',
          'bg-gradient-to-r from-white to-blue-300': weather && weather.weather[0].main === 'Snow',
          'bg-gradient-to-r from-purple-500 to-gray-800': weather && weather.weather[0].main === 'Thunderstorm',
          'bg-gradient-to-r from-blue-400 to-indigo-600': !weather
      }"
      class="flex items-center justify-center min-h-screen transition-colors duration-1000">

<!-- App container -->
<div class="weather-card text-white w-full max-w-md">
    <h1 class="text-3xl font-bold mb-6 text-center">Weather App</h1>

    <!-- Şəhər input və button -->
    <div class="flex mb-4 gap-2">
        <div class="relative flex-1">
            <input x-model="city" @input.debounce.300ms="searchCities" type="text" placeholder="Enter city"
                   class="w-full p-3 rounded-xl text-black focus:outline-none">
            <template x-if="suggestions.length">
                <div class="absolute z-10 mt-1 w-full bg-white text-black rounded-xl shadow">
                    <template x-for="item in suggestions" :key="item.lat + ',' + item.lon">
                        <button type="button" class="w-full text-left px-3 py-2 hover:bg-gray-100"
                                @click="selectSuggestion(item)">
                            <span x-text="item.name"></span>
                            <span class="text-gray-500" x-text="' - ' + (item.state || '') + (item.state ? ', ' : '') + (item.country || '')"></span>
                        </button>
                    </template>
                </div>
            </template>
        </div>
        <input x-model="country" type="text" placeholder="Country (code or name, e.g., TR or Türkiye)" maxlength="32"
               class="w-40 p-3 rounded-xl text-black focus:outline-none uppercase">
        <button @click="getWeather()" class="bg-blue-700 px-4 rounded-xl hover:bg-blue-800 transition">Check</button>
    </div>

    <!-- Nəticə kartı -->
    <template x-if="weather">
        <div class="weather-card text-black mt-6">
            <h2 class="text-2xl font-semibold mb-2" x-text="displayName || weather.name"></h2>
            <!-- Hava emoji -->
            <p class="text-5xl mb-2" x-text="getWeatherEmoji(weather.weather[0].main)"></p>
            <p class="text-lg mb-2" x-text="weather.main.temp + '°C - ' + weather.weather[0].description"></p>
            <img :src="'https://openweathermap.org/img/wn/' + weather.weather[0].icon + '.png'" 
                 alt="icon" class="weather-icon mx-auto mb-2">
            <p class="text-sm">Humidity: <span x-text="weather.main.humidity + '%'"></span></p>
            <p class="text-sm">Wind: <span x-text="weather.wind.speed + ' m/s'"></span></p>
        </div>
    </template>
</div>

<!-- Alpine.js funksiyası -->
<script>
function getWeatherEmoji(type) {
    switch(type) {
        case 'Clear': return '☀️';
        case 'Clouds': return '☁️';
        case 'Rain': return '🌧';
        case 'Snow': return '❄️';
        case 'Thunderstorm': return '⛈';
        default: return '🌤';
    }
}

function weatherApp() {
    return {
        city: 'Baku',
        country: '',
        suggestions: [],
        selectedLat: null,
        selectedLon: null,
        weather: null,
        displayName: '',
        getWeatherEmoji,
        async getWeather() {
            try {
                const params = new URLSearchParams({ city: this.city });
                if (this.country) params.set('country', this.country.trim().toUpperCase());
                if (this.selectedLat !== null && this.selectedLon !== null) {
                    params.set('lat', this.selectedLat);
                    params.set('lon', this.selectedLon);
                }
                // Set a friendly display name before fetching; fallback to API name later
                this.displayName = (this.city && this.city.trim().length >= 3)
                    ? this.city.trim()
                    : this.displayName;
                const res = await fetch(`/api/weather/current?${params.toString()}`);
                if (!res.ok) throw new Error("City not found");
                this.weather = await res.json();
                if (!this.displayName) { this.displayName = this.weather?.name || ''; }
            } catch (err) {
                alert(err.message);
            }
        },
        async searchCities() {
            this.selectedLat = null; this.selectedLon = null;
            if (!this.city || this.city.trim().length < 3) { this.suggestions = []; return; }
            try {
                const params = new URLSearchParams({ q: this.city });
                if (this.country) params.set('country', this.country.trim());
                const res = await fetch(`/api/weather/search?${params.toString()}`);
                if (!res.ok) { this.suggestions = []; return; }
                let list = await res.json();
                // If a country is specified, keep only matching country entries
                const c = this.country ? this.country.trim().toUpperCase() : '';
                if (c) list = list.filter(x => (x.country || '').toUpperCase() === c);
                // Basic noise reduction: require name length >= 3, startsWith and Latin-only when typed in Latin
                const q = this.city.trim();
                const isLatin = /^[A-Za-z .'-]+$/.test(q);
                const startsWith = (name) => (name || '').toLowerCase().startsWith(q.toLowerCase());
                this.suggestions = list
                    .filter(x => (x.name || '').length >= 3)
                    .filter(x => startsWith(x.name))
                    .filter(x => !isLatin || /^[A-Za-z .'-]+$/.test(x.name || ''))
                    .slice(0, 8);
            } catch (_) { this.suggestions = []; }
        },
        selectSuggestion(item) {
            this.city = item.name;
            this.country = item.country || this.country;
            this.selectedLat = item.lat; this.selectedLon = item.lon;
            this.displayName = item.name;
            this.suggestions = [];
        }
    }
}

document.addEventListener('alpine:init', () => {
    Alpine.data('weatherApp', weatherApp);
});
</script>
</body>
</html>