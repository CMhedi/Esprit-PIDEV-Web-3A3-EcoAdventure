<?php

namespace App\Controller\Admin;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/notifications')]
class NotificationAdminController extends AbstractController
{
    #[Route('/unread', name: 'app_admin_notifications_unread', methods: ['GET'])]
    public function getUnread(NotificationRepository $repository, \App\Repository\ReservationEvenementRepository $resRepo): JsonResponse
    {
        $allRecent = $repository->findRecent(15);
        $unreadCount = count($repository->findUnread());
        
        $data = array_map(function(Notification $n) {
            // Force timezone to Africa/Tunis for real-time consistency with user location
            $date = $n->getCreatedAt();
            if ($date instanceof \DateTimeImmutable) {
                $date = \DateTime::createFromImmutable($date);
            }
            $date->setTimezone(new \DateTimeZone('Africa/Tunis'));

            return [
                'id' => $n->getId(),
                'title' => $n->getTitle(),
                'message' => $n->getMessage(),
                'createdAt' => $date->format('d/m/Y H:i:s'),
                'type' => $n->getType(),
                'isRead' => $n->isRead(),
            ];
        }, $allRecent);

        // Calculate Stats for Topbar
        $stats = [
            'confirmee' => $resRepo->count(['statut_res' => \App\Enum\StatutReservationEvenement::CONFIRMEE]),
            'annulee' => $resRepo->count(['statut_res' => \App\Enum\StatutReservationEvenement::ANNULEE]),
            'attente' => $resRepo->count(['statut_res' => \App\Enum\StatutReservationEvenement::EN_ATTENTE]) + $resRepo->count(['statut_res' => \App\Enum\StatutReservationEvenement::LISTE_ATTENTE])
        ];

        return $this->json([
            'notifications' => $data,
            'unreadCount' => $unreadCount,
            'stats' => $stats
        ]);
    }

    #[Route('/mark-as-read/{id}', name: 'app_admin_notifications_read', methods: ['POST'])]
    public function markAsRead(Notification $notification, EntityManagerInterface $em): JsonResponse
    {
        $notification->setIsRead(true);
        $em->flush();

        return $this->json(['status' => 'success']);
    }

    #[Route('/mark-all-read', name: 'app_admin_notifications_read_all', methods: ['POST'])]
    public function markAllRead(NotificationRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $unread = $repository->findUnread();
        foreach ($unread as $n) {
            $n->setIsRead(true);
        }
        $em->flush();

        return $this->json(['status' => 'success']);
    }
}
