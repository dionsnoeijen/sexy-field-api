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
 * Class ApiSectionInfoFetched
 *
 * Dispatched after the info for a section is fetched (one or more)
 *
 * @package Tardigrades\SectionField\Event
 */
class ApiSectionInfoFetched extends ApiAfterEntriesEvent
{
    const NAME = 'api.section.info.fetched';
}
