<?php
namespace App\Repository;

use App\Entity\Activity;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    /**
     * Récupère les activités liées à un utilisateur,
     * mais seulement si l'événement existe encore.
     */
   public function findByUserOrRelatedSafe(User $user, int $limit = 50): array
{
    return $this->createQueryBuilder('a')
        ->leftJoin('a.event', 'e') // innerJoin → l’événement existe forcément
        ->leftJoin('a.targetUser', 't')
        ->leftJoin('a.actor', 'act')
        ->leftJoin('e.invitations', 'inv')
        ->where('a.actor = :user OR a.targetUser = :user OR e.organizer = :user OR inv.email = :email')
        ->setParameter('user', $user)
        ->setParameter('email', $user->getEmail())
        ->orderBy('a.createdAt', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}

// public function countUnreadForUser(User $user): int
// {
//     return (int) $this->createQueryBuilder('a')
//         ->select('COUNT(a.id)')
//         ->leftJoin('a.event', 'e')
//         ->leftJoin('a.targetUser', 't')
//         ->leftJoin('e.invitations', 'inv')
//         ->where('a.isRead = false')
//         ->andWhere('(a.actor = :user OR a.targetUser = :user OR e.organizer = :user OR inv.email = :email)')
//         ->setParameter('user', $user)
//         ->setParameter('email', $user->getEmail())
//         ->getQuery()
//         ->getSingleScalarResult();
// }
public function markAllAsRead(User $user): void
{
    $qb = $this->createQueryBuilder('a')
        ->update()
        ->set('a.isRead', ':true')
        ->where('a.isRead = false')
        ->andWhere('(a.actor = :user OR a.targetUser = :user)')
        ->setParameter('true', true)
        ->setParameter('user', $user);

    $qb->getQuery()->execute();
}

}
