<?php

namespace App\Entity;

use App\Repository\LogsIpsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LogsIpsRepository::class)]
class LogsIps
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;



    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ipAdress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\ManyToOne(inversedBy: 'logsIps')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Logs $log = null;


    public function getId(): ?int
    {
        return $this->id;
    }

   

    public function getIpAdress(): ?string
    {
        return $this->ipAdress;
    }

    public function setIpAdress(?string $ipAdress): static
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

    public function getLog(): ?Logs
    {
        return $this->log;
    }

    public function setLog(?Logs $log): static
    {
        $this->log = $log;

        return $this;
    }

}
