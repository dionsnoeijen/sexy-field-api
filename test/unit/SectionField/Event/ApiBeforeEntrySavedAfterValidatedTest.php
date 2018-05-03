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
 * @coversDefaultClass Tardigrades\SectionField\Event\ApiBeforeEntrySavedAfterValidated
 * @covers ::__construct
 */
final class ApiBeforeEntrySavedAfterValidatedTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var ApiBeforeEntrySavedAfterValidated */
    private $apiBeforeEntrySavedAfterValidated;

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

        $this->apiBeforeEntrySavedAfterValidated = new ApiBeforeEntrySavedAfterValidated(
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
        $result = $this->apiBeforeEntrySavedAfterValidated->getRequest();

        $this->assertEquals($this->request, $result);
    }

    /**
     * @test
     * @covers ::getResponseData
     */
    public function it_should_return_the_response_data()
    {
        $result = $this->apiBeforeEntrySavedAfterValidated->getResponseData();

        $this->assertEquals($this->responseData, $result);
    }

    /**
     * @test
     * @covers ::getResponse
     */
    public function it_should_return_the_response()
    {
        $result = $this->apiBeforeEntrySavedAfterValidated->getResponse();

        $this->assertEquals($this->jsonResponse, $result);
    }

    /**
     * @test
     * @covers ::getEntry
     */
    public function it_should_return_the_new_entry()
    {
        $result = $this->apiBeforeEntrySavedAfterValidated->getEntry();

        $this->assertEquals($this->newEntry, $result);
    }
}
