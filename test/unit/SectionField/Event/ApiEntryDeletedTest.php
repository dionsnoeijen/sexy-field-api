<?php
declare(strict_types=1);

namespace Tardigrades\SectionField\Event;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Tardigrades\SectionField\Generator\CommonSectionInterface;

/**
 * @coversDefaultClass Tardigrades\SectionField\Event\ApiEntryDeleted
 * @covers ::__construct
 */
final class ApiEntryDeletedTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var ApiEntryDeleted */
    private $apiEntryDeleted;

    /** @var CommonSectionInterface */
    private $entry;

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
        $this->entry = Mockery::mock(CommonSectionInterface::class);

        $this->apiEntryDeleted = new ApiEntryDeleted(
            $this->request,
            $this->responseData,
            $this->jsonResponse,
            $this->entry
        );
    }

    /**
     * @test
     * @covers ::getRequest
     */
    public function it_should_return_the_request()
    {
        $this->assertSame($this->request, $this->apiEntryDeleted->getRequest());
    }

    /**
     * @test
     * @covers ::getResponseData
     */
    public function it_should_return_the_response_data()
    {
        $this->assertSame($this->responseData, $this->apiEntryDeleted->getResponseData());
    }

    /**
     * @test
     * @covers ::getResponse
     */
    public function it_should_return_the_response()
    {
        $this->assertSame($this->jsonResponse, $this->apiEntryDeleted->getResponse());
    }

    /**
     * @test
     * @covers ::getEntry
     */
    public function it_should_return_the_entry()
    {
        $this->assertSame($this->entry, $this->apiEntryDeleted->getEntry());
    }
}
