<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * SupportType creates the support form for clients to contact support.
 * Fields include name, email, subject, message, and recipient.
 */
class SupportType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var string[] $availableEmails */
        $availableEmails = $options['available_emails'];

        $builder
            ->add('nameClient', TextType::class, [
                'label' => $this->translator->trans('Your name'),
                'attr' => [
                    'placeholder' => $this->translator->trans('Enter your full name'),
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => $this->translator->trans('Your name is required.'),
                    ]),
                    new Assert\Length([
                        'max' => 100,
                        'maxMessage' => $this->translator->trans('Your name cannot exceed {{ limit }} characters.'),
                    ]),
                ],
            ])
           ->add('emailClient', EmailType::class, [
                'label' => $this->translator->trans('Your email'),
                'attr' => ['placeholder' => $this->translator->trans('Enter your email address')],
                'constraints' => [
                    new Assert\NotBlank(['message' => $this->translator->trans('Your email is required.')]),
                    new Assert\Email(['message' => $this->translator->trans('Please enter a valid email address.')]),
                ],
                
            ])
           
            ->add('title', TextType::class, [
                'label' => $this->translator->trans('Subject'),
                'attr' => [
                    'placeholder' => $this->translator->trans('Enter the subject of your message'),
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => $this->translator->trans('Subject is required.'),
                    ]),
                    new Assert\Length([
                        'max' => 150,
                        'maxMessage' => $this->translator->trans('Subject cannot exceed {{ limit }} characters.'),
                    ]),
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => $this->translator->trans('Your message'),
                'attr' => [
                    'rows' => 6,
                    'placeholder' => $this->translator->trans('Describe your request or issue in detail'),
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => $this->translator->trans('Message is required.'),
                    ]),
                    new Assert\Length([
                        'min' => 10,
                        'minMessage' => $this->translator->trans('Your message must be at least {{ limit }} characters.'),
                        'max' => 2000,
                        'maxMessage' => $this->translator->trans('Your message cannot exceed {{ limit }} characters.'),
                    ]),
                ],
            ])
            ->add('email', ChoiceType::class, [
                'label' => $this->translator->trans('Send to'),
                'placeholder' => $this->translator->trans('Select a recipient'),
                'choices' => array_combine($availableEmails, $availableEmails),
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => $this->translator->trans('Please choose a recipient.'),
                    ]),
                    new Assert\Email([
                        'message' => $this->translator->trans('Recipient email is not valid.'),
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // We’re not binding to a class directly since we’re using a message DTO
            'data_class' => null,
            'available_emails' => [], // passed from controller
        ]);

        $resolver->setAllowedTypes('available_emails', ['array']);
    }
}
