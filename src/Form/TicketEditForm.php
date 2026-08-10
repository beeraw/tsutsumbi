<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\TicketPriority;
use App\Enum\TicketType;
use App\Form\Model\TicketEditData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<TicketEditData>
 */
final class TicketEditForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'ticket.form.title.label',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'ticket.form.description.label',
                'help' => 'ticket.form.description.help',
                'required' => false,
                'attr' => ['rows' => 8, 'placeholder' => 'ticket.form.description.placeholder'],
            ])
            ->add('type', EnumType::class, [
                'label' => 'ticket.form.type.label',
                'class' => TicketType::class,
                'choice_label' => static fn (TicketType $type): string => $type->translationKey(),
            ])
            ->add('priority', EnumType::class, [
                'label' => 'ticket.form.priority.label',
                'class' => TicketPriority::class,
                'choice_label' => static fn (TicketPriority $priority): string => $priority->translationKey(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TicketEditData::class]);
    }
}
