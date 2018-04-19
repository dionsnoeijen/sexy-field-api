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

    public function setUp()
    {
        $this->request = new Request();
        $this->apiCreateEntry = new ApiCreateEntry($this->request, 'someHandle');
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
}
