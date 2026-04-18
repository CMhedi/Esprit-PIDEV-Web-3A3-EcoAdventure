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
    public function getUnread(NotificationRepository $repository): JsonResponse
    {
        $allRecent = $repository->findRecent(15);
        $unreadCount = count($repository->findUnread());
        
        $data = array_map(function(Notification $n) {
            return [
                'id' => $n->getId(),
                'title' => $n->getTitle(),
                'message' => $n->getMessage(),
                'createdAt' => $n->getCreatedAt()->format('d/m/Y H:i'),
                'type' => $n->getType(),
                'isRead' => $n->isRead(),
            ];
        }, $allRecent);

        return $this->json([
            'notifications' => $data,
            'unreadCount' => $unreadCount
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
