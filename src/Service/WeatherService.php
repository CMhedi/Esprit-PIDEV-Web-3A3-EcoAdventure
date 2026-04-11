<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeatherService
{
    private $httpClient;
    private $apiKey = 'b6907d289e10d714a6e88b30761fae22'; // Clé de démonstration OpenWeatherMap

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getWeather(string $city): ?array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                'https://api.openweathermap.org/data/2.5/weather',
                [
                    'query' => [
                        'q' => $city,
                        'appid' => $this->apiKey,
                        'units' => 'metric',
                        'lang' => 'fr'
                    ]
                ]
            );

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray();
            return [
                'temp' => round($data['main']['temp']),
                'description' => ucfirst($data['weather'][0]['description']),
                'icon' => $data['weather'][0]['icon'],
                'humidity' => $data['main']['humidity']
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}
