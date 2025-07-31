<?php

namespace App\Message;

use App\Entity\AdminEntreprise;

final class sendReadNote
{
   
    public function __construct(
        public readonly ?string $email,
        public readonly string $ipadress,
        public readonly string $userAgent,
        public readonly string $nameClient,
        public readonly string $noteTitle,
        public readonly AdminEntreprise $entreprise
    ) {
    }

   
}
