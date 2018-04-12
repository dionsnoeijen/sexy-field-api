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
 * Class ApiBeforeCreateEntry
 *
 * Dispatched before new entry (record) is created
 * or even the form is evaluated
 *
 * @package Tardigrades\SectionField\Event
 */
class ApiCreateEntry extends ApiBeforeEntryEvent
{
    const NAME = 'api.create.entry';
}
