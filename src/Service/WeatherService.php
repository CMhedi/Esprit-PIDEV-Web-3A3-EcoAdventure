<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeatherService
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function getWeather(string $city): ?array
    {
        try {
            // Use a free endpoint so the widget can keep working without an API key.
            $url = 'https://wttr.in/' . urlencode($city) . '?format=j1';

            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['Accept-Language' => 'fr-FR,fr;q=0.9'],
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray();
            if (!isset($data['current_condition'][0])) {
                return null;
            }

            $current = $data['current_condition'][0];
            $temperature = (int) $current['temp_C'];
            $weatherCode = (int) $current['weatherCode'];

            // Map WMO-like codes from wttr.in to the icon codes expected by the UI.
            $icon = '01d';
            if (in_array($weatherCode, [113], true)) {
                $icon = '01d';
            } elseif (in_array($weatherCode, [116], true)) {
                $icon = '02d';
            } elseif (in_array($weatherCode, [119, 122], true)) {
                $icon = '03d';
            } elseif (in_array($weatherCode, [143, 248, 260], true)) {
                $icon = '50d';
            } elseif (in_array($weatherCode, [263, 266, 293, 296, 299, 302, 305, 308, 311, 353, 356, 359], true)) {
                $icon = '10d';
            } elseif (in_array($weatherCode, [386, 389, 392, 395, 200], true)) {
                $icon = '11d';
            } elseif (in_array($weatherCode, [227, 230, 320, 323, 326, 329, 332, 335, 338, 368, 371], true)) {
                $icon = '13d';
            }

            $description = $current['lang_fr'][0]['value'] ?? ($current['weatherDesc'][0]['value'] ?? 'Inconnu');

            return [
                'temp' => $temperature,
                'description' => ucfirst($description),
                'icon' => $icon,
                'humidity' => (int) $current['humidity'],
            ];
        } catch (\Exception) {
            return null;
        }
    }
}
