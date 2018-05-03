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
 * Class ApiEntriesFetched
 *
 * Dispatched after the new entry (record) is fetched
 *
 * @package Tardigrades\SectionField\Event
 */
class ApiEntriesFetched extends ApiAfterEntriesEvent
{
    const NAME = 'api.entries.fetched';
}
