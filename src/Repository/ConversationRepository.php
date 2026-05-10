<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    // Trouver les conversations d'un utilisateur
    public function findByParticipant(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'p')
            ->where('p.id = :userId')
            ->setParameter('userId', $user->getId())
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

   public function findByParticipantIds(array $participantIds): ?Conversation
{
    $participantIds = array_unique($participantIds);

    $qb = $this->createQueryBuilder('c')
        ->join('c.participants', 'p')
        ->where('p.id IN (:ids)')
        ->groupBy('c.id')
        ->having('COUNT(DISTINCT p.id) = :count')
        ->setParameter('ids', $participantIds)
        ->setParameter('count', count($participantIds));

    return $qb->getQuery()->getOneOrNullResult();
}

}