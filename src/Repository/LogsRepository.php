<?php

namespace App\Repository;

use App\Entity\Logs;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Logs>
 */
class LogsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Logs::class);
    }

    /**
 * Retrieves the last 5 unique logs (notes) for a specific user.
 *
 * This method fetches up to 5 Logs entities related to distinct Notes,
 * ordered by deletion date descending (most recent first),
 * for the given user. It avoids duplicates by grouping on the note.
 *
 * @param User $user The user whose logs to retrieve.
 *
 * @return Logs[] Returns an array of Logs entities.
 */
public function findLastFiveUniqueNotesByUser(User $user): array
{
    return $this->createQueryBuilder('l')
        ->innerJoin('l.note', 'n')
        ->addSelect('n') 
        ->where('l.user = :user')
        ->setParameter('user', $user)
        ->groupBy('n.id')
        ->orderBy('l.deletedAt', 'DESC')
        ->setMaxResults(5)
        ->getQuery()
        ->getResult();
}

/**
 * Retrieves all logs for a specific user ordered by deletion date descending.
 *
 * This method fetches all Logs entities related to the given user,
 * ordered by deletedAt descending (most recent first),
 * and fetches related Notes to avoid N+1 queries.
 *
 * @param User $user The user whose logs to retrieve.
 *
 * @return Logs[] Returns an array of Logs entities ordered by deletedAt descending.
 */
public function findAllLogsByUserOrderedDesc(User $user): array
{
    return $this->createQueryBuilder('l')
        ->innerJoin('l.note', 'n')
        ->addSelect('n') // Eager load notes to avoid N+1
        ->where('l.user = :user')
        ->setParameter('user', $user)
        ->orderBy('l.id', 'DESC') // DESC = descending order (latest first)
        ->getQuery()
        ->getResult();
}
}
