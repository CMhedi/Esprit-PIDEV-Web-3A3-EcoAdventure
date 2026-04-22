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
    // =========================
    // FORMULAIRE RESERVATION
    // =========================
    #[Route('/reservationfront/{id}', name: 'app_reservation_front')]
    public function reservationFront(int $id, EntityManagerInterface $em): Response
    {
        $activite = $em->getRepository(Activite::class)->find($id);

        if (!$activite) {
            throw $this->createNotFoundException('Activite non trouvee');
        }

        return $this->render('front/reservationfront.html.twig', [
            'activite' => $activite,
            'fieldErrors' => [],
            'formData' => []
        ]);
    }

    #[Route('/reservation/affichage/{id}', name: 'app_reservation_affichage')]
public function reservationAffichage(int $id, EntityManagerInterface $em): Response
{
    $reservation = $em->getRepository(ReservationActivite::class)->find($id);

    if (!$reservation) {
        throw $this->createNotFoundException('Reservation introuvable');
    }

    return $this->render('front/reservationaffichage.html.twig', [
        'reservation' => $reservation
    ]);
}

    // =========================
    // CREATE RESERVATION
    // =========================
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

        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('Utilisateur non connecte');
        }

        $formData = [
            'date_res' => $request->request->get('date_res', ''),
            'statut_res' => $request->request->get('statut_res', ''),
            'nb_personnes' => $request->request->get('nb_personnes', ''),
        ];

        $latitude = $request->request->get('latitude');
        $longitude = $request->request->get('longitude');

        $reservation = new ReservationActivite();
        $errors = [];

        // DATE
        if ($formData['date_res']) {
            $date = \DateTime::createFromFormat('Y-m-d H:i', str_replace('T', ' ', $formData['date_res']));
            if (!$date) {
                $errors['date_res'][] = 'Format invalide';
            } else {
                $reservation->setDateRes($date);
            }
        }

        // STATUT
        $reservation->setStatutRes(
            StatutReservationActivite::tryFrom($formData['statut_res'])
        );

        // NB PERSONNES
        if (!ctype_digit($formData['nb_personnes'])) {
            $errors['nb_personnes'][] = 'Nombre invalide';
        } else {
            $reservation->setNbPersonnes((int)$formData['nb_personnes']);
        }

        $reservation->setUserApp($user);
        $reservation->setActivite($activite);

        // =========================
        // DISPONIBILITE LOGIQUE
        // =========================
        $conn = $em->getConnection();

        $capacite = $conn->fetchOne(
            "SELECT capacite_totale FROM capacity_policy WHERE categorie_act = :cat",
            ['cat' => $activite->getCategorieAct()->value]
        );

        $reserve = $conn->fetchOne(
            "SELECT COALESCE(SUM(r.nb_personnes),0)
             FROM reservation_activite r
             JOIN activite a ON r.id_activite = a.id_activite
             WHERE a.categorie_act = :cat",
            ['cat' => $activite->getCategorieAct()->value]
        );

        $disponible = $capacite - $reserve;

        if ((int)$formData['nb_personnes'] > $disponible) {
            $errors['nb_personnes'][] = "Seulement $disponible places disponibles";
        }

        // VALIDATION SYMFONY
        $violations = $this->mapViolations($validator->validate($reservation));
        $errors = array_merge_recursive($errors, $violations);

        if ($errors) {
            return $this->render('front/reservationfront.html.twig', [
                'activite' => $activite,
                'fieldErrors' => $errors,
                'formData' => $formData
            ]);
        }

        $em->persist($reservation);
        $em->flush();

        return $this->redirectToRoute('app_reservation_weather', [
            'id' => $reservation->getIdResAct(),
            'lat' => $latitude,
            'lon' => $longitude
        ]);
    }

    // =========================
    // WEATHER PAGE (IMPORTANT)
    // =========================
   #[Route('/reservation/weather/{id}', name: 'app_reservation_weather')]
public function weatherPage(int $id, Request $request, EntityManagerInterface $em): Response
{
    $reservation = $em->getRepository(ReservationActivite::class)->find($id);

    if (!$reservation) {
        throw $this->createNotFoundException('Reservation introuvable');
    }

    // 🔥 IMPORTANT : fallback + request + activite
    $lat = $request->query->get('lat');
    $lon = $request->query->get('lon');

    // 🔥 si vide → prendre depuis activite (SECURITE)
    if (!$lat || !$lon) {
        $activite = $reservation->getActivite();
        $lat = $activite->getLatitude();
        $lon = $activite->getLongitude();
    }

    $date = $reservation->getDateRes()->format('Y-m-d');

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
            'message' => 'Meteo indisponible'
        ]);
    }

    $data = json_decode($response, true);
    $code = $data['daily']['weathercode'][0] ?? null;

    $rainCodes = [51, 53, 55, 61, 63, 65, 80, 81, 82];

    return $this->render('front/weather_result.html.twig', [
        'reservation' => $reservation,
        'isRainy' => in_array($code, $rainCodes),
        'weatherUnavailable' => false,
        'message' => null
    ]);
}
    // DISPONIBILITE
    // =========================
    #[Route('/reservation/disponibilite/{id}', name: 'app_reservation_disponibilite')]
    public function disponibiliteCategorie(int $id, EntityManagerInterface $em): Response
    {
        $activite = $em->getRepository(Activite::class)->find($id);

        if (!$activite) {
            throw $this->createNotFoundException('Activite non trouvee');
        }

        $conn = $em->getConnection();

        $capacite = $conn->fetchOne(
            "SELECT capacite_totale FROM capacity_policy WHERE categorie_act = :cat",
            ['cat' => $activite->getCategorieAct()->value]
        );

        $reserve = $conn->fetchOne(
            "SELECT COALESCE(SUM(nb_personnes),0)
             FROM reservation_activite r
             JOIN activite a ON r.id_activite = a.id_activite
             WHERE a.categorie_act = :cat",
            ['cat' => $activite->getCategorieAct()->value]
        );

        return $this->render('front/disponibilite.html.twig', [
            'activite' => $activite,
            'capaciteTotale' => $capacite,
            'nbReserve' => $reserve,
            'disponible' => $capacite - $reserve
        ]);
    }

    // =========================
    // UTILS
    // =========================
    private function mapViolations(ConstraintViolationListInterface $violations): array
    {
        $errors = [];

        foreach ($violations as $v) {
            $errors[$v->getPropertyPath()][] = $v->getMessage();
        }

        return $errors;
    }
}