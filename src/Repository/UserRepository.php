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

    /**
     * Find unverified users that were created more than the specified number of days ago
     *
     * @param int $days Number of days
     * @return array<int, User>
     */
    public function findUnverifiedOlderThan(int $days): array
    {
        $date = new \DateTimeImmutable(sprintf('-%d days', $days));
        
        return $this->createQueryBuilder('u')
            ->where('u.isVerified = :verified')
            ->andWhere('u.createdAt < :date')
            ->setParameter('verified', false)
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }
}
