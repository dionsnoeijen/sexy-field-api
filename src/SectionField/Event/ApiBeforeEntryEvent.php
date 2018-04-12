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

/**
 * Class ApiBeforeEntryEvent
 *
 * Dispatched with a request before an action on an entry.
 *
 * @package Tardigrades\SectionField\Event
 */
abstract class ApiBeforeEntryEvent extends Event
{
    const NAME = null;

    /** @var Request */
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
