<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\UserApp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

//    /**
//     * @return Conversation[] Returns an array of Conversation objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }
// Fichier: src/Repository/ConversationRepository.php

public function findOneByParticipants(UserApp $u1, UserApp $u2)
{
    return $this->createQueryBuilder('c')
        ->join('c.participants', 'p1')
        ->join('c.participants', 'p2')
        ->where('c.est_groupe = :isGroup')
        ->andWhere('p1 = :u1')
        ->andWhere('p2 = :u2')
        ->setParameter('isGroup', false)
        ->setParameter('u1', $u1)
        ->setParameter('u2', $u2)
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
}

    public function findLatestNonBlockedPrivateByParticipants(UserApp $u1, UserApp $u2): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->join('c.participants', 'p1')
            ->join('c.participants', 'p2')
            ->where('c.est_groupe = :isGroup')
            ->andWhere('p1 = :u1')
            ->andWhere('p2 = :u2')
            ->andWhere('(c.titre IS NULL OR c.titre NOT LIKE :blockedPrefix)')
            ->setParameter('isGroup', false)
            ->setParameter('u1', $u1)
            ->setParameter('u2', $u2)
            ->setParameter('blockedPrefix', '[PRIVATE_BLOCKED] %')
            ->orderBy('c.date_creation', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findConversationsByUser(UserApp $user)
    {
        return $this->createQueryBuilder('c')
            ->addSelect('participants', 'createur')
            ->join('c.participants', 'p')
            ->leftJoin('c.participants', 'participants')
            ->leftJoin('c.createur', 'createur')
            ->where('p = :user')
            ->setParameter('user', $user)
            ->orderBy('c.date_creation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getUnreadCountsForUser(UserApp $user): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(m.conversation) AS conversation_id, COUNT(m.id_message) AS unread_count')
            ->from(Message::class, 'm')
            ->join('m.conversation', 'c')
            ->join('c.participants', 'p')
            ->where('p = :user')
            ->andWhere('m.userApp != :user')
            ->andWhere('m.date_lecture IS NULL')
            ->groupBy('m.conversation')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['conversation_id']] = (int) $row['unread_count'];
        }

        return $counts;
    }

//    public function findOneBySomeField($value): ?Conversation
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
