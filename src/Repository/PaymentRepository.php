<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Payment;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }
    
    /**
     * Returns the highest invoiceId from succeeded payments.
     *
     * This is used to determine the next invoice number in sequence (e.g., INV001, INV002...).
     *
     * @return int The highest invoiceId, or 0 if no succeeded payment exists.
     */
    public function findLastSucceededInvoiceId(): int
    {
        $result = $this->createQueryBuilder('p')
            ->select('MAX(p.invoiceId) as maxInvoiceId')
            ->where('p.status = :status')
            ->setParameter('status', 'succeeded')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result; // will return 0 if null
    }

    public function getTotalAmountPaidByUser(User $user): float
{
    $qb = $this->createQueryBuilder('p')
        ->select('SUM(p.amount)')
        ->where('p.user = :user')
        ->andWhere('p.status = :status')
        ->setParameter('user', $user)
        ->setParameter('status', 'succeeded');

    $result = $qb->getQuery()->getSingleScalarResult();

    return $result !== null ? (float) $result : 0.0;
}

    /**
     * Returns the last 5 payments ordered by creation date descending.
     *
     * @return Payment[] Returns an array of Payment objects
     */
    public function findLastFivePayments(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'succeeded')
            ->orderBy('p.createdAt', 'DESC')

            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    
    /**
     * Returns the last 5 payments ordered by creation date descending.
     *
     * @return Payment[] Returns an array of Payment objects
     */
    public function findLastFivePaymentsByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'succeeded')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')

            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }


    /**
 * Returns the total amount of payments for the current week (last 7 days) and the percentage gain
 * compared to the previous week (the 7 days before the current week).
 *
 * @return array{
 *     currentWeekAmount: float,
 *     percentageGain: float|null
 * }
 */
public function getWeeklyAmountAndPercentageGain(): array
{
    $entityManager = $this->getEntityManager();

    $now = new \DateTime();
    $now->setTime(23, 59, 59);

    // Current week range: last 7 days including today
    $currentWeekStart = (clone $now)->modify('-6 days')->setTime(0, 0, 0);
    $currentWeekEnd = (clone $now);

    // Previous week range: 7 days before current week
    $previousWeekStart = (clone $currentWeekStart)->modify('-7 days');
    $previousWeekEnd = (clone $currentWeekStart)->modify('-1 second');

    // Query to get sum of amount for current week
    $currentWeekAmount = $this->createQueryBuilder('p')
        ->select('COALESCE(SUM(p.amount), 0)')
        ->where('p.createdAt BETWEEN :currentWeekStart AND :currentWeekEnd')
        ->andWhere('p.status = :status')
        ->setParameter('currentWeekStart', $currentWeekStart)
        ->setParameter('currentWeekEnd', $currentWeekEnd)
        ->setParameter('status', 'succeeded')
        ->getQuery()
        ->getSingleScalarResult();

    // Query to get sum of amount for previous week
    $previousWeekAmount = $this->createQueryBuilder('p')
        ->select('COALESCE(SUM(p.amount), 0)')
        ->where('p.createdAt BETWEEN :previousWeekStart AND :previousWeekEnd')
        ->andWhere('p.status = :status')
        ->setParameter('previousWeekStart', $previousWeekStart)
        ->setParameter('previousWeekEnd', $previousWeekEnd)
        ->setParameter('status', 'succeeded')
        ->getQuery()
        ->getSingleScalarResult();

    $currentWeekAmount = (float) $currentWeekAmount;
    $previousWeekAmount = (float) $previousWeekAmount;

    if ($previousWeekAmount == 0) {
        // Avoid division by zero, if no amount last week, percentage gain is null
        $percentageGain = null;
    } else {
        $percentageGain = (($currentWeekAmount - $previousWeekAmount) / $previousWeekAmount) * 100;
    }

    return [
        'currentWeekAmount' => $currentWeekAmount,
        'percentageGain' => $percentageGain,
    ];
}


/**
 * Returns the total amount of payments for the current month and the percentage gain
 * compared to the previous month.
 *
 * @return array{
 *     currentMonthAmount: float,
 *     percentageGain: float|null
 * }
 */
public function getMonthlyAmountAndPercentageGain(): array
{
    $now = new \DateTime();
    $now->setTime(23, 59, 59);

    // Current month range: from 1st day of this month to now
    $currentMonthStart = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
    $currentMonthEnd = (clone $now);

    // Previous month range: entire previous month
    $previousMonthStart = (clone $currentMonthStart)->modify('first day of last month')->setTime(0, 0, 0);
    $previousMonthEnd = (clone $currentMonthStart)->modify('last day of last month')->setTime(23, 59, 59);

    // Sum of payments for current month
    $currentMonthAmount = $this->createQueryBuilder('p')
        ->select('COALESCE(SUM(p.amount), 0)')
        ->where('p.createdAt BETWEEN :currentMonthStart AND :currentMonthEnd')
        ->andWhere('p.status = :status')
        ->setParameter('currentMonthStart', $currentMonthStart)
        ->setParameter('currentMonthEnd', $currentMonthEnd)
        ->setParameter('status', 'succeeded')
        ->getQuery()
        ->getSingleScalarResult();

    // Sum of payments for previous month
    $previousMonthAmount = $this->createQueryBuilder('p')
        ->select('COALESCE(SUM(p.amount), 0)')
        ->where('p.createdAt BETWEEN :previousMonthStart AND :previousMonthEnd')
        ->andWhere('p.status = :status')
        ->setParameter('previousMonthStart', $previousMonthStart)
        ->setParameter('previousMonthEnd', $previousMonthEnd)
        ->setParameter('status', 'succeeded')
        ->getQuery()
        ->getSingleScalarResult();

    $currentMonthAmount = (float) $currentMonthAmount;
    $previousMonthAmount = (float) $previousMonthAmount;

    if ($previousMonthAmount == 0) {
        $percentageGain = null; // Avoid division by zero
    } else {
        $percentageGain = (($currentMonthAmount - $previousMonthAmount) / $previousMonthAmount) * 100;
    }

    return [
        'currentMonthAmount' => $currentMonthAmount,
        'percentageGain' => $percentageGain,
    ];
}


/**
 * Retrieves the total payment amounts for each month of a given year.
 *
 * This method queries all Payment records within the specified year and aggregates 
 * the total amount per calendar month. Months with no payments will have a total of 0.
 *
 * @param int $year The year for which to calculate monthly payment totals.
 *
 * @return array An associative array where the keys are month names
 *               ('January' to 'December') and the values are the total
 *               payment amounts for each corresponding month.
 *
 * Example:
 * [
 *     'January' => 1500.00,
 *     'February' => 0.00,
 *     'March' => 420.50,
 *     ...
 *     'December' => 89.99,
 * ]
 */
public function getMonthlyPaymentsByYear(int $year): array
{
   

    $payments = $this->createQueryBuilder('p')
    ->select('p.amount, p.createdAt')
    ->where('p.createdAt >= :start')
    ->andWhere('p.createdAt <= :end')
    ->andWhere('p.status = :status')
    ->setParameter('start', new \DateTime("$year-01-01 00:00:00"))
    ->setParameter('end', new \DateTime("$year-12-31 23:59:59"))
    ->setParameter('status', 'succeeded')
    ->getQuery()
    ->getResult();


    $months = [
        'January' => 0,
        'February' => 0,
        'March' => 0,
        'April' => 0,
        'May' => 0,
        'June' => 0,
        'July' => 0,
        'August' => 0,
        'September' => 0,
        'October' => 0,
        'November' => 0,
        'December' => 0,
    ];

    foreach ($payments as $payment) {
        $monthIndex = (int)$payment['createdAt']->format('n') - 1;
        $monthName = array_keys($months)[$monthIndex];
        $months[$monthName] += $payment['amount'];
    }

    return $months;
}

    /**
     * Returns the total revenue (chiffre d'affaires global)
     *
     * @return float
     */
    public function getTotalRevenue(): float
    {
        $qb = $this->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->where('p.status = :status')
            ->setParameter('status', 'succeeded'); // ou 'paid', selon ta logique

        return (float) $qb->getQuery()->getSingleScalarResult();
    }
}
