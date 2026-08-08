<?php

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use NCB\Component\Gda\Administrator\Extension\GdaComponent;
use NCB\Component\Gda\Site\Service\CotisationService;
use NCB\Component\Gda\Site\Service\GdaConfigService;
use NCB\Component\Gda\Site\Service\NotificationMailService;

return new class implements ServiceProviderInterface
{
	public function register(Container $container)
	{
        $container->registerServiceProvider(new MVCFactory('\\NCB\\Component\\Gda'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\NCB\\Component\\Gda'));

        $container->set(
            GdaConfigService::class,
            function (Container $container) {
                return new GdaConfigService($container->get(DatabaseInterface::class));
            },
            true  // shared: une seule instance par requête
        );

        $container->set(
            CotisationService::class,
            function (Container $container) {
                return new CotisationService($container->get(DatabaseInterface::class), []);
            },
            true
        );

        $container->set(
            NotificationMailService::class,
            function (Container $container) {
                return new NotificationMailService(
                    $container->get(DatabaseInterface::class),
                    $container->get(MailerFactoryInterface::class),
                    $container->get(GdaConfigService::class)
                );
            },
            true
        );

        $container->set(
            ComponentInterface::class,
            function (Container $container)
            {
                $component = new GdaComponent($container->get(ComponentDispatcherFactoryInterface::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));

                return $component;
            }
        );

	}
};
