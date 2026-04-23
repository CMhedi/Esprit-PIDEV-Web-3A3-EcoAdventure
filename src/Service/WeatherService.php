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
            // Utilisation d'une API Libre & Sans Clé pour garantir 100% de vraies données (wttr.in)
            // L'ancienne clé OpenWeatherMap de test était révoquée
            $url = 'https://wttr.in/' . urlencode($city) . '?format=j1';
            
            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['Accept-Language' => 'fr-FR,fr;q=0.9']
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray();
            
            if (!isset($data['current_condition'][0])) {
                return null;
            }
            
            $current = $data['current_condition'][0];
            $t = (int) $current['temp_C'];
            $code = (int) $current['weatherCode'];
            
            // Map WMO Codes to OpenWeatherMap format for the UI & AI
            $icon = '01d'; // default clear
            if (in_array($code, [113])) $icon = '01d';
            elseif (in_array($code, [116])) $icon = '02d';
            elseif (in_array($code, [119, 122])) $icon = '03d';
            elseif (in_array($code, [143, 248, 260])) $icon = '50d'; // Fog
            elseif (in_array($code, [263, 266, 293, 296, 299, 302, 305, 308, 311, 353, 356, 359])) $icon = '10d'; // Rain
            elseif (in_array($code, [386, 389, 392, 395, 200])) $icon = '11d'; // Thunderstorm
            elseif (in_array($code, [227, 230, 320, 323, 326, 329, 332, 335, 338, 368, 371])) $icon = '13d'; // Snow
            
            $desc = $current['lang_fr'][0]['value'] ?? ($current['weatherDesc'][0]['value'] ?? 'Inconnu');

            return [
                'temp' => $t,
                'description' => ucfirst($desc),
                'icon' => $icon,
                'humidity' => (int) $current['humidity']
            ];
            
        } catch (\Exception $e) {
            return null; // Pas de fausse donnée, si ça plante on cache le widget
        }
    }
}
