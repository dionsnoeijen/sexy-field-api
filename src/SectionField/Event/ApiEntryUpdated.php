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

use Symfony\Component\EventDispatcher\Event;
use Tardigrades\SectionField\Generator\CommonSectionInterface;

/**
 * Class ApiEntryUpdated
 *
 * Dispatched after updated entry is saved.
 *
 * @package Tardigrades\SectionField\Event
 */
class ApiEntryUpdated extends Event
{
    const NAME = 'api.entry.updated';

    /** @var CommonSectionInterface */
    protected $originalEntry;

    /** @var CommonSectionInterface */
    protected $newEntry;

    public function __construct(
        CommonSectionInterface $originalEntry,
        CommonSectionInterface $newEntry
    ) {
        $this->originalEntry = $originalEntry;
        $this->newEntry = $newEntry;
    }

    /**
     * The Section Entry Entity that was just persisted
     */
    public function getOriginalEntry(): CommonSectionInterface
    {
        return $this->originalEntry;
    }

    /**
     * The Section Entry Entity that was just persisted
     */
    public function getNewEntry(): CommonSectionInterface
    {
        return $this->newEntry;
    }
}
