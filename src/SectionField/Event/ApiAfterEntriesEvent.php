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

use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class ApiAfterEntriesEvent extends Event
{
    const NAME = null;

    /** @var Request */
    protected $request;

    /** @var array */
    protected $responseData;

    /** @var \ArrayIterator */
    protected $entries;

    /** @var JsonResponse */
    private $response;

    public function __construct(
        Request $request,
        array $responseData,
        JsonResponse $response,
        \ArrayIterator $entries
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->responseData = $responseData;
        $this->entries = $entries;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getResponseData(): array
    {
        return $this->responseData;
    }

    public function getResponse(): JsonResponse
    {
        return $this->response;
    }

    public function getEntries(): \ArrayIterator
    {
        return $this->entries;
    }
}
