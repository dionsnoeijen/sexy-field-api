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
 * @coversDefaultClass Tardigrades\SectionField\Event\ApiBeforeEntryUpdatedAfterValidated
 * @covers ::__construct
 */
final class ApiBeforeEntryUpdatedAfterValidatedTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var ApiBeforeEntryUpdatedAfterValidated */
    private $apiBeforeEntryUpdatedAfterValidated;

    /** @var CommonSectionInterface */
    private $newEntry;

    /** @var Request */
    private $request;

    /** @var array */
    private $responseData;

    /** @var JsonResponse */
    private $jsonResponse;

    /** @var CommonSectionInterface */
    private $originalEntry;

    public function setUp()
    {
        $this->request = new Request();
        $this->responseData = [];
        $this->jsonResponse = new JsonResponse();
        $this->originalEntry = Mockery::mock(CommonSectionInterface::class);
        $this->newEntry = Mockery::mock(CommonSectionInterface::class);

        $this->apiBeforeEntryUpdatedAfterValidated = new ApiBeforeEntryUpdatedAfterValidated(
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
        $result = $this->apiBeforeEntryUpdatedAfterValidated->getRequest();

        $this->assertEquals($this->request, $result);
    }

    /**
     * @test
     * @covers ::getResponseData
     */
    public function it_should_return_the_response_data()
    {
        $result = $this->apiBeforeEntryUpdatedAfterValidated->getResponseData();

        $this->assertEquals($this->responseData, $result);
    }

    /**
     * @test
     * @covers ::getResponse
     */
    public function it_should_return_the_response()
    {
        $result = $this->apiBeforeEntryUpdatedAfterValidated->getResponse();

        $this->assertEquals($this->jsonResponse, $result);
    }

    /**
     * @test
     * @covers ::getNewEntry
     */
    public function it_should_return_the_new_entry()
    {
        $result = $this->apiBeforeEntryUpdatedAfterValidated->getNewEntry();

        $this->assertEquals($this->newEntry, $result);
    }

    /**
     * @test
     * @covers ::getOriginalEntry
     */
    public function it_should_return_the_original_entry()
    {
        $result = $this->apiBeforeEntryUpdatedAfterValidated->getOriginalEntry();

        $this->assertEquals($this->originalEntry, $result);
    }
}
