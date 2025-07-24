<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Country;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Form\EventSubscriber\FormSanitizeSubscriber;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

/**
 * Form type for managing Client entities.
 * 
 * This form handles input fields related to the Client entity, including personal and company details.
 * 
 * Labels and help texts are translated on the fly using the TranslatorInterface.
 * 
 * The form automatically attaches a global HTML sanitizer subscriber to clean all submitted string inputs
 * and prevent XSS attacks before data binding.
 * 
 * Fields included:
 *  - name: Full name or contact person (required)
 *  - isCompany: Checkbox indicating if the client is a company
 *  - company: Company name (optional)
 *  - companyAdress: Company address (optional)
 *  - vatNumber: VAT number (optional)
 *  - phone: Phone number (optional), with help text suggesting international format
 */
class ClientsType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator, private readonly FormSanitizeSubscriber $sanitizerSubscriber)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Full name or contact person
            ->add('name', TextType::class, [
                'label' => $this->translator->trans('Name'),
                'help' => $this->translator->trans('Full name or contact person'),
                'required' => false,
                'empty_data' => '',
            ])

            // Whether this client is a company
            ->add('isCompany', CheckboxType::class, [
                'label' => $this->translator->trans('Is this a company?'),
                'required' => false,
             
            ])

            // Company name (optional)
            ->add('company', TextType::class, [
                'label' => $this->translator->trans('Company name'),
                'required' => false,
                'empty_data' => '',
            ])

            // Company address (optional)
            ->add('companyAdress', TextType::class, [
                'label' => $this->translator->trans('Company address'),
                'required' => false,
                'empty_data' => '',
            ])

            // VAT number (optional)
            ->add('vatNumber', TextType::class, [
                'label' => $this->translator->trans('VAT number'),
                'required' => false,
                'empty_data' => '',
            ])

            // Phone number (optional)
            ->add('phone', TextType::class, [
                'label' => $this->translator->trans('Phone number'),
                'help' => $this->translator->trans('Use international format like +123456789'),
                'required' => false,
                'empty_data' => '',
            ])
            ->add('country', EntityType::class, [
                'class' => Country::class,
                'choice_label' => function (Country $country) {
                        return $this->translator->trans($country->getName());
                    },
              
                'label' => $this->translator->trans('Country'),
                'placeholder' => $this->translator->trans('Select a country'),
                'required' => true,                  // Make the field mandatory in the HTML form
                'empty_data' => null,
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('Please select a country.'),
                    ]),
                ],                                  // Backend validation to ensure the field is not empty
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')->orderBy('c.name', 'ASC');
                },
]);

            $builder->addEventSubscriber($this->sanitizerSubscriber);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
        ]);
    }
}
