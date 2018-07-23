<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Event;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass Tardigrades\SectionField\Event\ApiSectionInfoFetched
 * @covers ::__construct
 */
final class ApiSectionInfoFetchedTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var ApiSectionInfoFetched */
    private $apiSectionInfoFetched;

    /** @var \ArrayIterator */
    private $entries;

    /** @var Request */
    private $request;

    /** @var array */
    private $responseData;

    /** @var JsonResponse */
    private $jsonResponse;

    public function setUp()
    {
        $this->request = new Request();
        $this->responseData = [];
        $this->jsonResponse = new JsonResponse();
        $this->entries = new \ArrayIterator();

        $this->apiSectionInfoFetched = new ApiSectionInfoFetched(
            $this->request,
            $this->responseData,
            $this->jsonResponse,
            $this->entries
        );
    }

    /**
     * @test
     * @covers ::getRequest
     */
    public function it_should_return_the_request()
    {
        $result = $this->apiSectionInfoFetched->getRequest();

        $this->assertEquals($this->request, $result);
    }

    /**
     * @test
     * @covers ::getResponseData
     */
    public function it_should_return_the_response_data()
    {
        $result = $this->apiSectionInfoFetched->getResponseData();

        $this->assertEquals($this->responseData, $result);
    }

    /**
     * @test
     * @covers ::getResponse
     */
    public function it_should_return_the_response()
    {
        $result = $this->apiSectionInfoFetched->getResponse();

        $this->assertEquals($this->jsonResponse, $result);
    }

    /**
     * @test
     * @covers ::getEntries
     */
    public function it_should_return_the_original_entry()
    {
        $result = $this->apiSectionInfoFetched->getEntries();

        $this->assertEquals($this->entries, $result);
    }
}
