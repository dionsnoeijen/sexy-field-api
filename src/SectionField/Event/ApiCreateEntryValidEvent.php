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

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;
use Tardigrades\SectionField\Generator\CommonSectionInterface;

/**
 * Class ApiBeforeEntryValidEvent
 *
 * Dispatched with a request when a form is valid but the form data is not yet saved
 *
 * @package Tardigrades\SectionField\Event
 */
abstract class ApiCreateEntryValidEvent extends Event
{
    const NAME = null;

    /** @var Request */
    protected $request;

    /** @var array */
    protected $response;

    /** @var CommonSectionInterface */
    protected $entry;

    public function __construct(Request $request,  array $response, CommonSectionInterface $entry)
    {
        $this->request = $request;
        $this->response = $response;
        $this->entry = $entry;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getResponse(): array
    {
        return $this->response;
    }

    public function getEntry(): CommonSectionInterface
    {
        return $this->entry;
    }
}
