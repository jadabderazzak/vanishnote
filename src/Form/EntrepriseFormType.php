<?php

namespace App\Form;

use App\Entity\AdminEntreprise;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

/**
 * Form type to configure company administration settings.
 * 
 * Handles company details used for invoice generation and related settings.
 */
class EntrepriseFormType extends AbstractType
{
    /**
     * Translator service for dynamic translations.
     */
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * Build the form fields for company settings.
     * 
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('companyName', TextType::class, [
                'label' => $this->translator->trans('Company Name'),
                'attr' => ['placeholder' => $this->translator->trans('Enter the official registered company name')],
                'required' => false,
            ])
            ->add('companyAddress', TextType::class, [
                'label' => $this->translator->trans('Company Address'),
                'attr' => ['placeholder' => $this->translator->trans('Full legal address for invoicing and correspondence')],
                'required' => false,
            ])
            ->add('companyEmail', TextType::class, [
                'label' => $this->translator->trans('Company Email'),
                'attr' => ['placeholder' => $this->translator->trans('Email address used for official communications')],
                'required' => false,
            ])
            ->add('companyPhone', TextType::class, [
                'label' => $this->translator->trans('Company Phone'),
                'attr' => ['placeholder' => $this->translator->trans('Use international format, e.g. +123456789')],
                'required' => false,
            ])
            ->add('vatNumber', TextType::class, [
                'label' => $this->translator->trans('VAT Number'),
                'attr' => ['placeholder' => $this->translator->trans('Applicable VAT number if registered for tax purposes')],
                'required' => false,
            ])
            ->add('tvaApplicable', CheckboxType::class, [
                'label' => $this->translator->trans('Is VAT applicable?'),
                'required' => false,
            ])
             ->add('showLogoOnInvoice', CheckboxType::class, [
                'label' => $this->translator->trans('Show Logo On Invoice?'),
                'required' => false,
            ])
           ->add('tvaRate', IntegerType::class, [
                    'label' => $this->translator->trans('VAT Rate (%)'),
                    'attr' => [
                        'placeholder' => $this->translator->trans('Enter the VAT rate as a whole number, e.g. 20 for 20%'),
                        'min' => 0,
                        'max' => 27,
                    ],
                    'required' => false,
                    'constraints' => [
                        new Range([
                            'min' => 0,
                            'max' => 27,
                            'notInRangeMessage' => $this->translator->trans('The VAT rate must be between {{ min }} and {{ max }}.'),
                        ]),
                        new Type([
                            'type' => 'integer',
                            'message' => $this->translator->trans('The VAT rate must be an integer number.'),
                        ]),
                    ],
                ])
            ->add('defaultCurrency', TextType::class, [
                'label' => $this->translator->trans('Default Currency'),
                'attr' => ['placeholder' => $this->translator->trans('ISO currency code for invoice amounts, e.g. EUR')],
                'required' => true,
            ])
            ->add('invoicePrefix', TextType::class, [
                'label' => $this->translator->trans('Invoice Prefix'),
                'attr' => ['placeholder' => $this->translator->trans('Prefix for invoice numbers, e.g. INV-')],
                'required' => false,
            ])
            ->add('logoPath', FileType::class, [
                'label' => $this->translator->trans('Company Logo (optional)'),
                'attr' => ['accept' => 'image/png, image/jpeg'],
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/png', 'image/jpeg'],
                        'mimeTypesMessage' => $this->translator->trans('Please upload a valid PNG or JPG image'),
                    ]),
                ],
            ])
        ;
    }

    /**
     * Configure the options for this form.
     * 
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AdminEntreprise::class,
        ]);
    }
}
