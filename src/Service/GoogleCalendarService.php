<?php

namespace App\Service;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
class GoogleCalendarService
{
    private string $credentialsPath;

    public function __construct()
    {
        $this->credentialsPath = __DIR__ . '/../../config/google/credentials.json';
    }

    // ================= AUTH =================
    public function getClient(): Client
    {
        $client = new Client();

        $client->setAuthConfig($this->credentialsPath);
        $client->setRedirectUri('http://localhost:8000/seance/oauth/callback');

        $client->addScope(Calendar::CALENDAR);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }

    // ================= SERVICE =================
    public function getService(array $token): Calendar
    {
        $client = $this->getClient();
        $client->setAccessToken($token);

        // 🔁 refresh token auto
        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken(
                $client->getRefreshToken()
            );
        }

        return new Calendar($client);
    }

    // ================= ADD EVENT =================
    public function addEvent(
        array $token,
        string $summary,
        string $description,
        \DateTime $start,
        \DateTime $end
    ): array {

        if ($start >= $end) {
            throw new \Exception("Start must be before End");
        }

        $service = $this->getService($token);

        $event = new Event([
            'summary' => $summary,
            'description' => $description,
            'start' => [
                'dateTime' => $start->setTimezone(new \DateTimeZone('Africa/Tunis'))->format(\DateTime::RFC3339),
'timeZone' => 'Africa/Tunis',
            ],
            'end' => [
'dateTime' => $end->setTimezone(new \DateTimeZone('Africa/Tunis'))->format(\DateTime::RFC3339),
'timeZone' => 'Africa/Tunis',
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'popup', 'minutes' => 30],
                ],
            ],
        ]);

        $created = $service->events->insert('primary', $event);

        return [
            'id' => $created->getId(),
            'htmlLink' => $created->getHtmlLink()
        ];
    }

    // ================= DELETE =================
    public function deleteEvent(array $token, string $eventId): void
    {
        $service = $this->getService($token);

        try {
            $service->events->delete('primary', $eventId);
        } catch (\Exception $e) {
            // ignore si déjà supprimé
        }
    }

    // ================= UPDATE =================
    public function updateEvent(
        array $token,
        string $eventId,
        string $summary,
        string $description,
        \DateTime $start,
        \DateTime $end
    ): void {

        if ($start >= $end) {
            throw new \Exception("Start must be before End");
        }

        $service = $this->getService($token);

        $event = $service->events->get('primary', $eventId);

        $event->setSummary($summary);
        $event->setDescription($description);

$startEvent = new EventDateTime();
$startEvent->setDateTime($start->format(\DateTime::RFC3339));
$startEvent->setTimeZone('Africa/Tunis');

$endEvent = new EventDateTime();
$endEvent->setDateTime($end->format(\DateTime::RFC3339));
$endEvent->setTimeZone('Africa/Tunis');

$event->setStart($startEvent);
$event->setEnd($endEvent);

        $service->events->update('primary', $eventId, $event);
    }
}