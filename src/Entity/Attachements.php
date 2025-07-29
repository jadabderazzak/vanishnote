<?php

namespace App\Entity;

use App\Repository\AttachementsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AttachementsRepository::class)]
class Attachements
{
    /**
     * @var int|null
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * The related note for this attachment.
     * 
     * @var Notes|null
     */
    #[ORM\ManyToOne(inversedBy: 'attachements')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Note is required.")]
    private ?Notes $note = null;

    /**
     * Filename of the attachment.
     * 
     * @var string|null
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Filename cannot be blank.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Filename cannot be longer than {{ limit }} characters."
    )]
    private ?string $filename = null;

    /**
     * File path where the attachment is stored.
     * 
     * @var string|null
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Filepath cannot be blank.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Filepath cannot be longer than {{ limit }} characters."
    )]
    private ?string $filepath = null;

    /**
     * Mime type of the file.
     * 
     * @var string|null
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Mime type cannot be blank.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Mime type cannot be longer than {{ limit }} characters."
    )]
    private ?string $mimeType = null;

    /**
     * Size of the file in bytes.
     * 
     * @var int|null
     */
    #[ORM\Column]
    #[Assert\NotNull(message: "Size is required.")]
    #[Assert\PositiveOrZero(message: "Size must be zero or positive.")]
    private ?int $size = null;

    /**
     * Upload datetime.
     * 
     * @var \DateTime|null
     */
    #[ORM\Column]
    #[Assert\NotNull(message: "UploadedAt is required.")]
    #[Assert\Type("\DateTimeInterface", message: "UploadedAt must be a valid datetime.")]
    private ?\DateTime $uploadedAt = null;

    /**
     * Optional encryption metadata.
     * 
     * @var string|null
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: "Encryption metadata cannot be longer than {{ limit }} characters."
    )]
    private ?string $encryptionMetadata = null;

   
    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    /**
     * Soft delete datetime.
     * 
     * @var \DateTime|null
     */
    #[ORM\Column(nullable: true)]
    #[Assert\Type("\DateTimeInterface", message: "DeletedAt must be a valid datetime.")]
    private ?\DateTime $deletedAt = null;

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

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getFilepath(): ?string
    {
        return $this->filepath;
    }

    public function setFilepath(string $filepath): static
    {
        $this->filepath = $filepath;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getUploadedAt(): ?\DateTime
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTime $uploadedAt): static
    {
        $this->uploadedAt = $uploadedAt;

        return $this;
    }

    public function getEncryptionMetadata(): ?string
    {
        return $this->encryptionMetadata;
    }

    public function setEncryptionMetadata(?string $encryptionMetadata): static
    {
        $this->encryptionMetadata = $encryptionMetadata;

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

    public function getDeletedAt(): ?\DateTime
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(\DateTime $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }
    public function generateRandomSlug(): void
    {
        $this->slug = Uuid::v4()->toRfc4122(); 
    }
}
