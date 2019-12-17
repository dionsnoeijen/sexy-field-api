<?php
declare(strict_types=1);

namespace Tardigrades\SectionField\Api\Serializer;

use JMS\Serializer\Context;
use JMS\Serializer\JsonSerializationVisitor;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Tardigrades\SectionField\Api\Handler\TriggerHandler;
use Tardigrades\SectionField\Api\Handler\TriggerHandlerException;
use Tardigrades\SectionField\Generator\CommonSectionInterface;
use Tardigrades\SectionField\Service\TriggerServiceInterface;
use Tardigrades\SectionField\ValueObject\FullyQualifiedClassName;
use Tardigrades\SectionField\ValueObject\Trigger;

/**
 * @coversDefaultClass \Tardigrades\SectionField\Api\Handler\TriggerHandler
 * @covers ::<private>
 * @covers ::__construct
 */
final class TriggerHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var m\MockInterface|ContainerInterface */
    private $container;

    /** @var TriggerHandler */
    private $triggerHandler;

    public function setUp()
    {
        $this->container = m::mock(ContainerInterface::class);
        $this->triggerHandler = new TriggerHandler($this->container);
    }

    /**
     * @test
     * @covers ::executeTrigger
     */
    public function it_should_execute_a_trigger()
    {
        /** @var  JsonSerializationVisitor|m\MockInterface $visitor */
        $visitor = m::mock(JsonSerializationVisitor::class);

        /** @var CommonSectionInterface|m\MockInterface $entity */
        $entity = m::mock(CommonSectionInterface::class);

        /** @var Context|m\MockInterface $context */
        $context = m::mock(Context::class);

        $serviceName = FullyQualifiedClassName::fromString('service');
        $service = m::mock(TriggerServiceInterface::class);
        $trigger = Trigger::fromNameAndService(
            'name',
            $serviceName,
            $entity
        );

        $this->container->shouldReceive('get')
            ->once()
            ->with((string) $serviceName)
            ->andReturn($service);

        $service->shouldReceive('execute')
            ->once()
            ->with($trigger);

        $this->triggerHandler->executeTrigger($visitor, $trigger, [], $context);
    }

    /**
     * @test
     * @covers ::executeTrigger
     */
    public function it_should_throw_exception_when_trigger_is_not_of_correct_instance()
    {
        $this->expectException(TriggerHandlerException::class);
        $this->expectExceptionMessage('Invalid trigger service');

        /** @var  JsonSerializationVisitor|m\MockInterface $visitor */
        $visitor = m::mock(JsonSerializationVisitor::class);

        /** @var CommonSectionInterface|m\MockInterface $entity */
        $entity = m::mock(CommonSectionInterface::class);

        /** @var Context|m\MockInterface $context */
        $context = m::mock(Context::class);

        $serviceName = FullyQualifiedClassName::fromString('service');
        $service = m::mock(\stdClass::class);
        $trigger = Trigger::fromNameAndService(
            'name',
            $serviceName,
            $entity
        );

        $this->container->shouldReceive('get')
            ->once()
            ->with((string) $serviceName)
            ->andReturn($service);

        $this->triggerHandler->executeTrigger($visitor, $trigger, [], $context);
    }
}
