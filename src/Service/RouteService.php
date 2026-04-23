<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class RouteService
{
    private HttpClientInterface $client;
    private string $apiKey;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;

        // ⚠️ clé ORS (attention quota)
        $this->apiKey = 'eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6IjI2MmY0NGJhZGEyMzQzMTBhNTJhMWJjZmIzNTVlNDg3IiwiaCI6Im11cm11cjY0In0=';
    }

    // =====================================================
    // 🔵 GEOCODING (ville -> lat/lng)
    // =====================================================
    public function geocodeCity(string $city): ?array
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://api.openrouteservice.org/geocode/search',
                [
                    'headers' => [
                        'Authorization' => $this->apiKey
                    ],
                    'query' => [
                        'text' => trim($city) . ', Tunisia',
                        'size' => 1
                    ]
                ]
            );

            $data = $response->toArray(false);

            if (!isset($data['features'][0]['geometry']['coordinates'])) {
                return null;
            }

            // ORS retourne [lng, lat]
            return $data['features'][0]['geometry']['coordinates'];

        } catch (\Throwable $e) {
            return null;
        }
    }

    // =====================================================
    // 🔵 ROUTE (2 points -> GeoJSON)
    // =====================================================
    public function getRoute(
        float $startLat,
        float $startLng,
        float $endLat,
        float $endLng
    ): array {
        try {
            $response = $this->client->request(
                'POST',
                'https://api.openrouteservice.org/v2/directions/driving-car/geojson',
                [
                    'headers' => [
                        'Authorization' => $this->apiKey,
                        'Content-Type' => 'application/json'
                    ],
                    'json' => [
                        'coordinates' => [
                            [$startLng, $startLat],
                            [$endLng, $endLat]
                        ]
                    ]
                ]
            );

            return $response->toArray(false);

        } catch (\Throwable $e) {
            return [];
        }
    }

    // =====================================================
    // EXTRA: validation route
    
    public function hasValidRoute(array $data): bool
    {
        return isset($data['features'][0]['properties']['segments'][0]);
    }
}