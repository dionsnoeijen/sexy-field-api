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
 * Dispatched before entry is fetched
 *
 * Class ApiFetchEntries
 * @package Tardigrades\SectionField\Event
 */
class ApiFetchEntries extends ApiBeforeEntryEvent
{
    const NAME = 'api.fetch.entries';
}
