<?php
declare(strict_types=1);

namespace Tardigrades\SectionField\Api\Serializer;

use JMS\Serializer\Context;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\Metadata\PropertyMetadata;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Tardigrades\SectionField\Api\Serializer\DepthExclusionStrategy
 * @covers ::<private>
 */
final class DepthExclusionStrategyTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @test
     * @covers ::__construct
     * @covers ::shouldSkipClass
     * @covers ::shouldSkipProperty
     */
    public function it_skips_when_too_deep()
    {
        $strategy = new DepthExclusionStrategy(10);
        $class = Mockery::mock(ClassMetadata::class);
        $property = Mockery::mock(PropertyMetadata::class);
        $context = Mockery::mock(Context::class);
        $context
            ->shouldReceive('getDepth')
            ->andReturn(11);
        $this->assertTrue($strategy->shouldSkipClass($class, $context));
        $this->assertTrue($strategy->shouldSkipProperty($property, $context));
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::shouldSkipClass
     * @covers ::shouldSkipProperty
     */
    public function it_does_not_skip_when_not_too_deep()
    {
        $strategy = new DepthExclusionStrategy(30);
        $class = Mockery::mock(ClassMetadata::class);
        $property = Mockery::mock(PropertyMetadata::class);
        $context = Mockery::mock(Context::class);
        $context
            ->shouldReceive('getDepth')
            ->andReturn(11);
        $this->assertFalse($strategy->shouldSkipClass($class, $context));
        $this->assertFalse($strategy->shouldSkipProperty($property, $context));
    }
}
