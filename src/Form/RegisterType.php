<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class RegisterType extends AbstractType
{  public function __construct(private readonly TranslatorInterface $translator)
    {}
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('Enter your full name'),
                ],
            ])
            ->add('email', EmailType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('Enter your professional email address'),
                ],
            ])
            ->add('password', PasswordType::class, [
                'hash_property_path' => 'password',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('Create a secure password'),
                ],
            ])
            ->add('confirm_password', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('Confirm your password'),
                ],
            ])
          
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
