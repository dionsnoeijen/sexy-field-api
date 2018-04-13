<?php

/*
 * This file is part of the SexyField package.
 *
 * (c) Dion Snoeijen <hallo@dionsnoeijen.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types = 1);

namespace Tardigrades\SectionField\Event;

/**
 * Class ApiEntryCreated
 *
 * Dispatched after the new entry (record) is created
 * and the form has been found valid
 *
 * @package Tardigrades\SectionField\Event
 */
class ApiEntryCreated extends ApiAfterEntryEvent
{
    const NAME = 'api.entry.created';
}
