<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ClientRepository;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[Assert\Callback('validateCompanyFields')]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Name is required.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Name cannot be longer than {{ limit }} characters."
    )]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: "Company name cannot be longer than {{ limit }} characters."
    )]
    private ?string $company = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "The isCompany status is required.")]
    private ?bool $isCompany = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: "Company address cannot be longer than {{ limit }} characters."
    )]
    private ?string $companyAdress = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: "VAT number cannot be longer than {{ limit }} characters."
    )]
    private ?string $vatNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 20,
        maxMessage: "Phone number cannot be longer than {{ limit }} characters."
    )]
    #[Assert\Regex(
        pattern: '/^\+?[0-9\s\-]{7,20}$/',
        message: "Phone number is not valid."
    )]
    private ?string $phone = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "Number of created notes is required.")]
    #[Assert\PositiveOrZero(message: "Number of created notes must be zero or positive.")]
    private ?int $numberNotesCreated = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Slug(fields:["name"])]
    private ?string $slug = null;

    #[ORM\ManyToOne]
    private ?Country $country = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function isCompany(): ?bool
    {
        return $this->isCompany;
    }

    public function setIsCompany(bool $isCompany): static
    {
        $this->isCompany = $isCompany;

        return $this;
    }

    public function getCompanyAdress(): ?string
    {
        return $this->companyAdress;
    }

    public function setCompanyAdress(?string $companyAdress): static
    {
        $this->companyAdress = $companyAdress;

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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getNumberNotesCreated(): ?int
    {
        return $this->numberNotesCreated;
    }

    public function setNumberNotesCreated(int $numberNotesCreated): static
    {
        $this->numberNotesCreated = $numberNotesCreated;

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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

     // ✅ Conditional company validation logic
    public function validateCompanyFields(ExecutionContextInterface $context): void
    {
        if ($this->isCompany) {
            if (empty($this->company)) {
                $context->buildViolation('Company name is required when client is a company.')
                    ->atPath('company')
                    ->addViolation();
            }

            if (empty($this->companyAdress)) {
                $context->buildViolation('Company address is required when client is a company.')
                    ->atPath('companyAdress')
                    ->addViolation();
            }

            if (empty($this->vatNumber)) {
                $context->buildViolation('VAT number is required when client is a company.')
                    ->atPath('vatNumber')
                    ->addViolation();
            }
        }
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): static
    {
        $this->country = $country;

        return $this;
    }
}
