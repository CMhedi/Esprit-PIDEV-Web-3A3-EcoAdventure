<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\UserApp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function findMessagesForConversation(Conversation $conversation): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('u')
            ->leftJoin('m.userApp', 'u')
            ->where('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.date_envoi', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getLatestIdAndIncomingCount(Conversation $conversation, UserApp $user, int $lastSeenId): array
    {
        $qb = $this->createQueryBuilder('m');
        $result = $qb
            ->select('COALESCE(MAX(m.id_message), 0) AS latest_id')
            ->addSelect('SUM(CASE WHEN m.id_message > :lastSeenId AND m.userApp != :user THEN 1 ELSE 0 END) AS incoming_count')
            ->where('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->setParameter('user', $user)
            ->setParameter('lastSeenId', $lastSeenId)
            ->getQuery()
            ->getSingleResult();

        return [
            'latest_id' => (int) ($result['latest_id'] ?? 0),
            'incoming_count' => (int) ($result['incoming_count'] ?? 0),
        ];
    }

    public function markConversationAsRead(Conversation $conversation, UserApp $user): int
    {
        return $this->createQueryBuilder('m')
            ->update()
            ->set('m.date_lecture', ':readAt')
            ->where('m.conversation = :conversation')
            ->andWhere('m.userApp != :user')
            ->andWhere('m.date_lecture IS NULL')
            ->setParameter('readAt', new \DateTime())
            ->setParameter('conversation', $conversation)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

//    /**
//     * @return Message[] Returns an array of Message objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('m.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Message
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
