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

    public function setUp()
    {
        $this->request = new Request();
        $this->apiDeleteEntry = new ApiDeleteEntry($this->request, 'someHandle');
    }

    /**
     * @test
     * @covers ::getRequest
     */
    public function it_should_return_the_request()
    {
        $this->assertSame($this->request, $this->apiDeleteEntry->getRequest());
    }
}
