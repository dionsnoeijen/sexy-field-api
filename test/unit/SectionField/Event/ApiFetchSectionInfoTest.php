<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass Tardigrades\SectionField\Event\ApiFetchSectionInfo
 * @covers ::__construct
 */
final class ApiFetchSectionInfoTest extends TestCase
{
    /** @var ApiFetchSectionInfo */
    private $apiFetchSectionInfo;

    /** @var Request */
    private $request;

    /** @var string */
    private $handle;

    public function setUp(): void
    {
        $this->handle = 'someHandle';
        $this->request = new Request();
        $this->apiFetchSectionInfo = new ApiFetchSectionInfo($this->request, $this->handle);
    }

    /**
     * @test
     * @covers ::getRequest
     */
    public function it_should_return_the_request()
    {
        $result = $this->apiFetchSectionInfo->getRequest();

        $this->assertSame($this->request, $result);
    }

    /**
     * @test
     * @covers ::getSectionHandle
     */
    public function it_should_get_the_handle()
    {
        $this->assertSame($this->handle, $this->apiFetchSectionInfo->getSectionHandle());
    }
}
