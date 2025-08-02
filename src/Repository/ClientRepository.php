<?php

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Persistence\ManagerRegistry;
use phpDocumentor\Reflection\Types\Boolean;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

/**
 * Retrieves all clients along with their associated user,
 * the most recent active subscription of that user,
 * and the related subscription plan.
 *
 * This method performs a left join on the user's subscriptions,
 * filtering to include only the latest active subscription
 * (based on the maximum `startedAt` date).
 *
 * @return array Returns an array of Client entities with
 *               their last active subscription and subscription plan eagerly loaded.
 */
public function findAllWithLastActiveSubscription(): array
{
    $qb = $this->createQueryBuilder('c')
        ->leftJoin('c.user', 'u')
        ->addSelect('u')
        // Join with active subscriptions - latest by startedAt per user
        ->leftJoin(
            'u.subscriptions',
            's',
            'WITH',
            's.status = true AND s.startedAt = (
                SELECT MAX(s2.startedAt) 
                FROM App\Entity\Subscriptions s2 
                WHERE s2.user = u.id AND s2.status = true
            )'
        )
        ->addSelect('s')
        ->leftJoin('s.SubscriptionPlan', 'p')
        ->addSelect('p')
        ->orderBy('c.id', 'DESC');

    return $qb->getQuery()->getResult();
}




/**
 * Finds all clients filtered by their company status and fetches their last active subscription.
 *
 * This method retrieves clients joined with their associated user, 
 * the most recent active subscription of that user (based on startedAt), 
 * and the related subscription plan.
 *
 * @param bool $value Indicates whether to filter clients that are companies (true) or individuals (false).
 *
 * @return array Returns an array of Client entities with their last active subscription and plan eagerly loaded.
 */
public function findAllWithLastActiveSubscriptionByType(bool $value): array
{
    $qb = $this->createQueryBuilder('c')
        ->leftJoin('c.user', 'u')
        ->addSelect('u')
        // Join with active subscriptions - latest by startedAt per user
        ->leftJoin(
            'u.subscriptions',
            's',
            'WITH',
            's.status = true AND s.startedAt = (
                SELECT MAX(s2.startedAt) 
                FROM App\Entity\Subscriptions s2 
                WHERE s2.user = u.id AND s2.status = true
            )'
        )
        ->addSelect('s')
        ->leftJoin('s.SubscriptionPlan', 'p')
        ->addSelect('p')
        ->andWhere('c.isCompany = :value')
        ->setParameter('value', $value)
        ->orderBy('c.id', 'DESC');

    return $qb->getQuery()->getResult();
}


}
