<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }
    
    public function findOneByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }
    
    // ✅ NOUVELLE MÉTHODE: Rechercher les utilisateurs par nom ou email
    public function searchByNameOrEmail(string $query, int $limit = 20): array
    {
        $searchTerm = '%' . $query . '%';

        return $this->createQueryBuilder('u')
            ->where('u.name LIKE :query')
            ->orWhere('u.email LIKE :query')
            ->setParameter('query', $searchTerm)
            ->orderBy('u.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
    
    public function updateOfflineUsers()
    {
        $limit = new \DateTime('-2 minutes');

        $qb = $this->createQueryBuilder('u')
            ->update()
            ->set('u.isOnline', ':false')
            ->where('u.lastActivity < :limit')
            ->setParameter('false', false)
            ->setParameter('limit', $limit)
            ->getQuery()
            ->execute();
    }
    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
