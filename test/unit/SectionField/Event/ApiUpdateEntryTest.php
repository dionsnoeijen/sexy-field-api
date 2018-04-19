<?php
declare(strict_types=1);

namespace Tardigrades\SectionField\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass Tardigrades\SectionField\Event\ApiUpdateEntry
 * @covers ::__construct
 */
final class ApiUpdateEntryTest extends TestCase
{
    /** @var ApiUpdateEntry */
    private $apiUpdateEntry;

    /** @var Request */
    private $request;

    /** @var string */
    private $handle;

    public function setUp()
    {
        $this->request = new Request();
        $this->handle = 'someHandle';
        $this->apiUpdateEntry = new ApiUpdateEntry($this->request, $this->handle);
    }

    /**
     * @test
     * @covers ::getRequest
     */
    public function it_should_return_the_request()
    {
        $this->assertSame($this->request, $this->apiUpdateEntry->getRequest());
    }

    /**
     * @test
     * @covers ::getSectionHandle
     */
    public function it_should_return_the_handle()
    {
        $this->assertSame($this->handle, $this->apiUpdateEntry->getSectionHandle());
    }
}
