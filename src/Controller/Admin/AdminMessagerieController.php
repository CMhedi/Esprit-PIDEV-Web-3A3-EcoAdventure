<?php

namespace App\Controller\Admin;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\UserApp;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserAppRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/messagerie')]
class AdminMessagerieController extends AbstractController
{
    /**
     * Liste TOUTES les conversations du système
     */
    #[Route('/', name: 'admin_messagerie_index')]
    public function index(
        ConversationRepository $conversationRepo,
        MessageRepository $messageRepo,
        Request $request
    ): Response {
        // Get filter parameters
        $search = $request->query->get('search', '');
        $type = $request->query->get('type', '');
        $dateFrom = $request->query->get('date_from', '');
        $dateTo = $request->query->get('date_to', '');

        // Build query
        $qb = $conversationRepo->createQueryBuilder('c')
            ->leftJoin('c.participants', 'p')
            ->leftJoin('c.createur', 'cr')
            ->addSelect('p', 'cr')
            ->orderBy('c.date_creation', 'DESC');

        // Apply filters
        if (!empty($search)) {
            $qb->andWhere('c.titre LIKE :search OR cr.nom LIKE :search OR cr.prenom LIKE :search OR p.nom LIKE :search OR p.prenom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if (!empty($type)) {
            if ($type === 'groupe') {
                $qb->andWhere('c.est_groupe = true');
            } elseif ($type === 'privee') {
                $qb->andWhere('c.est_groupe = false');
            }
        }

        if (!empty($dateFrom)) {
            $qb->andWhere('c.date_creation >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($dateFrom));
        }

        if (!empty($dateTo)) {
            $qb->andWhere('c.date_creation <= :dateTo')
               ->setParameter('dateTo', new \DateTime($dateTo . ' 23:59:59'));
        }

        $conversations = $qb->getQuery()->getResult();
        
        // Récupérer le dernier message pour chaque conversation
        foreach ($conversations as $conversation) {
            $lastMessage = $messageRepo->findOneBy(
                ['conversation' => $conversation],
                ['date_envoi' => 'DESC']
            );
            $conversation->lastMessage = $lastMessage;
        }

        // Get statistics
        $stats = $this->getAdvancedStats($conversationRepo, $messageRepo);

        return $this->render('admin/messagerie/index.html.twig', [
            'conversations' => $conversations,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Voir les détails d'une conversation spécifique
     */
    #[Route('/{id}', name: 'admin_messagerie_view', requirements: ['id' => '\d+'])]
    public function view(
        Conversation $conversation,
        MessageRepository $messageRepo
    ): Response {
        $messages = $messageRepo->findBy(
            ['conversation' => $conversation],
            ['date_envoi' => 'ASC']
        );

        return $this->render('admin/messagerie/view.html.twig', [
            'conversation' => $conversation,
            'messages' => $messages,
            'participants' => $conversation->getParticipants(),
        ]);
    }

    /**
     * Supprimer une conversation (Modération)
     */
    #[Route('/delete/{id}', name: 'admin_messagerie_delete', methods: ['POST'])]
    public function delete(
        Conversation $conversation,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$conversation->getId_conversation(), $request->request->get('_token'))) {
            $em->remove($conversation);
            $em->flush();

            $this->addFlash('success', 'Conversation supprimée par l\'administrateur.');
        }

        return $this->redirectToRoute('admin_messagerie_index');
    }

    /**
     * Supprimer un message spécifique
     */
    #[Route('/message/delete/{id}', name: 'admin_messagerie_delete_message', methods: ['POST'])]
    public function deleteMessage(
        Message $message,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$message->getId_message(), $request->request->get('_token'))) {
            $conversationId = $message->getConversation()->getId_conversation();
            $em->remove($message);
            $em->flush();

            $this->addFlash('success', 'Message supprimé par l\'administrateur.');
            return $this->redirectToRoute('admin_messagerie_view', ['id' => $conversationId]);
        }

        return $this->redirectToRoute('admin_messagerie_index');
    }

    /**
     * Bannir une conversation (supprimer tous les messages et marquer comme bannie)
     */
    #[Route('/ban/{id}', name: 'admin_messagerie_ban_conversation', methods: ['POST'])]
    public function banConversation(
        Conversation $conversation,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        if ($this->isCsrfTokenValid('ban'.$conversation->getId_conversation(), $request->request->get('_token'))) {
            // Supprimer tous les messages de la conversation
            $messages = $conversation->getMessages();
            foreach ($messages as $message) {
                $em->remove($message);
            }

            if (!$conversation->isEst_groupe()) {
                $conversation->blockPrivateConversation();
                $em->persist($conversation);
            }

            $em->flush();
            
            // Ajouter un message système indiquant que la conversation est bannie
            // On ne crée pas de message système pour éviter l'erreur id_user null
            // La conversation reste vide après suppression des messages

            $this->addFlash('warning', 'Conversation bannie et tous les messages supprimés.');
        }

        return $this->redirectToRoute('admin_messagerie_index');
    }

    /**
     * Bannir un utilisateur de toutes les conversations
     */
    #[Route('/ban-user/{id}', name: 'admin_messagerie_ban_user', methods: ['POST'])]
    public function banUser(
        UserApp $user,
        ConversationRepository $conversationRepo,
        MessageRepository $messageRepo,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        if ($this->isCsrfTokenValid('ban_user'.$user->getId_user(), $request->request->get('_token'))) {
            // Récupérer toutes les conversations où l'utilisateur est créateur ou participant
            $qb = $conversationRepo->createQueryBuilder('c')
                ->leftJoin('c.participants', 'p')
                ->where('c.createur = :user OR p.id_user = :user')
                ->setParameter('user', $user->getId_user())
                ->getQuery();
            
            $conversations = $qb->getResult();
            
            $bannedCount = 0;
            foreach ($conversations as $conversation) {
                // Supprimer tous les messages de l'utilisateur dans cette conversation
                $messages = $messageRepo->findBy([
                    'conversation' => $conversation,
                    'userApp' => $user
                ]);
                
                foreach ($messages as $message) {
                    $em->remove($message);
                    $bannedCount++;
                }
                
                // Si l'utilisateur est le créateur, supprimer la conversation
                if ($conversation->getCreateur()?->getId_user() === $user->getId_user()) {
                    $allMessages = $conversation->getMessages();
                    foreach ($allMessages as $message) {
                        $em->remove($message);
                    }
                    $em->remove($conversation);
                }
            }
            
            $em->flush();

            $this->addFlash('error', 'Utilisateur banni. ' . $bannedCount . ' messages supprimés.');
        }

        return $this->redirectToRoute('admin_messagerie_index');
    }

    /**
     * Get advanced statistics for messaging system
     */
    private function getAdvancedStats(
        ConversationRepository $conversationRepo,
        MessageRepository $messageRepo
    ): array {
        $stats = [];

        // Most active accounts (most conversations)
        $qbMostConversations = $conversationRepo->createQueryBuilder('c')
            ->select('cr.id_user as user_id', 'cr.nom', 'cr.prenom', 'COUNT(c.id_conversation) as conversation_count')
            ->leftJoin('c.createur', 'cr')
            ->groupBy('cr.id_user', 'cr.nom', 'cr.prenom')
            ->orderBy('conversation_count', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $stats['most_conversations'] = $qbMostConversations;

        // Most communicative users per day (last 7 days)
        $sevenDaysAgo = new \DateTime('-7 days');
        $qbMostMessages = $messageRepo->createQueryBuilder('m')
            ->select('u.id_user as user_id', 'u.nom', 'u.prenom', 'COUNT(m.id_message) as message_count')
            ->leftJoin('m.userApp', 'u')
            ->where('m.date_envoi >= :date')
            ->setParameter('date', $sevenDaysAgo)
            ->groupBy('u.id_user', 'u.nom', 'u.prenom')
            ->orderBy('message_count', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $stats['most_messages'] = $qbMostMessages;

        // Type distribution
        $qbTypeStats = $conversationRepo->createQueryBuilder('c')
            ->select('c.est_groupe as is_group', 'COUNT(c.id_conversation) as count')
            ->groupBy('c.est_groupe')
            ->getQuery()
            ->getResult();

        $typeStats = ['group' => 0, 'private' => 0];
        foreach ($qbTypeStats as $stat) {
            if ($stat['is_group']) {
                $typeStats['group'] = $stat['count'];
            } else {
                $typeStats['private'] = $stat['count'];
            }
        }
        $stats['type_distribution'] = $typeStats;

        // Messages per day (last 7 days)
        $qbMessagesPerDay = $messageRepo->createQueryBuilder('m')
            ->select('m.date_envoi as date', 'COUNT(m.id_message) as count')
            ->where('m.date_envoi >= :date')
            ->setParameter('date', $sevenDaysAgo)
            ->groupBy('m.date_envoi')
            ->orderBy('m.date_envoi', 'ASC')
            ->getQuery()
            ->getResult();

        $stats['messages_per_day'] = $qbMessagesPerDay;

        // Message type distribution (last 7 days)
        $qbMessageTypeStats = $messageRepo->createQueryBuilder('m')
            ->select('m.type_message as type', 'COUNT(m.id_message) as count')
            ->where('m.date_envoi >= :date')
            ->setParameter('date', $sevenDaysAgo)
            ->groupBy('m.type_message')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $stats['message_type_distribution'] = $qbMessageTypeStats;

        return $stats;
    }

    /**
     * Statistiques pour le dashboard admin
     */
    #[Route('/stats', name: 'admin_messagerie_stats')]
    public function stats(
        ConversationRepository $conversationRepo,
        MessageRepository $messageRepo
    ): Response {
        $totalConversations = count($conversationRepo->findAll());
        $totalMessages = count($messageRepo->findAll());
        
        // Conversations des 7 derniers jours
        $sevenDaysAgo = new \DateTime('-7 days');
        $recentConversations = count($conversationRepo->createQueryBuilder('c')
            ->where('c.date_creation >= :date')
            ->setParameter('date', $sevenDaysAgo)
            ->getQuery()
            ->getResult());

        // Messages des 7 derniers jours
        $recentMessages = count($messageRepo->createQueryBuilder('m')
            ->where('m.date_envoi >= :date')
            ->setParameter('date', $sevenDaysAgo)
            ->getQuery()
            ->getResult());

        return $this->json([
            'total_conversations' => $totalConversations,
            'total_messages' => $totalMessages,
            'recent_conversations' => $recentConversations,
            'recent_messages' => $recentMessages,
        ]);
    }
}
