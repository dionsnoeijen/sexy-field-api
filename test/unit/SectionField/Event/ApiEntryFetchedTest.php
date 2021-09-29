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
 * @coversDefaultClass Tardigrades\SectionField\Event\ApiEntryFetched
 * @covers ::__construct
 */
final class ApiEntryFetchedTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var ApiEntryFetched */
    private $apiEntryFetched;

    /** @var CommonSectionInterface */
    private $newEntry;

    /** @var Request */
    private $request;

    /** @var array */
    private $responseData;

    /** @var JsonResponse */
    private $jsonResponse;

    public function setUp(): void
    {
        $this->request = new Request();
        $this->responseData = [];
        $this->jsonResponse = new JsonResponse();
        $this->newEntry = Mockery::mock(CommonSectionInterface::class);

        $this->apiEntryFetched = new ApiEntryFetched(
            $this->request,
            $this->responseData,
            $this->jsonResponse,
            $this->newEntry
        );
    }

    /**
     * @test
     * @covers ::getRequest
     */
    public function it_should_return_the_request()
    {
        $result = $this->apiEntryFetched->getRequest();

        $this->assertEquals($this->request, $result);
    }

    /**
     * @test
     * @covers ::getResponseData
     */
    public function it_should_return_the_response_data()
    {
        $result = $this->apiEntryFetched->getResponseData();

        $this->assertEquals($this->responseData, $result);
    }

    /**
     * @test
     * @covers ::getResponse
     */
    public function it_should_return_the_response()
    {
        $result = $this->apiEntryFetched->getResponse();

        $this->assertEquals($this->jsonResponse, $result);
    }

    /**
     * @test
     * @covers ::getEntry
     */
    public function it_should_return_the_original_entry()
    {
        $result = $this->apiEntryFetched->getEntry();

        $this->assertEquals($this->newEntry, $result);
    }
}
