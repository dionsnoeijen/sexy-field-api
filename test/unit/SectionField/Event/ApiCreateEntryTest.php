<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass Tardigrades\SectionField\Event\ApiCreateEntry
 * @covers ::__construct
 */
final class ApiCreateEntryTest extends TestCase
{
    /** @var ApiCreateEntry */
    private $apiCreateEntry;

    /** @var Request */
    private $request;

    /** @var string */
    private $handle;

    public function setUp()
    {
        $this->handle = 'someHandle';
        $this->request = new Request();
        $this->apiCreateEntry = new ApiCreateEntry($this->request, $this->handle);
    }

    /**
     * @test
     * @covers ::getRequest
     */
    public function it_should_return_the_request()
    {
        $result = $this->apiCreateEntry->getRequest();

        $this->assertSame($this->request, $result);
    }

    /**
     * @test
     * @covers ::getSectionHandle
     */
    public function it_should_get_the_handle()
    {
        $this->assertSame($this->handle, $this->apiCreateEntry->getSectionHandle());
    }
}
