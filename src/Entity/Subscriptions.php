<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\SubscriptionsRepository;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SubscriptionsRepository::class)]
class Subscriptions
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column]
    private ?\DateTime $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $endsAt = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $notesCreated = null;

    #[ORM\Column]
    private ?\DateTime $created_at = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?SubscriptionPlan $SubscriptionPlan = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStartedAt(): ?\DateTime
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTime $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTime
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTime $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getNotesCreated(): ?int
    {
        return $this->notesCreated;
    }

    public function setNotesCreated(int $notesCreated): static
    {
        $this->notesCreated = $notesCreated;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTime $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }
     public function generateRandomSlug(): void
    {
        $this->slug = Uuid::v4()->toRfc4122(); 
    }

     public function getSubscriptionPlan(): ?SubscriptionPlan
     {
         return $this->SubscriptionPlan;
     }

     public function setSubscriptionPlan(?SubscriptionPlan $SubscriptionPlan): static
     {
         $this->SubscriptionPlan = $SubscriptionPlan;

         return $this;
     }
}
