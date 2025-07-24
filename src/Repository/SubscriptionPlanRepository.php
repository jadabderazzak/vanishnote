<?php

namespace App\Repository;

use App\Entity\SubscriptionPlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubscriptionPlan>
 */
class SubscriptionPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubscriptionPlan::class);
    }

    /**
     * Finds one SubscriptionPlan entity by its name, ignoring case sensitivity.
     *
     * This method performs a case-insensitive search on the 'name' field
     * by converting both the stored value and the input parameter to lowercase.
     *
     * @param string $name The subscription plan name to search for.
     *
     * @return SubscriptionPlan|null Returns the SubscriptionPlan entity if found, or null if not.
     */
    public function findOneByNameCaseInsensitive(string $name): ?SubscriptionPlan
    {
        return $this->createQueryBuilder('sp')
            ->where('LOWER(sp.name) = LOWER(:name)')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

}
