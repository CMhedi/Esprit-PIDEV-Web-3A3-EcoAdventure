<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\Notification;
use App\Entity\ReservationEvenement;
use App\Entity\UserApp;
use App\Enum\StatutReservationEvenement;
use Doctrine\ORM\EntityManagerInterface;

class EventWorkflowService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Gère l'annulation d'une réservation et la promotion de la liste d'attente
     */
    public function cancelReservations(UserApp $user, Evenement $evenement): void
    {
        $reservations = $this->entityManager->getRepository(ReservationEvenement::class)->findBy([
            'userApp' => $user,
            'evenement' => $evenement
        ]);

        foreach ($reservations as $reservation) {
            $reservation->setStatut_res(StatutReservationEvenement::ANNULEE);
        }

        // Notification Admin
        $notif = new Notification();
        $notif->setTitle('Annulation de Réservation')
            ->setMessage(sprintf('Le client %s %s a annulé sa participation à l\'événement %s.', $user->getNom(), $user->getPrenom(), $evenement->getTitre()))
            ->setType('cancellation');
        $this->entityManager->persist($notif);

        $this->entityManager->flush();

        // Promotion de la liste d'attente
        $this->promoteWaitlist($evenement);
    }

    /**
     * Promeut les personnes de la liste d'attente si des places se libèrent
     */
    public function promoteWaitlist(Evenement $evenement): void
    {
        $reservationsAttente = $this->entityManager->getRepository(ReservationEvenement::class)->findBy(
            ['evenement' => $evenement, 'statut_res' => StatutReservationEvenement::LISTE_ATTENTE],
            ['date_reservation' => 'ASC']
        );

        $placesRestantes = $evenement->getPlacesRestantes();
        foreach ($reservationsAttente as $resAttente) {
            if ($resAttente->getNb_billets() <= $placesRestantes) {
                $resAttente->setStatut_res(StatutReservationEvenement::EN_ATTENTE);
                $resAttente->setIsNotifiedAvailability(true);
                $placesRestantes -= $resAttente->getNb_billets();

                $notifWaitlist = new Notification();
                $notifWaitlist->setTitle('Priorité liste d\'attente')
                    ->setMessage(sprintf(
                        'La réservation du client %s %s pour l\'événement %s est passée de la liste d\'attente à En Attente (disponible).',
                        $resAttente->getUserApp()->getNom(),
                        $resAttente->getUserApp()->getPrenom(),
                        $evenement->getTitre()
                    ))
                    ->setType('availability');
                $this->entityManager->persist($notifWaitlist);
            }
        }
        $this->entityManager->flush();
    }
}
