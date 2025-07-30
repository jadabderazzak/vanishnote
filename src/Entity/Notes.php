<?php

namespace App\Entity;

use App\Repository\NotesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: NotesRepository::class)]
class Notes
{
    /**
     * @var int|null
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * The user who owns the note.
     * 
     * @var User|null
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "User is required.")]
    private ?User $user = null;

    /**
     * Title of the note.
     * 
     * @var string|null
     */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Title cannot be blank.")]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: "Title must be at least {{ limit }} characters long.",
        maxMessage: "Title cannot be longer than {{ limit }} characters."
    )]
    private ?string $title = null;

    /**
     * Content of the note.
     * 
     * @var string|null
     */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Content cannot be blank.")]
    private ?string $content = null;

    /**
     * Creation datetime.
     * 
     * @var \DateTime|null
     */
    #[ORM\Column]
    #[Assert\NotNull(message: "Creation date is required.")]
    #[Assert\Type("\DateTimeInterface", message: "Creation date must be a valid datetime.")]
    private ?\DateTime $createdAt = null;

    /**
     * Last update datetime.
     * 
     * @var \DateTime|null
     */
    #[ORM\Column]
    #[Assert\NotNull(message: "Update date is required.")]
    #[Assert\Type("\DateTimeInterface", message: "Update date must be a valid datetime.")]
    private ?\DateTime $updatedAt = null;

    
    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    /**
     * Number of characters in the note.
     * 
     * @var int|null
     */
    #[ORM\Column]
    #[Assert\NotNull(message: "Character count is required.")]
    #[Assert\PositiveOrZero(message: "Character count must be zero or positive.")]
    private ?int $characterCount = null;

    /**
     * The subscription plan associated with the note.
     * 
     * @var SubscriptionPlan|null
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Subscription plan is required.")]
    private ?SubscriptionPlan $planType = null;

    /**
     * Encryption metadata string.
     * 
     * @var string|null
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Encryption metadata is required.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Encryption metadata cannot be longer than {{ limit }} characters."
    )]
    private ?string $encryptionMetadata = null;

    /**
     * Optional password for the note.
     * 
     * @var string|null
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: "Password cannot be longer than {{ limit }} characters."
    )]
    private ?string $password = null;

    /**
     * Date and time when the note was read.
     * 
     * @var \DateTime|null
     */
    #[ORM\Column(nullable: true)]
    #[Assert\Type("\DateTimeInterface", message: "ReadAt must be a valid datetime.")]
    private ?\DateTime $readAt = null;

    /**
     * Date and time when the note was deleted (soft delete).
     * 
     * @var \DateTime|null
     */
    #[ORM\Column(nullable: true)]
    #[Assert\Type("\DateTimeInterface", message: "DeletedAt must be a valid datetime.")]
    private ?\DateTime $deletedAt = null;

    /**
     * Attachments related to the note.
     * 
     * @var Collection<int, Attachements>
     */
    #[ORM\OneToMany(targetEntity: Attachements::class, mappedBy: 'note')]
    private Collection $attachements;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $expirationDate = null;

    #[ORM\Column(nullable: true)]
    private ?bool $burnAfterReading = null;

    #[ORM\Column(nullable: true)]
    private ?bool $burned = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $minutes = null;

    public function __construct()
    {
        $this->attachements = new ArrayCollection();
    }


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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedat(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedat(\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

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

    public function getCharacterCount(): ?int
    {
        return $this->characterCount;
    }

    public function setCharacterCount(int $characterCount): static
    {
        $this->characterCount = $characterCount;

        return $this;
    }

    public function getPlanType(): ?SubscriptionPlan
    {
        return $this->planType;
    }

    public function setPlanType(?SubscriptionPlan $planType): static
    {
        $this->planType = $planType;

        return $this;
    }

    public function getEncryptionMetadata(): ?string
    {
        return $this->encryptionMetadata;
    }

    public function setEncryptionMetadata(string $encryptionMetadata): static
    {
        $this->encryptionMetadata = $encryptionMetadata;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getReadAt(): ?\DateTime
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTime $readAt): static
    {
        $this->readAt = $readAt;

        return $this;
    }

    public function getDeletedAt(): ?\DateTime
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTime $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /**
     * @return Collection<int, Attachements>
     */
    public function getAttachements(): Collection
    {
        return $this->attachements;
    }

    public function addAttachement(Attachements $attachement): static
    {
        if (!$this->attachements->contains($attachement)) {
            $this->attachements->add($attachement);
            $attachement->setNote($this);
        }

        return $this;
    }

    public function removeAttachement(Attachements $attachement): static
    {
        if ($this->attachements->removeElement($attachement)) {
            // set the owning side to null (unless already changed)
            if ($attachement->getNote() === $this) {
                $attachement->setNote(null);
            }
        }

        return $this;
    }

    public function generateRandomSlug(): void
    {
        $this->slug = Uuid::v4()->toRfc4122(); 
    }

    public function getExpirationDate(): ?\DateTime
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(?\DateTime $expirationDate): static
    {
        $this->expirationDate = $expirationDate;

        return $this;
    }

    public function isBurnAfterReading(): ?bool
    {
        return $this->burnAfterReading;
    }

    public function setBurnAfterReading(?bool $burnAfterReading): static
    {
        $this->burnAfterReading = $burnAfterReading;

        return $this;
    }

    public function isBurned(): ?bool
    {
        return $this->burned;
    }

    public function setBurned(?bool $burned): static
    {
        $this->burned = $burned;

        return $this;
    }

    public function getMinutes(): ?int
    {
        return $this->minutes;
    }

    public function setMinutes(?int $minutes): static
    {
        $this->minutes = $minutes;

        return $this;
    }
}
