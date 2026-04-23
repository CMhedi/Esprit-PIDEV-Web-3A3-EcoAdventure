<?php

namespace App\Controller;

use App\Entity\Activite;
use App\Entity\ReservationActivite;
use App\Entity\UserApp;
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

    #[Route('/reservation/affichage/{id}', name: 'app_reservation_affichage')]
    public function reservationAffichage(int $id, EntityManagerInterface $em): Response
    {
        $reservation = $em->getRepository(ReservationActivite::class)->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException('Reservation introuvable');
        }

        return $this->render('front/reservationaffichage.html.twig', [
            'reservation' => $reservation,
        ]);
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
            throw $this->createAccessDeniedException('Aucun utilisateur connecte n a ete trouve pour cette reservation.');
        }

        $formData = [
            'date_res' => trim((string) $request->request->get('date_res', '')),
            'statut_res' => trim((string) $request->request->get('statut_res', '')),
            'nb_personnes' => trim((string) $request->request->get('nb_personnes', '')),
        ];

        $latitude = $request->request->get('latitude');
        $longitude = $request->request->get('longitude');

        $reservation = new ReservationActivite();
        $fieldErrors = [];

        if ($formData['date_res'] !== '') {
            $date = \DateTime::createFromFormat('Y-m-d H:i', str_replace('T', ' ', $formData['date_res']));
            if ($date === false) {
                $fieldErrors['date_res'][] = 'Format invalide';
            } else {
                $reservation->setDateRes($date);
            }
        }

        $reservation->setStatutRes(
            StatutReservationActivite::tryFrom($formData['statut_res'])
        );

        if ($formData['nb_personnes'] === '') {
            $reservation->setNbPersonnes(null);
        } elseif (!ctype_digit($formData['nb_personnes'])) {
            $fieldErrors['nb_personnes'][] = 'Nombre invalide';
        } else {
            $reservation->setNbPersonnes((int) $formData['nb_personnes']);
        }

        $reservation->setUserApp($user);
        $reservation->setActivite($activite);

        $conn = $em->getConnection();

        $capacite = (int) $conn->fetchOne(
            "SELECT capacite_totale FROM capacity_policy WHERE categorie_act = :cat",
            ['cat' => $activite->getCategorieAct()->value]
        );

        $reserve = (int) $conn->fetchOne(
            "SELECT COALESCE(SUM(r.nb_personnes), 0)
             FROM reservation_activite r
             JOIN activite a ON r.id_activite = a.id_activite
             WHERE a.categorie_act = :cat",
            ['cat' => $activite->getCategorieAct()->value]
        );

        $disponible = $capacite - $reserve;

        if (ctype_digit($formData['nb_personnes']) && (int) $formData['nb_personnes'] > $disponible) {
            $fieldErrors['nb_personnes'][] = "Seulement $disponible places disponibles";
        }

        $validationErrors = $this->mapViolations($validator->validate($reservation));
        foreach (array_keys($fieldErrors) as $fieldName) {
            unset($validationErrors[$fieldName]);
        }
        $fieldErrors = array_merge_recursive($fieldErrors, $validationErrors);

        if ($fieldErrors !== []) {
            return $this->renderReservationForm($activite, $fieldErrors, $formData);
        }

        $em->persist($reservation);
        $em->flush();

        $this->addFlash('success', 'Reservation effectuee avec succes');

        return $this->redirectToRoute('app_reservation_weather', [
            'id' => $reservation->getIdResAct(),
            'lat' => $latitude ?? $activite->getLatitude(),
            'lon' => $longitude ?? $activite->getLongitude(),
        ]);
    }

    #[Route('/reservation/weather/{id}', name: 'app_reservation_weather')]
    public function weatherPage(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $reservation = $em->getRepository(ReservationActivite::class)->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException('Reservation introuvable');
        }

        $lat = $request->query->get('lat');
        $lon = $request->query->get('lon');

        if (!$lat || !$lon) {
            $activite = $reservation->getActivite();
            $lat = $activite?->getLatitude();
            $lon = $activite?->getLongitude();
        }

        $date = $reservation->getDateRes()?->format('Y-m-d');

        if (!$lat || !$lon || !$date) {
            return $this->render('front/weather_result.html.twig', [
                'reservation' => $reservation,
                'weatherUnavailable' => true,
                'isRainy' => false,
                'message' => 'Meteo indisponible',
            ]);
        }

        $url = "https://api.open-meteo.com/v1/forecast"
            . "?latitude={$lat}"
            . "&longitude={$lon}"
            . "&daily=weathercode"
            . "&timezone=auto"
            . "&start_date={$date}"
            . "&end_date={$date}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return $this->render('front/weather_result.html.twig', [
                'reservation' => $reservation,
                'weatherUnavailable' => true,
                'isRainy' => false,
                'message' => 'Meteo indisponible',
            ]);
        }

        $data = json_decode($response, true);
        $code = $data['daily']['weathercode'][0] ?? null;
        $rainCodes = [51, 53, 55, 61, 63, 65, 80, 81, 82];

        return $this->render('front/weather_result.html.twig', [
            'reservation' => $reservation,
            'isRainy' => in_array($code, $rainCodes, true),
            'weatherUnavailable' => false,
            'message' => null,
        ]);
    }

    #[Route('/reservation/disponibilite/{id}', name: 'app_reservation_disponibilite')]
    public function disponibiliteCategorie(int $id, EntityManagerInterface $em): Response
    {
        $activite = $em->getRepository(Activite::class)->find($id);

        if (!$activite) {
            throw $this->createNotFoundException('Activite non trouvee');
        }

        $conn = $em->getConnection();

        $capacite = (int) $conn->fetchOne(
            "SELECT capacite_totale FROM capacity_policy WHERE categorie_act = :cat",
            ['cat' => $activite->getCategorieAct()->value]
        );

        $reserve = (int) $conn->fetchOne(
            "SELECT COALESCE(SUM(nb_personnes), 0)
             FROM reservation_activite r
             JOIN activite a ON r.id_activite = a.id_activite
             WHERE a.categorie_act = :cat",
            ['cat' => $activite->getCategorieAct()->value]
        );

        return $this->render('front/disponibilite.html.twig', [
            'activite' => $activite,
            'capaciteTotale' => $capacite,
            'nbReserve' => $reserve,
            'disponible' => $capacite - $reserve,
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

    private function mapViolations(ConstraintViolationListInterface $violations): array
    {
        $errors = [];

        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return $errors;
    }

    private function resolveCurrentUser(Request $request, EntityManagerInterface $em): ?UserApp
    {
        $authenticatedUser = $this->getUser();
        if ($authenticatedUser instanceof UserApp) {
            return $authenticatedUser;
        }

        $session = $request->getSession();
        if ($session === null) {
            return null;
        }

        $sessionUserId = $session->get('id_user') ?? $session->get('user_id');
        if (!is_numeric($sessionUserId)) {
            return null;
        }

        return $em->getRepository(UserApp::class)->find((int) $sessionUserId);
    }
}
