<?php

namespace App\Form;

use App\Entity\Notes;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class NotesFormType extends AbstractType
{
   
    /**
     * Translator service for translating form labels and placeholders.
     */
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * Build form fields.
     *
     * @param FormBuilderInterface $builder
     * @param array $options expects 'planPrivileges' as array of privileges strings
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $privileges = $options['planPrivileges'] ?? [];

        $builder
            ->add('title', TextType::class, [
                'label' => $this->translator->trans('Note Title'),
                'required' => true,
                'attr' => [
                    'placeholder' => $this->translator->trans('Enter the title of your note'),
                    'maxlength' => 255,
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => $this->translator->trans('Note Content'),
                'required' => true,
                'attr' => [
                    'placeholder' => $this->translator->trans('Write your note content here'),
                    'rows' => 10,
                ],
            ]);

            // Password protection
            if (in_array('password_protection', $privileges, true)) {
                $builder->add('password', PasswordType::class, [
                    'label' => $this->translator->trans('Password (optional)'),
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->translator->trans('Set a password to protect your note'),
                        'autocomplete' => 'new-password',
                    ],
                ]);
            }


            // Burn after reading - custom checkbox
            if (in_array('burn_after_reading', $privileges, true)) {
                $builder->add('burnAfterReading', CheckboxType::class, [
                    'label' => $this->translator->trans('Burn After Reading'),
                    'required' => false,
                ]);
            }

            // Custom expiration date
            if (in_array('custom_expiration', $privileges, true)) {
                $builder->add('expirationDate', DateTimeType::class, [
                    'label' => $this->translator->trans('Custom Expiration Date'),
                    'required' => false,
                    'widget' => 'single_text',
                    'attr' => [
                        'placeholder' => $this->translator->trans('Choose an expiration date for the note'),
                    ],
                ]);
            }

           if (in_array('attachment_support', $privileges, true)) {
               $builder->add('attachements', FileType::class, [
    'label' => $this->translator->trans('Attachments'),
    'mapped' => false,
    'required' => false,
    'multiple' => true,
    'attr' => [
        'accept' => 'image/*,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain',
    ],
    'constraints' => [
        new Count([
            'max' => 5,
            'maxMessage' => $this->translator->trans('You can upload up to 5 files only.'),
        ]),
        new All([
            'constraints' => [
                new File([
                    'maxSize' => '10M',
                    'mimeTypes' => [
                        'image/*',
                        'application/pdf',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'text/plain',
                    ],
                    'mimeTypesMessage' => $this->translator->trans('Please upload a valid image, PDF, DOCX or text file'),
                ])
            ]
        ]),
    ],
]);
            }
              
        }

       

           

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Notes::class,
            'planPrivileges' => [],
        ]);
        $resolver->setAllowedTypes('planPrivileges', ['array']);
    }
}
