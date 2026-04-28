<?php

namespace App\Notification\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

final class AppNotificationExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        // Keep the same behavior as the former config/packages/notifier.yaml
        $container->prependExtensionConfig('framework', [
            'notifier' => [
                'chatter_transports' => [],
                'texter_transports' => [],
                'channel_policy' => [
                    'urgent' => ['email'],
                    'high' => ['email'],
                    'medium' => ['email'],
                    'low' => ['email'],
                ],
                'admin_recipients' => [
                    ['email' => 'admin@example.com'],
                ],
            ],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        // No services to load for now. This bundle only provides framework.notifier configuration.
    }
}

