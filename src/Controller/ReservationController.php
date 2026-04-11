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
            throw $this->createAccessDeniedException('Aucun utilisateur connecte n a ete trouve pour cette reservation.');
        }

        $formData = [
            'date_res' => trim((string) $request->request->get('date_res', '')),
            'statut_res' => trim((string) $request->request->get('statut_res', '')),
            'nb_personnes' => trim((string) $request->request->get('nb_personnes', '')),
        ];

        $reservation = new ReservationActivite();
        $fieldErrors = [];

        if ($formData['date_res'] === '') {
            $reservation->setDateRes(null);
        } else {
            $normalizedDate = str_replace('T', ' ', $formData['date_res']);
            $dateRes = \DateTime::createFromFormat('Y-m-d H:i', $normalizedDate);

            if ($dateRes === false) {
                $fieldErrors['date_res'][] = 'Le format de date doit etre AAAA-MM-JJ HH:MM.';
                $reservation->setDateRes(null);
            } else {
                $reservation->setDateRes($dateRes);
            }
        }

        $reservation->setStatutRes(
            StatutReservationActivite::tryFrom($formData['statut_res'])
        );

        if ($formData['nb_personnes'] === '') {
            $reservation->setNbPersonnes(null);
        } elseif (!ctype_digit($formData['nb_personnes'])) {
            $fieldErrors['nb_personnes'][] = 'Le nombre de personnes doit etre un entier valide.';
            $reservation->setNbPersonnes(null);
        } else {
            $reservation->setNbPersonnes((int) $formData['nb_personnes']);
        }

        $reservation->setUserApp($user);
        $reservation->setActivite($activite);

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

        return $this->redirectToRoute('app_reservation_affichage', [
            'id' => $reservation->getIdResAct(),
        ]);
    }

    #[Route('/reservation/affichage/{id}', name: 'app_reservation_affichage')]
    public function reservationAffichage(
        int $id,
        EntityManagerInterface $em
    ): Response {
        $reservation = $em->getRepository(ReservationActivite::class)->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException('Reservation non trouvee');
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

    private function mapViolations(ConstraintViolationListInterface $violations): array
    {
        $fieldErrors = [];

        foreach ($violations as $violation) {
            $fieldErrors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return $fieldErrors;
    }

    private function resolveCurrentUser(Request $request, EntityManagerInterface $em): ?\App\Entity\UserApp
    {
        $authenticatedUser = $this->getUser();

        if ($authenticatedUser instanceof \App\Entity\UserApp) {
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

        return $em->getRepository(\App\Entity\UserApp::class)->find((int) $sessionUserId);
    }
}
