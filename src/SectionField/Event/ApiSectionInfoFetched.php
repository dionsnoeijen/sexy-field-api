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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ApiSectionInfoFetched
 *
 * Dispatched after the info for a section is fetched (one or more)
 *
 * @package Tardigrades\SectionField\Event
 */
class ApiSectionInfoFetched extends Event
{
    const NAME = 'api.section.info.fetched';

    /** @var Request */
    protected $request;

    /** @var array */
    protected $responseData;

    /** @var JsonResponse */
    protected $response;

    /** @var string */
    protected $sectionHandle;

    public function __construct(
        Request $request,
        array $responseData,
        JsonResponse $response,
        string $sectionHandle
    ) {
        $this->request = $request;
        $this->responseData = $responseData;
        $this->response = $response;
        $this->sectionHandle = $sectionHandle;
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

    public function getSectionHandle(): string
    {
        return $this->sectionHandle;
    }
}
