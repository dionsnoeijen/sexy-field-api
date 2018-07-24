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

/**
 * @todo: This needs some work to take out the hardcoded information on the entity.
 * Make configurable, and have it look at the associated timezone field in the data storage.
 *
 * Class DateTimeTimezoneHandler
 * @package Tardigrades\SectionField\Api\Handler
 */
class DateTimeTimezoneHandler implements SubscribingHandlerInterface
{

    public static function getSubscribingMethods()
    {
        return array(
            array(
                'direction' => GraphNavigator::DIRECTION_SERIALIZATION,
                'format' => 'json',
                'type' => 'DateTime',
                'method' => 'serializeDateTimeToJson'
            ),
        );
    }

    public function serializeDateTimeToJson(JsonSerializationVisitor $visitor, \DateTime $date, array $type, Context $context)
    {
        $date->setTimezone(new \DateTimeZone('Europe/Amsterdam'));
        return $date->format('Y-m-d H:i');
    }
}
