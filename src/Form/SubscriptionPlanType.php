<?php

// src/Form/SubscriptionPlanType.php

namespace App\Form;

use App\Entity\SubscriptionPlan;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * Form type to create and edit SubscriptionPlan entities.
 * 
 * Handles the subscription plan's name, description, features (stored as JSON array),
 * and price. Features are managed as checkboxes with translation support.
 */
class SubscriptionPlanType extends AbstractType
{
    /**
     * Translator service for translating form labels and choices.
     */
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * Builds the form fields.
     *
     * @param FormBuilderInterface $builder The form builder.
     * @param array $options Additional options.
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
             ->add('name', TextType::class, [
                    'attr' => [
                        'placeholder' => $this->translator->trans('e.g. Premium Plan'),
                    ],
                    'required' => false,
                    'empty_data' => '',
                ])
            ->add('description', TextareaType::class, [
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->translator->trans('e.g. Includes advanced encryption and 24/7 support'),
                    ],
                ])

            // Features field displayed as multiple checkboxes
            ->add('features', ChoiceType::class, [
                'label' => $this->translator->trans('Features'),
                'choices' => [
                    $this->translator->trans('Client-side encryption') => 'encryption',
                    $this->translator->trans('Burn after reading') => 'burn_after_reading',
                    $this->translator->trans('Custom expiration') => 'custom_expiration',
                    $this->translator->trans('IP logging') => 'ip_logging',
                    $this->translator->trans('Attachment support') => 'attachment_support',
                    $this->translator->trans('Password protection') => 'password_protection',
                    $this->translator->trans('Email notifications') => 'email_notifications',
                    $this->translator->trans('Priority support') => 'priority_support',
                ],
                'expanded' => true,   // Render choices as checkboxes
                'multiple' => true,   // Allow multiple selections
                'required' => false,
            ])
            ->add('isActive', CheckboxType::class, [
                    'label' => $this->translator->trans('Activate this plan'),
                    'required' => false,
                ])

            ->add('price', NumberType::class, [
                'scale' => 2,
                'required' => true,
                'html5' => true,
                'attr' => [
                    'min' => 0,
                    'placeholder' => $this->translator->trans('e.g. 49.99'),
                ],
            ]);
    }

    /**
     * Configures the options for this form.
     *
     * @param OptionsResolver $resolver The options resolver.
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        // Bind this form to the SubscriptionPlan entity class
        $resolver->setDefaults([
            'data_class' => SubscriptionPlan::class,
        ]);
    }
}
