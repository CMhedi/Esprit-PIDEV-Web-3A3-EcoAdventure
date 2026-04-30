<?php

namespace App\Service\Context;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HolidayContextProvider
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly string $countryCode = 'TN',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(?\DateTimeImmutable $referenceDate = null): array
    {
        $referenceDate ??= new \DateTimeImmutable('today');
        $holidays = $this->loadHolidays((int) $referenceDate->format('Y'));

        $upcoming = array_values(array_filter($holidays, static function (array $holiday) use ($referenceDate): bool {
            return isset($holiday['date']) && $holiday['date'] >= $referenceDate->format('Y-m-d');
        }));

        if ($upcoming === []) {
            $upcoming = $this->loadHolidays((int) $referenceDate->format('Y') + 1);
        }

        $nextHoliday = $upcoming[0] ?? null;
        if (!$nextHoliday || !isset($nextHoliday['date'])) {
            return [
                'country' => $this->countryCode,
                'available' => false,
                'message' => 'Aucun contexte calendaire externe disponible.',
            ];
        }

        $holidayDate = new \DateTimeImmutable((string) $nextHoliday['date']);
        $daysUntil = (int) $referenceDate->diff($holidayDate)->format('%r%a');
        $window = $daysUntil <= 3 ? 'imminent' : ($daysUntil <= 10 ? 'near' : 'normal');

        return [
            'country' => $this->countryCode,
            'available' => true,
            'name' => $nextHoliday['localName'] ?? $nextHoliday['name'] ?? 'Jour férié',
            'date' => $holidayDate,
            'daysUntil' => $daysUntil,
            'window' => $window,
            'message' => $this->buildWindowMessage($daysUntil),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadHolidays(int $year): array
    {
        $cacheKey = sprintf('holiday_context_%s_%d', strtolower($this->countryCode), $year);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($year): array {
            $item->expiresAfter(86400);

            try {
                $response = $this->httpClient->request(
                    'GET',
                    sprintf('https://date.nager.at/api/v3/PublicHolidays/%d/%s', $year, strtoupper($this->countryCode))
                );

                if ($response->getStatusCode() !== 200) {
                    return [];
                }

                return $response->toArray(false);
            } catch (\Throwable) {
                return [];
            }
        });
    }

    private function buildWindowMessage(int $daysUntil): string
    {
        if ($daysUntil <= 3) {
            return 'Fenêtre très proche d’un jour férié: bon contexte pour valoriser les packs accessibles et famille.';
        }

        if ($daysUntil <= 10) {
            return 'Période favorable à une mise en avant commerciale des packs de découverte et des bons plans.';
        }

        return 'Contexte calendaire normal: la performance dépend surtout de la valeur métier du pack.';
    }
}
