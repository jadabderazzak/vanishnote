<?php

namespace App\Message;

use App\Entity\AdminEntreprise;

final class supportMessage
{
   
    public function __construct(
        public readonly string $emailClient,
        public readonly ?string $entrepriseClient,
        public readonly string $email, // Email configured in the admin section, selected in the form. 
        public readonly string $message,
        public readonly string $nameClient,
        public readonly string $title,
        public readonly AdminEntreprise $entreprise
 
    ) {
    }

   
}
