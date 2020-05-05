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

class PurifierHandler implements SubscribingHandlerInterface
{
    private $container;
    private $profile;

    public function __construct(ContainerInterface $container, string $profile = 'default')
    {
        $this->container = $container;
        $this->profile = $profile;
    }

    public static function getSubscribingMethods()
    {
        return [
            [
                'direction' => GraphNavigator::DIRECTION_SERIALIZATION,
                'format' => 'json',
                'type' => 'string',
                'method' => 'executePurifier'
            ],
        ];
    }

    /**
     * @param JsonSerializationVisitor $visitor
     * @param string $value
     * @param array $type
     * @param Context $context
     * @return mixed
     */
    public function executeTrigger(
        JsonSerializationVisitor $visitor,
        string $value,
        array $type,
        Context $context
    ) {
        $purifier = $this->container->get('sexy_field.'.$this->profile);
        return $purifier->purify($value);
    }
}
