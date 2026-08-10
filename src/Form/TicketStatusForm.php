<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\TicketResolution;
use App\Enum\TicketStatus;
use App\Form\Model\TicketStatusData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Status, resolution and note in one form, because that is how the model moves:
 * the two axes travel together and the note is what makes a blockage legible.
 *
 * The pairing rules are not re-implemented here. The domain refuses an
 * inconsistent move and the controller turns that refusal into a form error, so
 * there is one authority on the invariant rather than two that can drift.
 *
 * @extends AbstractType<TicketStatusData>
 */
final class TicketStatusForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', EnumType::class, [
                'label' => 'ticket.form.status.label',
                'class' => TicketStatus::class,
                'choice_label' => static fn (TicketStatus $status): string => $status->translationKey(),
                /* Read by the menu the script builds on a machine with a mouse:
                   it draws each option as the badge the ticket itself wears.
                   Harmless anywhere else — a native menu ignores it. */
                'choice_attr' => static fn (TicketStatus $status): array => ['data-badge' => $status->value],
            ])
            ->add('resolution', EnumType::class, [
                'label' => 'ticket.form.resolution.label',
                'help' => 'ticket.form.resolution.help',
                'class' => TicketResolution::class,
                'choice_label' => static fn (TicketResolution $resolution): string => $resolution->translationKey(),
                'required' => false,
                'placeholder' => 'ticket.form.resolution.placeholder',
            ])
            ->add('note', TextareaType::class, [
                'label' => 'ticket.form.note.label',
                'help' => 'ticket.form.note.help',
                'required' => false,
                'attr' => ['rows' => 4, 'placeholder' => 'ticket.form.note.placeholder'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TicketStatusData::class]);
    }
}
