<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WeatherService
{
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        // Load API key and base URL from the .env file
        $this->apiKey = env('WEATHERBIT_API_KEY');
        $this->baseUrl = 'https://api.weatherbit.io/v2.0/';
    }

    /**
     * Get a weather forecast for a specific date.
     */
    public function getForecastForDate($latitude, $longitude, $eventDate)
    {
        // Call the Weatherbit API's 16-day daily forecast endpoint
        $response = Http::get($this->baseUrl . 'forecast/daily', [
            'lat'   => $latitude,
            'lon'   => $longitude,
            'key'   => $this->apiKey,
            'units' => 'M', // Metric (Celsius)
        ]);

        if ($response->successful()) {
            $forecastData = $response->json();

            // Find the forecast for the specific event date
            $filteredForecasts = array_filter($forecastData['data'], function ($forecast) use ($eventDate) {
                return $forecast['datetime'] === $eventDate;
            });

            if (!empty($filteredForecasts)) {
                return array_values($filteredForecasts)[0]; // Return the closest forecast
            }

            throw new \Exception('No forecast data available for the specified date.');
        }

        throw new \Exception('Failed to retrieve weather data from Weatherbit.');
    }
}
