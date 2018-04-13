<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Event;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Tardigrades\SectionField\Generator\CommonSectionInterface;

/**
 * @coversDefaultClass Tardigrades\SectionField\Event\ApiEntryUpdated
 * @covers ::__construct
 */
final class ApiEntryUpdatedTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var ApiEntryUpdated */
    private $apiEntryUpdated;

    /** @var Request */
    private $request;

    /** @var array */
    private $response;

    /** @var CommonSectionInterface */
    private $originalEntry;

    /** @var CommonSectionInterface */
    private $newEntry;

    public function setUp()
    {
        $this->request = new Request();
        $this->response = ['foo' => 'bar'];
        $this->originalEntry = Mockery::mock(CommonSectionInterface::class);
        $this->newEntry = Mockery::mock(CommonSectionInterface::class);
        $this->apiEntryUpdated = new ApiEntryUpdated(
            $this->request,
            $this->response,
            $this->originalEntry,
            $this->newEntry
        );
    }

    /**
     * @test
     * @covers ::getRequest
     */
    public function it_should_return_the_request()
    {
        $this->assertSame($this->request, $this->apiEntryUpdated->getRequest());
    }

    /**
     * @test
     * @covers ::getResponse
     */
    public function it_should_return_the_response()
    {
        $this->assertSame($this->response, $this->apiEntryUpdated->getResponse());
    }

    /**
     * @test
     * @covers ::getOriginalEntry
     */
    public function it_should_return_the_original_entry()
    {
        $result = $this->apiEntryUpdated->getOriginalEntry();

        $this->assertSame($this->originalEntry, $result);
    }

    /**
     * @test
     * @covers ::getNewEntry
     */
    public function it_should_return_the_new_entry()
    {
        $result = $this->apiEntryUpdated->getNewEntry();

        $this->assertSame($this->newEntry, $result);
    }
}
