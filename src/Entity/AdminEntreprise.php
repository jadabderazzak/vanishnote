<?php

namespace App\Entity;

use App\Repository\AdminEntrepriseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AdminEntrepriseRepository::class)]
class AdminEntreprise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Company name should not be blank.")]
    #[Assert\Length(max: 255)]
    private ?string $companyName = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Company address should not be blank.")]
    private ?string $companyAddress = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Company email should not be blank.")]
    #[Assert\Email(message: "Please provide a valid email address.")]
    #[Assert\Length(max: 255)]
    private ?string $companyEmail = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "TVA applicable field must be set.")]
    private ?bool $tvaApplicable = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Default currency should not be blank.")]
    #[Assert\Length(max: 255)]
    private ?string $defaultCurrency = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $vatNumber = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotNull(message: "TVA rate must be set.")]
    #[Assert\PositiveOrZero(message: "TVA rate must be zero or positive.")]
    private ?int $tvaRate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $logoPath = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $invoicePrefix = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $companyPhone = null;

    #[ORM\Column(nullable: true)]
    private ?bool $showLogoOnInvoice = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): static
    {
        $this->companyName = $companyName;

        return $this;
    }

    public function getCompanyAddress(): ?string
    {
        return $this->companyAddress;
    }

    public function setCompanyAddress(string $companyAddress): static
    {
        $this->companyAddress = $companyAddress;

        return $this;
    }

    public function getCompanyEmail(): ?string
    {
        return $this->companyEmail;
    }

    public function setCompanyEmail(string $companyEmail): static
    {
        $this->companyEmail = $companyEmail;

        return $this;
    }

    public function isTvaApplicable(): ?bool
    {
        return $this->tvaApplicable;
    }

    public function setTvaApplicable(bool $tvaApplicable): static
    {
        $this->tvaApplicable = $tvaApplicable;

        return $this;
    }

    public function getDefaultCurrency(): ?string
    {
        return $this->defaultCurrency;
    }

    public function setDefaultCurrency(string $defaultCurrency): static
    {
        $this->defaultCurrency = $defaultCurrency;

        return $this;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): static
    {
        $this->vatNumber = $vatNumber;

        return $this;
    }

    public function getTvaRate(): ?int
    {
        return $this->tvaRate;
    }

    public function setTvaRate(int $tvaRate): static
    {
        $this->tvaRate = $tvaRate;

        return $this;
    }

    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function setLogoPath(?string $logoPath): static
    {
        $this->logoPath = $logoPath;

        return $this;
    }

    public function getInvoicePrefix(): ?string
    {
        return $this->invoicePrefix;
    }

    public function setInvoicePrefix(?string $invoicePrefix): static
    {
        $this->invoicePrefix = $invoicePrefix;

        return $this;
    }

    public function getCompanyPhone(): ?string
    {
        return $this->companyPhone;
    }

    public function setCompanyPhone(?string $companyPhone): static
    {
        $this->companyPhone = $companyPhone;

        return $this;
    }

    public function isShowLogoOnInvoice(): ?bool
    {
        return $this->showLogoOnInvoice;
    }

    public function setShowLogoOnInvoice(?bool $showLogoOnInvoice): static
    {
        $this->showLogoOnInvoice = $showLogoOnInvoice;

        return $this;
    }
}
