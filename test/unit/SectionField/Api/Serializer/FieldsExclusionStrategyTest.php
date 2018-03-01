<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Api\Serializer;

use Mockery;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\Metadata\PropertyMetadata;
use PHPUnit\Framework\TestCase;
use JMS\Serializer\SerializationContext;

/**
 * @coversDefaultClass Tardigrades\SectionField\Api\Serializer\FieldsExclusionStrategy
 * @covers ::<private>
 */
class FieldsExclusionStrategyTest extends TestCase
{
    use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    /**
     * @test
     * @covers ::__construct
     */
    public function it_constructs()
    {
        $fields = ['a', 'b', 'c'];
        $strategy = new FieldsExclusionStrategy($fields);
        $this->assertInstanceOf(FieldsExclusionStrategy::class, $strategy);
    }

    /**
     * @test
     * @covers ::shouldSkipClass
     */
    public function it_should_return_false_for_skip_class()
    {
        $strategy = new FieldsExclusionStrategy(['strategic']);
        $class = Mockery::mock(ClassMetadata::class)->makePartial();
        $this->assertFalse($strategy->shouldSkipClass($class, SerializationContext::create()));
    }

    /**
     * @test
     * @covers ::shouldSkipProperty
     */
    public function it_should_return_correctly_for_skip_property()
    {
        $strategy = new FieldsExclusionStrategy(['apples', 'bananas', 'crustaceans']);

        $property = Mockery::mock(PropertyMetadata::class);
        $property->serializedName = false;
        $property->name = 'bananas';

        $propertytwo = Mockery::mock(PropertyMetadata::class);
        $propertytwo->serializedName = false;
        $propertytwo->name = 'dinosaurs';

        $this->assertFalse($strategy->shouldSkipProperty($property, SerializationContext::create()));
        $this->assertTrue($strategy->shouldSkipProperty($propertytwo, SerializationContext::create()));
    }

    /**
     * @test
     * @covers ::shouldSkipProperty
     */
    public function it_should_not_skip_a_property_if_the_list_is_empty()
    {
        $strategy = new FieldsExclusionStrategy([]);

        $property = Mockery::mock(PropertyMetadata::class);
        $property->serializedName = false;
        $property->name = 'bananas';

        $this->assertFalse($strategy->shouldSkipProperty($property, SerializationContext::create()));
    }
}
