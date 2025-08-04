<?php

namespace App\Entity;

use App\Repository\SecureFilesRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SecureFilesRepository::class)]
class SecureFiles
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Filename should not be blank.")]
    #[Assert\Length(max: 255)]
    private ?string $filename = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Filepath should not be blank.")]
    #[Assert\Length(max: 255)]
    private ?string $filepath = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Mime type should not be blank.")]
    #[Assert\Length(max: 255)]
    private ?string $mimeType = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "Size must be provided.")]
    #[Assert\Positive(message: "Size must be a positive integer.")]
    private ?int $size = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "Upload date must be provided.")]
    private ?\DateTime $uploadedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $encryptionMetadata = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $aad = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function generateRandomSlug(): void
    {
        $this->slug = Uuid::v4()->toRfc4122(); 
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

    public function getAad(): ?string
    {
        return $this->aad;
    }

    public function setAad(string $aad): static
    {
        $this->aad = $aad;

        return $this;
    }
}
