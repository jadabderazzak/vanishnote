<?php

namespace App\EventSubscriber;

use App\Entity\Logs;
use App\Entity\Notes;
use App\Entity\Payment;
use Doctrine\ORM\Events;
use App\Entity\Attachements;
use App\Entity\Subscriptions;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;

/**
 * Doctrine Event Subscriber that automatically generates a slug and sets
 * the creation date when a Subscriptions entity is persisted.
 */
#[AsDoctrineListener(event: Events::prePersist)]
class SlugGenerationSubscriber implements EventSubscriber
{
    /**
     * Returns the list of Doctrine events this subscriber listens to.
     *
     * @return array<string>
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
        ];
    }

    /**
     * Triggered before an entity is persisted to the database.
     *
     * @param PrePersistEventArgs $args Event arguments
     */
    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->handleEntity($args);
    }

    /**
     * Handles the logic for initializing entity data before persisting.
     * Specifically applies to Subscriptions entities.
     *
     * @param PrePersistEventArgs $args
     */
    private function handleEntity(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Subscriptions) {
            // Generate a slug if not already set
            if (!$entity->getSlug()) {
                $entity->generateRandomSlug();
            }

            // Set createdAt timestamp if not already set
            if (!$entity->getCreatedAt()) {
                $entity->setCreatedAt(new \DateTime());
            }
        }

        if ($entity instanceof Payment) {
            // Generate a slug if not already set
            if (!$entity->getSlug()) {
                $entity->generateRandomSlug();
            }

            // Set createdAt timestamp if not already set
            if (!$entity->getCreatedAt()) {
                $entity->setCreatedAt(new \DateTime());
            }
        }

        if ($entity instanceof Notes) {
            // Generate a slug if not already set
            if (!$entity->getSlug()) {
                $entity->generateRandomSlug();
            }

            // Set createdAt timestamp if not already set
            if (!$entity->getCreatedAt()) {
                $entity->setCreatedAt(new \DateTime());
            }
        }

         if ($entity instanceof Attachements) {
            // Generate a slug if not already set
            if (!$entity->getSlug()) {
                $entity->generateRandomSlug();
            }
        }

        if ($entity instanceof Logs) {
            // Generate a slug if not already set
            if (!$entity->getSlug()) {
                $entity->generateRandomSlug();
            }
        }
    }
}
