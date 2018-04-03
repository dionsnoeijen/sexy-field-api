<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Event;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Tardigrades\SectionField\Generator\CommonSectionInterface;

/**
 * @coversDefaultClass Tardigrades\SectionField\Event\SectionEntryUpdated
 * @covers ::__construct
 */
final class SectionEntryUpdatedTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var SectionEntryUpdated */
    private $sectionEntryUpdated;

    /** @var CommonSectionInterface */
    private $originalEntry;

    /** @var CommonSectionInterface */
    private $newEntry;

    public function setUp()
    {
        $this->originalEntry = Mockery::mock(CommonSectionInterface::class);
        $this->newEntry = Mockery::mock(CommonSectionInterface::class);
        $this->sectionEntryUpdated = new sectionEntryUpdated($this->originalEntry, $this->newEntry);
    }

    /**
     * @test
     * @covers ::getOriginalEntry
     */
    public function it_should_return_the_original_entry()
    {
        $result = $this->sectionEntryUpdated->getOriginalEntry();

        $this->assertEquals($this->originalEntry, $result);
    }

    /**
     * @test
     * @covers ::getNewEntry
     */
    public function it_should_return_the_new_entry()
    {
        $result = $this->sectionEntryUpdated->getNewEntry();

        $this->assertEquals($this->newEntry, $result);
    }
}
