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



/**
 * Retrieves monthly statistics of notes created in the past year.
 *
 * This method fetches all notes created within the last 12 months,
 * groups them by month (formatted as "YYYY-MM"), and counts the number
 * of notes created and the number of notes marked as "burned" for each month.
 *
 * @return array<int, array<string, mixed>> Returns an indexed array of monthly stats.
 * Each element is an associative array containing:
 *   - 'month' (string): The year and month in "YYYY-MM" format.
 *   - 'created' (int): The total number of notes created in that month.
 *   - 'burned' (int): The total number of notes marked as burned in that month.
 */
public function getMonthlyStats(User $user): array
{
    $results = $this->createQueryBuilder('n')
        ->select([
            'n.id',
            'n.createdAt',
            'n.burned'
        ])
        ->where('n.createdAt >= :startDate')
        ->andWhere('n.user = :user')
        ->setParameter('user', $user)
        ->setParameter('startDate', new \DateTime('-1 year'))
        ->orderBy('n.createdAt', 'ASC')
        ->getQuery()
        ->getResult();

    $stats = [];
    foreach ($results as $note) {
        $month = $note['createdAt']->format('Y-m');
        if (!isset($stats[$month])) {
            $stats[$month] = ['month' => $month, 'created' => 0, 'burned' => 0];
        }
        $stats[$month]['created']++;
        if ($note['burned']) {
            $stats[$month]['burned']++;
        }
    }

    return array_values($stats);
}


/**
 * Calculates the percentage growth in note creation between the last two months for a given user.
 *
 * This function retrieves the number of notes created by the specified user in each of the last two full months,
 * and returns the percentage growth from the previous month to the current one.
 *
 * Example: If 50 notes were created in June and 75 in July, returns 50.0.
 *
 * @param User $user The user for whom the growth is calculated.
 * @return float|null The growth percentage, or null if insufficient data or division by zero.
 */
public function getMonthlyGrowthRate(User $user): ?float
{
    $results = $this->createQueryBuilder('n')
        ->select('n.createdAt')
        ->where('n.createdAt >= :startDate')
        ->andWhere('n.user = :user')
        ->setParameter('startDate', new \DateTime('-2 months'))
        ->setParameter('user', $user)
        ->orderBy('n.createdAt', 'ASC')
        ->getQuery()
        ->getResult();

    $monthlyCounts = [];

    foreach ($results as $row) {
        $month = $row['createdAt']->format('Y-m');
        if (!isset($monthlyCounts[$month])) {
            $monthlyCounts[$month] = 0;
        }
        $monthlyCounts[$month]++;
    }

    $months = array_keys($monthlyCounts);
    $count = count($months);

    if ($count < 2) {
        return null; // Pas assez de données
    }

    $previousMonth = $months[$count - 2];
    $currentMonth = $months[$count - 1];

    $previousCount = $monthlyCounts[$previousMonth];
    $currentCount = $monthlyCounts[$currentMonth];

    if ($previousCount === 0) {
        return null; // Pas de division par zéro
    }

    $growth = (($currentCount - $previousCount) / $previousCount) * 100;

    return round($growth, 2);
}


/**
 * Counts the number of notes burned by the specified user during the current month.
 *
 * This method counts notes where the 'burned' flag is true and the deletion date
 * falls within the current calendar month.
 *
 * @param User $user The user whose burned notes count is being calculated.
 *
 * @return int The total number of burned notes for the current month.
 */
public function countBurnedNotesThisMonthByUser(User $user): int
{
    $now = new \DateTimeImmutable();
    $year = (int) $now->format('Y');
    $month = (int) $now->format('m');

    $startDate = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
    $endDate = $startDate->modify('first day of next month');

    return (int) $this->createQueryBuilder('n')
        ->select('COUNT(n.id)')
        ->where('n.user = :user')
        ->andWhere('n.burned = :burned')
        ->andWhere('n.deletedAt >= :start')
        ->andWhere('n.deletedAt < :end')
        ->setParameter('user', $user)
        ->setParameter('burned', true)
        ->setParameter('start', $startDate)
        ->setParameter('end', $endDate)
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * Counts the total number of notes created by the specified user.
 *
 * @param User $user The user whose notes are being counted.
 * @return int Total count of notes created by the user.
 */
public function countTotalNotesByUser(User $user): int
{
    return (int) $this->createQueryBuilder('n')
        ->select('COUNT(n.id)')
        ->where('n.user = :user')
        ->setParameter('user', $user)
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * Counts the total number of notes burned by the specified user.
 *
 * @param User $user The user whose burned notes are being counted.
 * @return int Total count of burned notes by the user.
 */
public function countTotalBurnedNotesByUser(User $user): int
{
    return (int) $this->createQueryBuilder('n')
        ->select('COUNT(n.id)')
        ->where('n.user = :user')
        ->andWhere('n.burned = :burned')
        ->setParameter('user', $user)
        ->setParameter('burned', true)
        ->getQuery()
        ->getSingleScalarResult();
}

 /**
     * Finds the 5 most recent notes that have not been burned yet.
     *
     * This method returns notes ordered by their creation date descending,
     * filtered to only include notes where 'burned' is false or null.
     *
     * @return Notes[] Returns an array of Notes entities
     */
    public function findLast5NotBurned(User $user): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.burned = false OR n.burned IS NULL')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }


    /**
     * Returns a list of notes that are not marked as burned.
     *
     * A note is considered "not burned" if the 'burned' field is either NULL or false.
     *
     * @return Notes[] Returns an array of non-burned Notes entities
     */
    public function findNotBurnedNotes(): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.burned IS NULL OR n.burned != :burned')
            ->setParameter('burned', true)
            ->orderBy('n.id', 'DESC')
            ->getQuery()
            ->getResult();
    }


}
