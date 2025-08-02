<?php
namespace App\Service;

use App\Entity\SystemLog;
use Doctrine\ORM\EntityManagerInterface;

/**
 * This service handles persistent system-level logging.
 * It stores log entries in the database using the SystemLog entity.
 */
class SystemLoggerService
{
    private EntityManagerInterface $entityManager;

    /**
     * Constructor.
     *
     * @param EntityManagerInterface $entityManager Doctrine's EntityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Logs a message into the system log table.
     *
     * @param int $level A log level constant (e.g., 100 for DEBUG, 200 for INFO, 300 for WARNING, 400 for ERROR, etc.)
     * @param string $message The log message to store
     * @param bool $isHandled Whether the issue is already handled (default: false)
     *
     * @return void
     */
    public function log(int $level, string $message, bool $isHandled = false): void
    {
        $log = new SystemLog();
        $log->setLevel($level);
        $log->setMessage($message);
        $log->setLoggedAt(new \DateTime());
        $log->setIsHandled($isHandled);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
