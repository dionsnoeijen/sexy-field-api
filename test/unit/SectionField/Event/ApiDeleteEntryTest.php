<?php
declare(strict_types=1);

namespace Tardigrades\SectionField\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass Tardigrades\SectionField\Event\ApiDeleteEntry
 * @covers ::__construct
 */
final class ApiDeleteEntryTest extends TestCase
{
    /** @var ApiDeleteEntry */
    private $apiDeleteEntry;

    /** @var Request */
    private $request;

    /** @var string */
    private $handle;

    public function setUp(): void
    {
        $this->handle = 'someHandle';
        $this->request = new Request();
        $this->apiDeleteEntry = new ApiDeleteEntry($this->request, $this->handle);
    }

    /**
     * @test
     * @covers ::getRequest
     */
    public function it_should_return_the_request()
    {
        $this->assertSame($this->request, $this->apiDeleteEntry->getRequest());
    }

    /**
     * @test
     * @covers ::getSectionHandle
     */
    public function it_should_return_the_handle()
    {
        $this->assertSame($this->handle, $this->apiDeleteEntry->getSectionHandle());
    }
}
