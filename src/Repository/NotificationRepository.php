<?php

namespace App\Repository;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 *
 * @method Notification|null find($id, $lockMode = null, $lockVersion = null)
 * @method Notification|null findOneBy(array $criteria, array $orderBy = null)
 * @method Notification[]    findAll()
 * @method Notification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Compte le nombre de notifications non lues pour un utilisateur et une table donnée.
     */
    public function countUnreadForUserAndTable(int $userId, string $relatedTable): int
    {
        return $this->createQueryBuilder('n')
            ->select('count(n.id)')
            ->where('n.recipient = :userId')
            ->andWhere('n.relatedTable = :table')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('userId', $userId)
            ->setParameter('table', $relatedTable)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }
    
    /**
     * Marque toutes les notifications non lues d'une catégorie comme lues pour un utilisateur.
     */
    public function markAllAsReadForUserAndTable(int $userId, string $relatedTable): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->where('n.recipient = :userId')
            ->andWhere('n.relatedTable = :table')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('userId', $userId)
            ->setParameter('table', $relatedTable)
            ->setParameter('isRead', false)
            ->getQuery()
            ->execute();
    }
}