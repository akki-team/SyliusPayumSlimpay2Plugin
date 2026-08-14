<?php

declare(strict_types=1);

namespace Akki\SyliusPayumSlimpayPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

final class SlimpayGatewayConfigurationType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('app_id', TextType::class, [
                'label' => 'akki.slimpay.app_id',
                'constraints' => [
                    new NotBlank([
                        'message' => 'akki.slimpay.app_id.not_blank',
                        'groups' => ['sylius']
                    ])
                ],
            ])
            ->add('app_secret', TextType::class, [
                'label' => 'akki.slimpay.app_secret',
                'constraints' => [
                    new NotBlank([
                        'message' => 'akki.slimpay.app_secret.not_blank',
                        'groups' => ['sylius']
                    ])
                ],
            ])
            ->add('creditor_reference', TextType::class, [
                'label' => 'akki.slimpay.creditor_reference',
                'constraints' => [
                    new NotBlank([
                        'message' => 'akki.slimpay.creditor_reference.not_blank',
                        'groups' => ['sylius']
                    ])
                ],
            ])
            ->add('sandbox', ChoiceType::class, [
                'label' => 'akki.slimpay.sandbox',
                'choices' => [
                    'akki.slimpay.no' => false,
                    'akki.slimpay.yes' => true
                ],
            ])
            ->add('default_checkout_mode', TextType::class, [
                'label' => 'akki.slimpay.default_checkout_mode',
                'constraints' => [
                    new NotBlank([
                        'message' => 'akki.slimpay.default_checkout_mode.not_blank',
                        'groups' => ['sylius']
                    ])
                ],
            ])
        ;
    }
}
