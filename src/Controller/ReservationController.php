<?php

namespace App\Controller;

use App\Entity\Activite;
use App\Entity\ReservationActivite;
use App\Enum\StatutReservationActivite;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ReservationController extends AbstractController
{
    #[Route('/reservationfront/{id}', name: 'app_reservation_front')]
    public function reservationFront(int $id, EntityManagerInterface $em): Response
    {
        $activite = $em->getRepository(Activite::class)->find($id);

        if (!$activite) {
            throw $this->createNotFoundException('Activite non trouvee');
        }

        return $this->renderReservationForm($activite);
    }

    #[Route('/reservation/create/{id}', name: 'app_reservation_create', methods: ['POST'])]
    public function createReservation(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): Response {
        $activite = $em->getRepository(Activite::class)->find($id);

        if (!$activite) {
            throw $this->createNotFoundException('Activite non trouvee');
        }

        $user = $this->resolveCurrentUser($request, $em);

        if (!$user) {
            throw $this->createAccessDeniedException(
                'Aucun utilisateur connecte n a ete trouve pour cette reservation.'
            );
        }

        $formData = [
            'date_res' => trim((string) $request->request->get('date_res', '')),
            'statut_res' => trim((string) $request->request->get('statut_res', '')),
            'nb_personnes' => trim((string) $request->request->get('nb_personnes', '')),
        ];

        // coordinates from JS
        $latitude = $request->request->get('latitude');
        $longitude = $request->request->get('longitude');

        $reservation = new ReservationActivite();
        $fieldErrors = [];

        // date
        if ($formData['date_res'] === '') {
            $reservation->setDateRes(null);
        } else {
            $normalizedDate = str_replace('T', ' ', $formData['date_res']);
            $dateRes = \DateTime::createFromFormat('Y-m-d H:i', $normalizedDate);

            if ($dateRes === false) {
                $fieldErrors['date_res'][] =
                    'Le format de date doit etre AAAA-MM-JJ HH:MM.';
                $reservation->setDateRes(null);
            } else {
                $reservation->setDateRes($dateRes);
            }
        }

        // status
        $reservation->setStatutRes(
            StatutReservationActivite::tryFrom($formData['statut_res'])
        );

        // number of people
        if ($formData['nb_personnes'] === '') {
            $reservation->setNbPersonnes(null);
        } elseif (!ctype_digit($formData['nb_personnes'])) {
            $fieldErrors['nb_personnes'][] =
                'Le nombre de personnes doit etre un entier valide.';
            $reservation->setNbPersonnes(null);
        } else {
            $reservation->setNbPersonnes((int) $formData['nb_personnes']);
        }

        $reservation->setUserApp($user);
        $reservation->setActivite($activite);

        // validation
        $validationErrors = $this->mapViolations(
            $validator->validate($reservation)
        );

        foreach (array_keys($fieldErrors) as $fieldName) {
            unset($validationErrors[$fieldName]);
        }

        $fieldErrors = array_merge_recursive(
            $fieldErrors,
            $validationErrors
        );

        if ($fieldErrors !== []) {
            return $this->renderReservationForm(
                $activite,
                $fieldErrors,
                $formData
            );
        }

        // save reservation
        $em->persist($reservation);
        $em->flush();

        $this->addFlash('success', 'Reservation effectuee avec succes');

        // redirect to weather page
        return $this->redirectToRoute('app_reservation_weather', [
            'id' => $reservation->getIdResAct(),
            'lat' => $latitude,
            'lon' => $longitude,
        ]);
    }

    #[Route('/reservation/weather/{id}', name: 'app_reservation_weather')]
public function weatherPage(
    int $id,
    Request $request,
    EntityManagerInterface $em
): Response {
    $reservation = $em
        ->getRepository(ReservationActivite::class)
        ->find($id);

    if (!$reservation) {
        throw $this->createNotFoundException('Reservation non trouvee');
    }

    $lat = $request->query->get('lat');
    $lon = $request->query->get('lon');

    $reservationDate = $reservation->getDateRes();
    $date = $reservationDate->format('Y-m-d');

    // today + 16 days max forecast
    $today = new \DateTime();
    $maxForecastDate = (clone $today)->modify('+16 days');

    if ($reservationDate > $maxForecastDate) {
        return $this->render('front/weather_result.html.twig', [
            'reservation' => $reservation,
            'isRainy' => false,
            'weatherUnavailable' => true,
            'message' => 'Weather forecast is only available for the next 16 days.',
        ]);
    }

    $url = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&daily=weathercode&timezone=auto&start_date={$date}&end_date={$date}";

    $response = @file_get_contents($url);

    if ($response === false) {
        return $this->render('front/weather_result.html.twig', [
            'reservation' => $reservation,
            'isRainy' => false,
            'weatherUnavailable' => true,
            'message' => 'Unable to retrieve weather information at the moment.',
        ]);
    }

    $weatherData = json_decode($response, true);

    $weatherCode = $weatherData['daily']['weathercode'][0] ?? null;

    $rainCodes = [51, 53, 55, 61, 63, 65, 80, 81, 82];

    $isRainy = in_array($weatherCode, $rainCodes);

    return $this->render('front/weather_result.html.twig', [
        'reservation' => $reservation,
        'isRainy' => $isRainy,
        'weatherUnavailable' => false,
        'message' => null,
    ]);
}

    #[Route('/reservation/affichage/{id}', name: 'app_reservation_affichage')]
    public function reservationAffichage(
        int $id,
        EntityManagerInterface $em
    ): Response {
        $reservation = $em
            ->getRepository(ReservationActivite::class)
            ->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException(
                'Reservation non trouvee'
            );
        }

        return $this->render('front/reservationaffichage.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    private function renderReservationForm(
        Activite $activite,
        array $fieldErrors = [],
        array $formData = []
    ): Response {
        return $this->render('front/reservationfront.html.twig', [
            'activite' => $activite,
            'fieldErrors' => $fieldErrors,
            'formData' => $formData,
        ]);
    }

    private function mapViolations(
        ConstraintViolationListInterface $violations
    ): array {
        $fieldErrors = [];

        foreach ($violations as $violation) {
            $fieldErrors[$violation->getPropertyPath()][] =
                $violation->getMessage();
        }

        return $fieldErrors;
    }

    private function resolveCurrentUser(
        Request $request,
        EntityManagerInterface $em
    ): ?\App\Entity\UserApp {
        $authenticatedUser = $this->getUser();

        if ($authenticatedUser instanceof \App\Entity\UserApp) {
            return $authenticatedUser;
        }

        $session = $request->getSession();

        if ($session === null) {
            return null;
        }

        $sessionUserId = $session->get('id_user')
            ?? $session->get('user_id');

        if (!is_numeric($sessionUserId)) {
            return null;
        }

        return $em
            ->getRepository(\App\Entity\UserApp::class)
            ->find((int) $sessionUserId);
    }
}