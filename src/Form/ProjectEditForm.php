<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Model\ProjectEditData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ProjectEditData>
 */
final class ProjectEditForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'project.form.name.label',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'project.form.description.label',
                'help' => 'project.form.description.help',
                'required' => false,
                'attr' => ['rows' => 5, 'placeholder' => 'project.form.description.placeholder'],
            ])
            ->add('path', TextType::class, [
                'label' => 'project.form.path.label',
                'help' => 'project.form.path.help',
            ])
            // The one field with consequences beyond this page: the ticket keys
            // are derived from it, so the help text spells out what a change
            // costs rather than the form refusing it.
            ->add('key', TextType::class, [
                'label' => 'project.form.key.label',
                'help' => 'project.form.key.help',
                'attr' => ['maxlength' => 10, 'spellcheck' => 'false', 'autocapitalize' => 'characters'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ProjectEditData::class]);
    }
}
