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


}
