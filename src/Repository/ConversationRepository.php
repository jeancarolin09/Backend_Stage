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

    // Trouver une conversation existante par liste de participants
    public function findByParticipantIds(array $participantIds): ?Conversation
    {
        $qb = $this->createQueryBuilder('c');
        
        foreach ($participantIds as $i => $id) {
            $qb->innerJoin('c.participants', "p{$i}", 'WITH', "p{$i}.id = :id{$i}")
               ->setParameter("id{$i}", $id);
        }

        $qb->having($qb->expr()->eq("COUNT(DISTINCT c.id)", count($participantIds)))
           ->groupBy('c.id');

        $result = $qb->getQuery()->getResult();
        return $result[0] ?? null;
    }
}