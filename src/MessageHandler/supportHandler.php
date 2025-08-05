<?php

namespace App\MessageHandler;

use App\Message\supportMessage;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use App\Repository\AdminEntrepriseRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;


#[AsMessageHandler]
final class supportHandler
{
    public function __construct(private readonly MailerInterface $mailer,private readonly AdminEntrepriseRepository $repoEntreprise)
    {
        
    }
    public function __invoke(supportMessage $message): void
    {
        $entreprise = $this->repoEntreprise->findOneBy(['id' => 1]);
        $noreply = $entreprise->getNoReplyEmail() ?? 'no-replay@yourentreprise.com';
        $entrepriseName = $entreprise->getCompanyName();
        $supportEmail = $entreprise->getSupportEmail();
        $email = (new TemplatedEmail())
        ->from(new Address($noreply, $entrepriseName))
        ->to($message->email)
        ->subject("New Support Message from : ". $message->nameClient)
        ->htmlTemplate('emails/support.html.twig')
        ->context([
            'companyName' => $entrepriseName,
            'clientName' => $message->nameClient,
            'emailClient' => $message->emailClient,
            'message' => $message->message,
            'supportEmail' => $supportEmail,
            'title' => $message->title
      
      
        ]);

        $this->mailer->send($email);
    }
}
