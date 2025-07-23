<?php

namespace App\Entity;

use App\Repository\ApiCredentialRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ApiCredentialRepository::class)]
class ApiCredential
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'API key is required')]
    #[Assert\Length(
        min: 30,
        max: 255,
        minMessage: 'The API key is too short (minimum {{ limit }} characters).',
        maxMessage: 'The API key is too long (maximum {{ limit }} characters).'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_\-\=]+$/',
        message: 'The API key contains invalid characters.'
    )]
    private ?string $secretKeyEncrypted = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        min: 30,
        max: 255,
        minMessage: 'The public key is too short (minimum {{ limit }} characters).',
        maxMessage: 'The public key is too long (maximum {{ limit }} characters).'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_\-\=]+$/',
        message: 'The public key contains invalid characters.'
    )]
    private ?string $publicKeyEncrypted = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Service name is required')]
    private ?string $service = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSecretKeyEncrypted(): ?string
    {
        return $this->secretKeyEncrypted;
    }

    public function setSecretKeyEncrypted(?string $secretKeyEncrypted): static
    {
        $this->secretKeyEncrypted = $secretKeyEncrypted;

        return $this;
    }

    public function getPublicKeyEncrypted(): ?string
    {
        return $this->publicKeyEncrypted;
    }

    public function setPublicKeyEncrypted(?string $publicKeyEncrypted): static
    {
        $this->publicKeyEncrypted = $publicKeyEncrypted;

        return $this;
    }

    public function getService(): ?string
    {
        return $this->service;
    }

    public function setService(string $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
