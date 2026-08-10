<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\ApiTokenScope;
use App\Form\Model\ApiTokenData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ApiTokenData>
 */
final class ApiTokenForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'api_token.form.label.label',
                'help' => 'api_token.form.label.help',
                'attr' => ['placeholder' => 'api_token.form.label.placeholder'],
            ])
            ->add('agentSlug', TextType::class, [
                'label' => 'api_token.form.agent_slug.label',
                'help' => 'api_token.form.agent_slug.help',
                'attr' => ['placeholder' => 'claude-code'],
            ])
            ->add('scopes', EnumType::class, [
                'label' => 'api_token.form.scopes.label',
                'help' => 'api_token.form.scopes.help',
                'class' => ApiTokenScope::class,
                'choice_label' => static fn (ApiTokenScope $scope): string => $scope->translationKey(),
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('expiresAt', DateType::class, [
                'label' => 'api_token.form.expires_at.label',
                'help' => 'api_token.form.expires_at.help',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ApiTokenData::class]);
    }
}
