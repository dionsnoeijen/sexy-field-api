<?php

/*
 * This file is part of the SexyField package.
 *
 * (c) Dion Snoeijen <hallo@dionsnoeijen.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types=1);

namespace Tardigrades\SectionField\Api\Handler;

use JMS\Serializer\Context;
use JMS\Serializer\GraphNavigator;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\JsonSerializationVisitor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Tardigrades\SectionField\Service\TriggerServiceInterface;
use Tardigrades\SectionField\ValueObject\Trigger;

class TriggerHandler implements SubscribingHandlerInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public static function getSubscribingMethods()
    {
        return [
            [
                'direction' => GraphNavigator::DIRECTION_SERIALIZATION,
                'format' => 'json',
                'type' => Trigger::class,
                'method' => 'executeTrigger'
            ],
        ];
    }

    /**
     * @param JsonSerializationVisitor $visitor
     * @param Trigger $trigger
     * @param array $type
     * @param Context $context
     * @return mixed
     * @throws TriggerHandlerException
     */
    public function executeTrigger(
        JsonSerializationVisitor $visitor,
        Trigger $trigger,
        array $type,
        Context $context
    ) {
        $triggerService = $this->container->get((string) $trigger->getService());

        if (!$triggerService instanceof TriggerServiceInterface) {
            return $triggerService->execute($trigger);
        }

        throw new TriggerHandlerException();
    }
}
