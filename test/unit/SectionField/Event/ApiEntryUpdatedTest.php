<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Event;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    private $responseData;

    /** @var CommonSectionInterface */
    private $originalEntry;

    /** @var CommonSectionInterface */
    private $newEntry;

    /** @var JsonResponse */
    private $jsonResponse;

    public function setUp(): void
    {
        $this->request = new Request();
        $this->responseData = ['foo' => 'bar'];
        $this->jsonResponse = new JsonResponse();
        $this->originalEntry = Mockery::mock(CommonSectionInterface::class);
        $this->newEntry = Mockery::mock(CommonSectionInterface::class);
        $this->apiEntryUpdated = new ApiEntryUpdated(
            $this->request,
            $this->responseData,
            $this->jsonResponse,
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
     * @covers ::getResponseData
     */
    public function it_should_return_the_response_data()
    {
        $this->assertSame($this->responseData, $this->apiEntryUpdated->getResponseData());
    }

    /**
     * @test
     * @covers ::getResponse
     */
    public function it_should_return_the_response()
    {
        $this->assertSame($this->jsonResponse, $this->apiEntryUpdated->getResponse());
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
