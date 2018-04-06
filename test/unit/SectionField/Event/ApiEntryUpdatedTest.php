<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Event;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
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

    /** @var CommonSectionInterface */
    private $originalEntry;

    /** @var CommonSectionInterface */
    private $newEntry;

    public function setUp()
    {
        $this->originalEntry = Mockery::mock(CommonSectionInterface::class);
        $this->newEntry = Mockery::mock(CommonSectionInterface::class);
        $this->apiEntryUpdated = new ApiEntryUpdated($this->originalEntry, $this->newEntry);
    }

    /**
     * @test
     * @covers ::getOriginalEntry
     */
    public function it_should_return_the_original_entry()
    {
        $result = $this->apiEntryUpdated->getOriginalEntry();

        $this->assertEquals($this->originalEntry, $result);
    }

    /**
     * @test
     * @covers ::getNewEntry
     */
    public function it_should_return_the_new_entry()
    {
        $result = $this->apiEntryUpdated->getNewEntry();

        $this->assertEquals($this->newEntry, $result);
    }
}
