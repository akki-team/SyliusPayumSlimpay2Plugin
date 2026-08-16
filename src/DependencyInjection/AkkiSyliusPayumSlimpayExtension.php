<?php

namespace Akki\SyliusPayumSlimpayPlugin\DependencyInjection;

use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class AkkiSyliusPayumSlimpayExtension extends Extension implements PrependExtensionInterface
{
    private const VOLET_CONFIGURATION = [
        'slimpay' => [
            'template' => '@AkkiSyliusPayumSlimpayPlugin/admin/payment_method/form/sections/gateway_configuration/slimpay.html.twig',
            'priority' => 0,
        ],
    ];

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $loader->load('services.yaml');
    }

    /**
     * L'ecran de methode de paiement de Sylius 2 ne rend que ce qui est declare : sa section
     * « Configuration de la passerelle » n'affiche que le choix de la passerelle et l'indicateur
     * Payum, puis appelle `{% hook 'gateway_configuration.<passerelle>' %}`. Sans ce volet, les
     * champs de Slimpay restent dans le formulaire sans etre affiches, et l'enregistrement de la
     * methode de paiement les remet a vide.
     */
    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('sylius_twig_hooks')) {
            return;
        }

        $container->prependExtensionConfig('sylius_twig_hooks', [
            'hooks' => [
                'sylius_admin.payment_method.create.content.form.sections.gateway_configuration.slimpay' => self::VOLET_CONFIGURATION,
                'sylius_admin.payment_method.update.content.form.sections.gateway_configuration.slimpay' => self::VOLET_CONFIGURATION,
            ],
        ]);
    }
}
