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
 * @coversDefaultClass Tardigrades\SectionField\Event\ApiEntryCreated
 * @covers ::__construct
 */
final class ApiEntryCreatedTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var ApiEntryCreated */
    private $apiEntryCreated;

    /** @var CommonSectionInterface */
    private $newEntry;

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
        $this->newEntry = Mockery::mock(CommonSectionInterface::class);

        $this->apiEntryCreated = new ApiEntryCreated(
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
        $result = $this->apiEntryCreated->getRequest();

        $this->assertEquals($this->request, $result);
    }

    /**
     * @test
     * @covers ::getResponseData
     */
    public function it_should_return_the_response_data()
    {
        $result = $this->apiEntryCreated->getResponseData();

        $this->assertEquals($this->responseData, $result);
    }

    /**
     * @test
     * @covers ::getResponse
     */
    public function it_should_return_the_response()
    {
        $result = $this->apiEntryCreated->getResponse();

        $this->assertEquals($this->jsonResponse, $result);
    }

    /**
     * @test
     * @covers ::getEntry
     */
    public function it_should_return_the_original_entry()
    {
        $result = $this->apiEntryCreated->getEntry();

        $this->assertEquals($this->newEntry, $result);
    }
}
