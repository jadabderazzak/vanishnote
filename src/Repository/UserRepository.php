<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

   /**
 * Returns the number of users registered in the current week (last 7 days),
 * excluding admins (users having "ROLE_ADMIN"),
 * and the percentage gain compared to the previous week.
 *
 * @return array{
 *     currentWeekCount: int,
 *     percentageGain: float|null
 * }
 */
public function getWeeklyUserRegistrationsAndPercentageGain(): array
{
    $now = new \DateTime();
    $now->setTime(23, 59, 59);

    $currentWeekStart = (clone $now)->modify('-6 days')->setTime(0, 0, 0);
    $currentWeekEnd = (clone $now);

    $previousWeekStart = (clone $currentWeekStart)->modify('-7 days');
    $previousWeekEnd = (clone $currentWeekStart)->modify('-1 second');

    $countNonAdminUsers = function ($start, $end) {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt BETWEEN :start AND :end')
            ->andWhere('u.roles NOT LIKE :role')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getSingleScalarResult();
    };

    $currentWeekCount = (int) $countNonAdminUsers($currentWeekStart, $currentWeekEnd);
    $previousWeekCount = (int) $countNonAdminUsers($previousWeekStart, $previousWeekEnd);

    if ($previousWeekCount === 0) {
        $percentageGain = null;
    } else {
        $percentageGain = (($currentWeekCount - $previousWeekCount) / $previousWeekCount) * 100;
    }

    return [
        'currentWeekCount' => $currentWeekCount,
        'percentageGain' => $percentageGain,
    ];
}

/**
 * Returns the number of users registered in the current month,
 * excluding admins (users having "ROLE_ADMIN"),
 * and the percentage gain compared to the previous month.
 *
 * @return array{
 *     currentMonthCount: int,
 *     percentageGain: float|null
 * }
 */
public function getMonthlyUserRegistrationsAndPercentageGain(): array
{
    $now = new \DateTime();
    $now->setTime(23, 59, 59);

    // First day of current month, 00:00:00
    $currentMonthStart = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
    // End of current day (today 23:59:59)
    $currentMonthEnd = (clone $now);

    // Previous month start (first day of previous month 00:00:00)
    $previousMonthStart = (clone $currentMonthStart)->modify('first day of previous month');
    // Previous month end (last day of previous month 23:59:59)
    $previousMonthEnd = (clone $currentMonthStart)->modify('last day of previous month')->setTime(23, 59, 59);

    $countNonAdminUsers = function ($start, $end) {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt BETWEEN :start AND :end')
            ->andWhere('u.roles NOT LIKE :role')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getSingleScalarResult();
    };

    $currentMonthCount = (int) $countNonAdminUsers($currentMonthStart, $currentMonthEnd);
    $previousMonthCount = (int) $countNonAdminUsers($previousMonthStart, $previousMonthEnd);

    if ($previousMonthCount === 0) {
        $percentageGain = null;
    } else {
        $percentageGain = (($currentMonthCount - $previousMonthCount) / $previousMonthCount) * 100;
    }

    return [
        'currentMonthCount' => $currentMonthCount,
        'percentageGain' => $percentageGain,
    ];
}

}
