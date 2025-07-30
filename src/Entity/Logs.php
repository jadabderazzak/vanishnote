<?php

namespace App\Entity;

use App\Repository\LogsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Uid\Uuid;


#[ORM\Entity(repositoryClass: LogsRepository::class)]
class Logs
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Related note.
     * 
     * @var Notes|null
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Note is required.")]
    private ?Notes $note = null;

    /**
     * Related user.
     * 
     * @var User|null
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "User is required.")]
    private ?User $user = null;

    /**
     * Deletion date and time (soft delete).
     * 
     * @var \DateTime|null
     */
    #[ORM\Column(nullable: true)]
    #[Assert\NotNull(message: "DeletedAt datetime is required.")]
    #[Assert\Type("\DateTimeInterface", message: "DeletedAt must be a valid datetime.")]
    private ?\DateTime $deletedAt = null;

    /**
     * IP address of the user.
     * 
     * @var string|null
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "IP Address cannot be blank.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "IP Address cannot be longer than {{ limit }} characters."
    )]
    private ?string $ipAdress = null;

    /**
     * User agent string (optional).
     * 
     * @var string|null
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: "User Agent cannot be longer than {{ limit }} characters."
    )]
    private ?string $userAgent = null;

    /**
     * Additional data in text format (optional).
     * 
     * @var string|null
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $additionnalData = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    /**
     * @var Collection<int, LogsIps>
     */
    #[ORM\OneToMany(targetEntity: LogsIps::class, mappedBy: 'log')]
    private Collection $logsIps;

    public function __construct()
    {
        $this->logsIps = new ArrayCollection();
    }



    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNote(): ?Notes
    {
        return $this->note;
    }

    public function setNote(?Notes $note): static
    {
        $this->note = $note;

        return $this;
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

    public function getDeletedAt(): ?\DateTime
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(\DateTime $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getIpAdress(): ?string
    {
        return $this->ipAdress;
    }

    public function setIpAdress(string $ipAdress): static
    {
        $this->ipAdress = $ipAdress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getAdditionnalData(): ?string
    {
        return $this->additionnalData;
    }

    public function setAdditionnalData(?string $additionnalData): static
    {
        $this->additionnalData = $additionnalData;

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

     /**
      * @return Collection<int, LogsIps>
      */
     public function getLogsIps(): Collection
     {
         return $this->logsIps;
     }

     public function addLogsIp(LogsIps $logsIp): static
     {
         if (!$this->logsIps->contains($logsIp)) {
             $this->logsIps->add($logsIp);
             $logsIp->setLog($this);
         }

         return $this;
     }

     public function removeLogsIp(LogsIps $logsIp): static
     {
         if ($this->logsIps->removeElement($logsIp)) {
             // set the owning side to null (unless already changed)
             if ($logsIp->getLog() === $this) {
                 $logsIp->setLog(null);
             }
         }

         return $this;
     }

  
}
