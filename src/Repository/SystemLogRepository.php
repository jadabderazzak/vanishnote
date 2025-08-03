<?php

namespace App\Repository;

use App\Entity\SystemLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SystemLog>
 */
class SystemLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemLog::class);
    }

   /**
     * Retrieves the five most recent system logs ordered by logged date (newest first).
     *
     * @return SystemLog[] Returns an array of SystemLog objects
     */
    public function findFiveMostRecentLogs(): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.isHandled = :false')
            ->setParameter('false', false)
            ->orderBy('l.loggedAt', 'DESC')
            ->setMaxResults(5)
            ->orderBy('l.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
