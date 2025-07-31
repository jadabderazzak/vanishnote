<?php

namespace App\MessageHandler;

use App\Message\sendReadNote;
use App\Repository\AdminEntrepriseRepository;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;


#[AsMessageHandler]
final class sendReadHandler
{
    public function __construct(private readonly MailerInterface $mailer,private readonly AdminEntrepriseRepository $repoEntreprise)
    {
        
    }
    public function __invoke(sendReadNote $message): void
    {
        $entreprise = $this->repoEntreprise->findOneBy(['id' => 1]);
        $noreply = $entreprise->getNoReplyEmail() ?? 'no-replay@yourentreprise.com';
        $entrepriseName = $entreprise->getCompanyName();
        $supportEmail = $entreprise->getSupportEmail();
        $email = (new TemplatedEmail())
        ->from(new Address($noreply, $entrepriseName))
        ->to($message->email)
        ->subject("Someone has opened your note.")
        ->htmlTemplate('emails/readedNote.html.twig')
        ->context([
            'companyName' => $entrepriseName,
            'clientName' => $message->nameClient,
            'ipAdress' => $message->ipadress,
            'userAgent' => $message->userAgent,
            'supportEmail' => $supportEmail,
            'noteTitle' => $message->noteTitle
      
      
        ]);

        $this->mailer->send($email);
    }
}
