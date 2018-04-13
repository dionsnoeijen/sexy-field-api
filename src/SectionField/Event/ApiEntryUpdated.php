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

use Symfony\Component\HttpFoundation\Request;
use Tardigrades\SectionField\Generator\CommonSectionInterface;

/**
 * Class ApiEntryUpdated
 *
 * Dispatched after updated entry is saved.
 *
 * @package Tardigrades\SectionField\Event
 */
class ApiEntryUpdated extends ApiAfterEntryEvent
{
    const NAME = 'api.entry.updated';

    /** @var CommonSectionInterface */
    protected $originalEntry;

    public function __construct(
        Request $request,
        array $response,
        CommonSectionInterface $originalEntry,
        CommonSectionInterface $newEntry
    ) {
        parent::__construct($request, $response, $newEntry);
        $this->originalEntry = $originalEntry;
    }

    /**
     * The Section Entry Entity that was replaced
     */
    public function getOriginalEntry(): CommonSectionInterface
    {
        return $this->originalEntry;
    }

    /**
     * The new Section Entry Entity
     */
    public function getNewEntry(): CommonSectionInterface
    {
        return $this->entry;
    }
}
