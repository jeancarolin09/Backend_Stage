<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Conversation;
use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    // Trouver les messages d'une conversation (avec pagination)
    public function findByConversation(
        Conversation $conversation,
        int $limit = 50,
        int $offset = 0
    ): array {
        return $this->createQueryBuilder('m')
            ->where('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    // Compter les messages
    public function countByConversation(Conversation $conversation): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getUnreadCountByConversation(User $user): array
{
    $qb = $this->createQueryBuilder('m')
        ->select('IDENTITY(m.conversation) AS conversationId, COUNT(m.id) AS unreadCount')
        ->join('m.conversation', 'c')
        ->join('c.participants', 'p')
        ->where('p = :user')
        ->andWhere('m.sender != :user')
        ->andWhere('m.isRead = false')
        ->groupBy('m.conversation')
        ->setParameter('user', $user);

    $result = $qb->getQuery()->getArrayResult();

    // Format en tableau indexé par conversationId
    $formatted = [];
    foreach ($result as $row) {
        $formatted[$row['conversationId']] = (int)$row['unreadCount'];
    }

    return $formatted;
}
  public function markConversationAsRead(int $conversationId, User $user)
{
    $this->createQueryBuilder('m')
        ->update()
        ->set('m.isRead', true)
        ->where('m.conversation = :cid')
        ->andWhere('m.sender != :user')
        ->andWhere('m.isRead = false')
        ->setParameter('cid', $conversationId)
        ->setParameter('user', $user)
        ->getQuery()
        ->execute();
}


}