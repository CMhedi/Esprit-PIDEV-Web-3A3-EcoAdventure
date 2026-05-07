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

    /**
     * @return array<int, Message>
     */
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

    /**
     * @return array<string, int>
     */
    public function getLatestIdAndIncomingCount(Conversation $conversation, UserApp $user, int $lastSeenId): array
    {
        $latestId = (int) ($this->createQueryBuilder('m')
            ->select('COALESCE(MAX(m.id_message), 0)')
            ->where('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        $incomingCount = (int) ($this->createQueryBuilder('m')
            ->select('COUNT(m.id_message)')
            ->where('m.conversation = :conversation')
            ->andWhere('m.id_message > :lastSeenId')
            ->andWhere('m.userApp != :user')
            ->setParameter('conversation', $conversation)
            ->setParameter('user', $user)
            ->setParameter('lastSeenId', $lastSeenId)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        $attentionPriority = 'normal';
        if ($incomingCount > 0) {
            $prioritySql = <<<'SQL'
SELECT COALESCE(priorite_message, 'NORMAL') AS priority
FROM message
WHERE id_conversation = :conversation_id
  AND id_message > :last_seen_id
  AND id_user <> :user_id
  AND date_lecture IS NULL
ORDER BY CASE priorite_message
    WHEN 'URGENT' THEN 3
    WHEN 'NORMAL' THEN 2
    WHEN 'FAIBLE' THEN 1
    ELSE 0
END DESC, id_message DESC
LIMIT 1
SQL;

            $priority = $this->getEntityManager()->getConnection()->fetchOne($prioritySql, [
                'conversation_id' => $conversation->getId_conversation(),
                'last_seen_id' => $lastSeenId,
                'user_id' => $user->getId_user(),
            ]);

            $priority = strtoupper(trim((string) $priority));
            if (in_array($priority, ['URGENT', 'NORMAL', 'FAIBLE'], true)) {
                $attentionPriority = strtolower($priority);
            }
        }

        return [
            'latest_id' => $latestId,
            'incoming_count' => $incomingCount,
            'attention_priority' => $attentionPriority,
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
