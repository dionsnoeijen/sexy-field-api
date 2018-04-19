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

    public function setUp()
    {
        $this->request = new Request();
        $this->apiUpdateEntry = new ApiUpdateEntry($this->request, 'someHandle');
    }

    /**
     * @test
     * @covers ::getRequest
     */
    public function it_should_return_the_request()
    {
        $this->assertSame($this->request, $this->apiUpdateEntry->getRequest());
    }
}
