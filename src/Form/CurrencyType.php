<?php

namespace App\Form;

use App\Entity\Currency;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class CurrencyType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {}
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder

            ->add('code', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('Enter the ISO currency code (e.g. USD)'),
                ],
            ])
            ->add('name', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('Enter the full currency name (e.g. United States Dollar)'),
                ],
            ])
            ->add('symbol', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('Enter the currency symbol (e.g. $)'),
                ],
            ])
            ->add('isPrimary', CheckboxType::class, [
                'required' => false,
                'label' => $this->translator->trans('Primary currency'),
            ])


           
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Currency::class,
        ]);
    }
}
