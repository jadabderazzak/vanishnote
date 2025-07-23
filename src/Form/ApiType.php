<?php

namespace App\Form;

use App\Entity\ApiCredential;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class ApiType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('secretKeyEncrypted', PasswordType::class, [
                'label' => $this->translator->trans('Secret Key'),
                'required' => false,
                'empty_data' => '',
                'attr' => ['autocomplete' => 'new-password'],
                'help' => $this->translator->trans('The secret key will be encrypted on save.'),
            ])
            ->add('publicKeyEncrypted', PasswordType::class, [
                'label' => $this->translator->trans('Public Key (optional)'),
                'required' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'help' => $this->translator->trans('Optional public key, will be encrypted.'),
            ])
            ->add('service', ChoiceType::class, [
                'choices' => [
                    $this->translator->trans('Stripe') => 'stripe',
                   $this->translator->trans('PayPal') => 'paypal',
                ],
                'label' => $this->translator->trans('Service'),
                'required' => true,
                'placeholder' => $this->translator->trans('Select a service'),
            ])
             ->add('isActive', CheckboxType::class, [
                'required' => false,
                'label' => $this->translator->trans('Activate this service'),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ApiCredential::class,
        ]);
    }
}
