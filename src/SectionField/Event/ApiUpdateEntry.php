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
 * Class ApiUpdateEntry
 *
 * Dispatched with the request before an entry is updated or the form is evaluated.
 *
 * @package Tardigrades\SectionField\Event
 */
class ApiUpdateEntry extends Event
{
    const NAME = 'api.update.entry';

    /** @var Request */
    protected $request;

    /** @param Request $request */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /** @return Request */
    public function getRequest(): Request
    {
        return $this->request;
    }
}
