<?php

/*
 * This file is part of the SexyField package.
 *
 * (c) Dion Snoeijen <hallo@dionsnoeijen.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tardigrades\SectionField\Event;

/**
 * Class ApiEntryDeleted
 *
 * Dispatched after an entry is deleted.
 *
 * @package Tardigrades\SectionField\Event
 */
class ApiEntryDeleted extends ApiAfterEntryEvent
{
    const NAME = 'api.entry.deleted';
}
