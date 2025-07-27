<?php

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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

}
