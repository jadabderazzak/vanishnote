<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Notes;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Notes>
 */
class NotesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notes::class);
    }

    /**
     * Gets monthly note stats for a user with proper JOIN to avoid N+1.
     * 
     * Uses LEFT JOIN to fetch all required data in a single query while
     * maintaining your exact business logic and return format.
     *
     * @param User $user
     * @return array[] Format: [ ['year'=>2025, 'month'=>1, 'count'=>5], ... ]
     */
    public function getMonthlyActiveNotesByUser(User $user): array
    {
        $results = $this->createQueryBuilder('n')
            
            //->andWhere('n.deletedAt IS NULL')
           ->select('n.createdAt, u.id')
            ->leftJoin('n.user', 'u')  // Explicit join
            ->where('u.id = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getArrayResult();

        $stats = [];
        foreach ($results as $note) {
            $date = $note['createdAt'] ?? null;
            if (!$date instanceof \DateTimeInterface) {
                continue;
            }

            $year = (int)$date->format('Y');
            $month = (int)$date->format('m');
            $key = $year . '-' . $month;

            if (!isset($stats[$key])) {
                $stats[$key] = [
                    'year' => $year,
                    'month' => $month,
                    'count' => 0
                ];
            }

            $stats[$key]['count']++;
        }

        // Sort by year and month (descending)
        usort($stats, function ($a, $b) {
            return $b['year'] <=> $a['year'] ?: $b['month'] <=> $a['month'];
        });

        return array_values($stats);
    }

    /**
 * Counts the number of notes created by a user for a specific month and year.
 *
 * This method avoids SQL functions in WHERE clauses for maximum portability.
 *
 * @param User $user The user whose notes to count.
 * @param int $year The year to filter (e.g., 2025).
 * @param int $month The month to filter (1 to 12).
 * @return int The number of notes created by the user in that month.
 */
public function countUserNotesByMonthAndYear(User $user, int $year, int $month): int
{
    $startDate = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
    $endDate = $startDate->modify('first day of next month');

    return (int) $this->createQueryBuilder('n')
        ->select('COUNT(n.id)')
        ->where('n.user = :user')
        ->andWhere('n.createdAt >= :start')
        ->andWhere('n.createdAt < :end')
        ->setParameter('user', $user)
        ->setParameter('start', $startDate)
        ->setParameter('end', $endDate)
        ->getQuery()
        ->getSingleScalarResult();
}

}
