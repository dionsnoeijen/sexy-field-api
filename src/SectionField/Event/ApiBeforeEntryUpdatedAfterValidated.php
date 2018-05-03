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

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Tardigrades\SectionField\Generator\CommonSectionInterface;

class ApiBeforeEntryUpdatedAfterValidated extends ApiCreateEntryValidEvent
{
    const NAME = 'api.before.entry.updated.after.validated';

    /** @var CommonSectionInterface */
    private $originalEntry;

    public function __construct(
        Request $request,
        array $responseData,
        JsonResponse $response,
        CommonSectionInterface $originalEntry,
        CommonSectionInterface $newEntry
    ) {
        parent::__construct($request, $responseData, $response, $newEntry);
        $this->originalEntry = $originalEntry;
    }

    public function getOriginalEntry(): CommonSectionInterface
    {
        return $this->originalEntry;
    }

    public function getNewEntry(): CommonSectionInterface
    {
        return $this->entry;
    }
}
