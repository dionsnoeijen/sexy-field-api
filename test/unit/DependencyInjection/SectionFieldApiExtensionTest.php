<?php
declare (strict_types=1);

namespace Tardigrades\DependencyInjection;

use Mockery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * @coversDefaultClass Tardigrades\DependencyInjection\SectionFieldApiExtension
 * @covers ::<private>
 */
class SectionFieldApiExtensionTest extends TestCase
{
    /**
     * @test
     * @covers ::load
     */
    public function it_loads()
    {
        $containerBuilder = Mockery::mock(ContainerBuilder::class)->shouldDeferMissing();

        $loader = Mockery::mock('overload:Symfony\Component\DependencyInjection\Loader\YamlFileLoader')->shouldDeferMissing();
        $loader->shouldReceive('load')
            ->once()
            ->with('controllers.yml');

        $load = new SectionFieldApiExtension;
        $load->load([], $containerBuilder);
        $this->assertInstanceOf(SectionFieldApiExtension::class, $load);
    }
}
