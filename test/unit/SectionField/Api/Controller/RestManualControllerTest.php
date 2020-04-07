<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Api\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Tardigrades\Entity\Field;
use Tardigrades\Entity\FieldType;
use Tardigrades\Entity\Section;
use Tardigrades\Entity\SectionInterface;
use Tardigrades\FieldType\Relationship\Relationship;
use Tardigrades\SectionField\Api\Serializer\SerializeToArrayInterface;
use Tardigrades\SectionField\Form\FormInterface;
use Tardigrades\SectionField\Generator\CommonSectionInterface;
use Tardigrades\SectionField\Service\CacheInterface;
use Tardigrades\SectionField\Service\CreateSectionInterface;
use Tardigrades\SectionField\Service\DeleteSectionInterface;
use Tardigrades\SectionField\Service\ReadOptions;
use Tardigrades\SectionField\Service\ReadSectionInterface;
use Tardigrades\SectionField\Service\SectionManagerInterface;
use Tardigrades\SectionField\ValueObject\Handle;
use Tardigrades\SectionField\ValueObject\Name;
use Tardigrades\SectionField\ValueObject\SectionConfig;
use Symfony\Component\Form\FormInterface as SymfonyFormInterface;

use Mockery;

/**
 * @coversDefaultClass Tardigrades\SectionField\Api\Controller\RestManualController
 *
 * @covers ::<private>
 * @covers ::<protected>
 */
class RestManualControllerTest extends TestCase
{
    use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    /** @var ReadSectionInterface|Mockery\Mock */
    private $readSection;

    /** @var CreateSectionInterface|Mockery\Mock */
    private $createSection;

    /** @var DeleteSectionInterface|Mockery\Mock */
    private $deleteSection;

    /** @var FormInterface|Mockery\Mock */
    private $form;

    /** @var SectionManagerInterface|Mockery\Mock */
    private $sectionManager;

    /** @var RequestStack|Mockery\Mock */
    private $requestStack;

    /** @var EventDispatcherInterface|Mockery\Mock */
    private $dispatcher;

    /** @var SerializeToArrayInterface|Mockery\MockInterface */
    private $serialize;

    /** @var CacheInterface|Mockery\MockInterface */
    private $cache;

    /** @var TokenStorageInterface|Mockery\MockInterface */
    private $tokenStorage;

    /** @var RestManualController */
    private $controller;

    public function setUp()
    {
        $this->readSection = Mockery::mock(ReadSectionInterface::class);
        $this->requestStack = Mockery::mock(RequestStack::class);
        $this->createSection = Mockery::mock(CreateSectionInterface::class);
        $this->deleteSection = Mockery::mock(DeleteSectionInterface::class);
        $this->form = Mockery::mock(FormInterface::class);
        $this->sectionManager = Mockery::mock(SectionManagerInterface::class);
        $this->dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $this->serialize = Mockery::mock(SerializeToArrayInterface::class);
        $this->cache = Mockery::mock(CacheInterface::class);
        $this->tokenStorage = Mockery::mock(TokenStorageInterface::class);

        $this->controller = new RestManualController(
            $this->createSection,
            $this->readSection,
            $this->deleteSection,
            $this->form,
            $this->sectionManager,
            $this->requestStack,
            $this->dispatcher,
            $this->serialize,
            $this->cache,
            $this->tokenStorage
        );
    }

    /**
     * @test
     * @covers ::getAction
     */
    public function it_should_pass_get_request_on_to_entry_by_id_action(): void
    {
        $request = new Request();
        $request->setMethod(Request::METHOD_GET);
        $this->requestStack->shouldReceive('getCurrentRequest')
            ->times(3)
            ->andReturn($request);

        // Loosely test getEntryByIdAction, because it's more
        // rigidly tested by another test
        $this->dispatcher->shouldReceive('dispatch');

        $section = Mockery::mock(Section::class);
        $sectionConfig = SectionConfig::fromArray([
            'section' => [
                'name' => 'SomeName',
                'handle' => 'someSectionHandle',
                'fields' => [
                    'name'
                ],
                'default' => 'name',
                'namespace' => 'I\\Am\\Namespace'
            ]
        ]);
        $section->shouldReceive('getConfig')
            ->once()
            ->andReturn($sectionConfig);

        $this->sectionManager->shouldReceive('readByHandle')
            ->once()
            ->andReturn($section);

        $this->cache->shouldReceive('start')
            ->once();
        $this->cache->shouldReceive('isHit')
            ->once()
            ->andReturn(false);

        $this->readSection->shouldReceive('read')
            ->once()
            ->with(Mockery::on(function (ReadOptions $readOptions) {
                return $readOptions->toArray()['id'] === 10 &&
                    $readOptions->toArray()['section'] === 'anotherHandle';
            }))
            ->andReturn(new \ArrayIterator([new SomeCommonSectionEntity()]));

        $this->serialize->shouldReceive('toArray')
            ->once()
            ->andReturn([
                'id' => 10
            ]);

        $this->cache->shouldReceive('set')
            ->once();

        $response = $this->controller->getAction(
            'anotherHandle',
            null,
            (string) 10
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame($response->getContent(), '{"id":10}');
    }

    /**
     * @test
     * @covers ::getAction
     */
    public function it_should_pass_get_request_on_to_entry_by_slug_action(): void
    {
        $request = new Request();
        $request->setMethod(Request::METHOD_GET);
        $this->requestStack->shouldReceive('getCurrentRequest')
            ->times(3)
            ->andReturn($request);

        // Loosely test getEntryByIdAction, because it's more
        // rigidly tested by another test
        $this->dispatcher->shouldReceive('dispatch');

        $section = Mockery::mock(Section::class);
        $sectionConfig = SectionConfig::fromArray([
            'section' => [
                'name' => 'SomeName',
                'handle' => 'someHandle',
                'fields' => [
                    'name'
                ],
                'default' => 'name',
                'namespace' => 'I\\Am\\Namespace'
            ]
        ]);
        $section->shouldReceive('getConfig')
            ->once()
            ->andReturn($sectionConfig);

        $this->sectionManager->shouldReceive('readByHandle')
            ->once()
            ->andReturn($section);

        $this->cache->shouldReceive('start')->once();
        $this->cache->shouldReceive('isHit')
            ->once()
            ->andReturn(false);

        $this->readSection->shouldReceive('read')
            ->once()
            ->with(Mockery::on(function (ReadOptions $readOptions) {
                return $readOptions->toArray()['slug'] === 'someSlug' &&
                    $readOptions->toArray()['section'] === 'someHandle';
            }))
            ->andReturn(new \ArrayIterator([new SomeCommonSectionEntity()]));

        $this->serialize->shouldReceive('toArray')
            ->once()
            ->andReturn([
                'id' => 10
            ]);

        $this->cache->shouldReceive('set')
            ->once();

        $response = $this->controller->getAction(
            'someHandle',
            null,
            null,
            'someSlug'
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame($response->getContent(), '{"id":10}');
    }

    /**
     * @test
     * @covers ::getAction
     */
    public function it_should_pass_get_request_on_to_entry_by_field_value_action(): void
    {
        $request = new Request();
        $request->setMethod(Request::METHOD_GET);
        $this->requestStack->shouldReceive('getCurrentRequest')
            ->times(3)
            ->andReturn($request);

        // Loosely test getEntryByIdAction, because it's more
        // rigidly tested by another test
        $this->dispatcher->shouldReceive('dispatch');

        $section = Mockery::mock(Section::class);
        $sectionConfig = SectionConfig::fromArray([
            'section' => [
                'name' => 'SomeName',
                'handle' => 'someHandle',
                'fields' => [
                    'name'
                ],
                'default' => 'name',
                'namespace' => 'I\\Am\\Namespace'
            ]
        ]);
        $section->shouldReceive('getConfig')
            ->once()
            ->andReturn($sectionConfig);

        $this->sectionManager->shouldReceive('readByHandle')
            ->once()
            ->andReturn($section);

        $this->cache->shouldReceive('start')->once();
        $this->cache->shouldReceive('isHit')
            ->once()
            ->andReturn(false);

        $this->readSection->shouldReceive('read')
            ->once()
            ->with(Mockery::on(function (ReadOptions $readOptions) {
                return $readOptions->toArray() === [
                    'section' => 'someHandle',
                    'field' => [
                        'aField' => 'aValue'
                    ],
                    'relate' => [],
                    'offset' => 0,
                    'limit' => 100,
                    'orderBy' =>  [
                        'created' => 'desc'
                    ],
                    'fetchFields' => []
                ];
            }))
            ->andReturn(new \ArrayIterator([new SomeCommonSectionEntity()]));

        $this->serialize->shouldReceive('toArray')
            ->once()
            ->andReturn([
                'id' => 10
            ]);

        $this->cache->shouldReceive('set')
            ->once();

        $response = $this->controller->getAction(
            'someHandle',
            'aField',
            null,
            null,
            null,
            null,
            null,
            null,
            'aValue'
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame($response->getContent(), '[{"id":10}]');
    }

    /**
     * @test
     * @covers ::getAction
     */
    public function it_should_get_entries(): void
    {
        $request = new Request();
        $this->requestStack->shouldReceive('getCurrentRequest')
            ->times(3)
            ->andReturn($request);

        $this->dispatcher->shouldReceive('dispatch')
            ->twice();

        $section = Mockery::mock(Section::class);
        $sectionConfig = SectionConfig::fromArray([
            'section' => [
                'name' => 'SomeName',
                'handle' => 'someHandle',
                'fields' => [
                    'name'
                ],
                'default' => 'name',
                'namespace' => 'I\\Am\\Namespace'
            ]
        ]);
        $section->shouldReceive('getConfig')
            ->once()
            ->andReturn($sectionConfig);

        $this->sectionManager->shouldReceive('readByHandle')
            ->once()
            ->andReturn($section);

        $this->cache->shouldReceive('start')->once();
        $this->cache->shouldReceive('isHit')
            ->once()
            ->andReturn(false);

        $this->readSection->shouldReceive('read')
            ->once()
            ->with(Mockery::on(function (ReadOptions $readOptions) {
                return $readOptions->toArray() === [
                    'section' => 'someSectionHandle',
                    'offset' => 0,
                    'limit' => 100,
                    'orderBy' => [
                        'created' => 'desc'
                    ],
                    'fetchFields' => []
                ];
            }))
            ->andReturn(new \ArrayIterator([ new SomeCommonSectionEntity() ]));

        $this->serialize->shouldReceive('toArray')
            ->once()
            ->andReturn([
                'id' => 10
            ]);

        $this->cache->shouldReceive('set')->once();

        $response = $this->controller->getAction(
            'someSectionHandle'
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame($response->getContent(), '[{"id":10}]');
    }

    /**
     * @test
     * @covers ::postAction
     */
    public function it_should_pass_on_post_request_to_create_entry_action(): void
    {
        $request = new Request();
        $request->setMethod(Request::METHOD_POST);
        $this->requestStack->shouldReceive('getCurrentRequest')
            ->twice()
            ->andReturn($request);

        $this->dispatcher->shouldReceive('dispatch')->times(3);

        $form = Mockery::mock(SymfonyFormInterface::class);
        $this->form->shouldReceive('buildFormForSection')
            ->once()
            ->andReturn($form);

        $entry = new SomeCommonSectionEntity();

        $form->shouldReceive('getName')->once();
        $form->shouldReceive('submit')->once();
        $form->shouldReceive('isValid')->once()->andReturn(true);
        $form->shouldReceive('getData')->times(3)->andReturn($entry);

        $this->createSection->shouldReceive('save')->once();

        $this->serialize->shouldReceive('toArray')
            ->once()
            ->andReturn([
                'id' => 10
            ]);

        $response = $this->controller->postAction('someSectionHandle');

        $this->assertSame(
            $response->getStatusCode(),
            Response::HTTP_OK
        );
        $this->assertSame(
            '{"code":200,"success":true,"errors":false,"entry":{"id":10}}',
            $response->getContent()
        );
    }

    /**
     * @test
     * @covers ::putAction
     */
    public function it_should_pass_on_put_request_to_update_entry_by_id_action(): void
    {
        $request = new Request();
        $request->setMethod(Request::METHOD_PUT);
        $this->requestStack->shouldReceive('getCurrentRequest')
            ->times(3)
            ->andReturn($request);

        // Loosely test getEntryByIdAction, because it's more
        // rigidly tested by another test
        $this->dispatcher->shouldReceive('dispatch');

        $form = Mockery::mock(SymfonyFormInterface::class);
        $this->form->shouldReceive('buildFormForSection')
            ->once()
            ->andReturn($form);

        $entry = new SomeCommonSectionEntity();

        $this->readSection->shouldReceive('read')
            ->once()
            ->with(Mockery::on(function (ReadOptions $readOptions) {
                return $readOptions->toArray() === [
                    'section' => 'someHandle',
                    'id' => 10
                ];
            }))
            ->andReturn(new \ArrayIterator([ $entry ]));

        $form->shouldReceive('getName')->once();
        $form->shouldReceive('submit')->once();
        $form->shouldReceive('isValid')->once()->andReturn(true);
        $form->shouldReceive('getData')->times(2)->andReturn($entry);

        $this->createSection->shouldReceive('save')->once();

        $this->serialize->shouldReceive('toArray')
            ->once()
            ->andReturn([
                'id' => 10
            ]);

        $response = $this->controller->putAction(
            'someHandle',
            (string) 10
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('{"code":200,"success":true,"errors":false,"entry":{"id":10}}', $response->getContent());
    }

    /**
     * @test
     * @covers ::putAction
     */
    public function it_should_pass_on_put_request_to_update_entry_by_slug_action(): void
    {
        $request = new Request();
        $request->setMethod(Request::METHOD_PUT);
        $this->requestStack->shouldReceive('getCurrentRequest')
            ->times(3)
            ->andReturn($request);

        // Loosely test getEntryByIdAction, because it's more
        // rigidly tested by another test
        $this->dispatcher->shouldReceive('dispatch');

        $form = Mockery::mock(SymfonyFormInterface::class);
        $this->form->shouldReceive('buildFormForSection')
            ->once()
            ->andReturn($form);

        $entry = new SomeCommonSectionEntity();

        $this->readSection->shouldReceive('read')
            ->once()
            ->with(Mockery::on(function (ReadOptions $readOptions) {
                return $readOptions->toArray() === [
                        'section' => 'someHandle',
                        'slug' => 'someSlug'
                    ];
            }))
            ->andReturn(new \ArrayIterator([ $entry ]));

        $form->shouldReceive('getName')->once();
        $form->shouldReceive('submit')->once();
        $form->shouldReceive('isValid')->once()->andReturn(true);
        $form->shouldReceive('getData')->times(2)->andReturn($entry);

        $this->createSection->shouldReceive('save')->once();

        $this->serialize->shouldReceive('toArray')
            ->once()
            ->andReturn([
                'id' => 10
            ]);

        $response = $this->controller->putAction(
            'someHandle',
            null,
            'someSlug'
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('{"code":200,"success":true,"errors":false,"entry":{"id":10}}', $response->getContent());
    }

    /**
     * @test
     * @covers ::deleteAction
     */
    public function it_should_pass_on_delete_request_to_delete_by_id(): void
    {
        $request = new Request();
        $request->setMethod(Request::METHOD_DELETE);
        $this->requestStack->shouldReceive('getCurrentRequest')
            ->times(2)
            ->andReturn($request);

        $this->dispatcher->shouldReceive('dispatch');

        $entry = new SomeCommonSectionEntity();

        $this->readSection->shouldReceive('read')
            ->once()
            ->with(Mockery::on(function (ReadOptions $readOptions) {
                return $readOptions->toArray() === [
                    'section' => 'someSectionHandle',
                    'id' => 10
                ];
            }))
            ->andReturn(new \ArrayIterator([$entry]));

        $this->deleteSection->shouldReceive('delete')->once()->andReturn(true);
        $response = $this->controller->deleteAction('someSectionHandle', (string) 10);

        $this->assertSame('{"success":true}', $response->getContent());
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @test
     *
     * @covers ::infoAction
     */
    public function it_should_pass_on_info_request(): void
    {
        $sectionName = 'Sexy';
        $sectionHandle = 'sexyHandle';
        $section = Mockery::mock(SectionInterface::class);

        $sectionConfig = SectionConfig::fromArray([
            'section' => [
                'name' => 'Some section',
                'handle' => 'anotherHandle',
                'fields' => [
                    'someHandle',
                    'someOtherHandle'
                ],
                'default' => 'default',
                'namespace' => 'Space'
            ]
        ]);
        $section->shouldReceive('getConfig')
            ->twice()
            ->andReturn($sectionConfig);

        $this->dispatcher->shouldReceive('dispatch')->once();

        $this->tokenStorage->shouldReceive('getToken')
            ->once()
            ->andReturn(null);

        $this->cache->shouldReceive('start')->once();
        $this->cache->shouldReceive('isHit')->once()->andReturn(false);
        $this->cache->shouldReceive('set')->once();

        $expectedFieldInfo = [
            'name' => $sectionName,
            'handle' => $sectionHandle
        ];

        $request = new Request([], [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);
        $request->setMethod(Request::METHOD_GET);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->times(3)
            ->andReturn($request);

        $this->sectionManager->shouldReceive('readByHandle')
            ->once()
            ->andReturn($section);

        $section->shouldReceive('getName')
            ->once()
            ->andReturn(Name::fromString($sectionName));

        $section->shouldReceive('getHandle')
            ->once()
            ->andReturn(Handle::fromString($sectionHandle));

        $section->shouldReceive('getFields')
            ->once()
            ->andReturn($this->givenASetOfFieldsForASection());

        $fields = $this->givenASetOfFieldInfo();

        $this->dispatcher->shouldReceive('dispatch')
            ->once();

        $sectionConfig = SectionConfig::fromArray([
            'section' => [
                'name' => 'Some section',
                'handle' => 'someHandle',
                'fields' => [
                    'someHandle',
                    'someOtherHandle'
                ],
                'default' => 'default',
                'namespace' => 'Space'
            ]
        ]);
        $section->shouldReceive('getConfig')
            ->once()
            ->andReturn($sectionConfig);

        $expectedFieldInfo['fields'] = $fields;

        $expectedResponse = new JsonResponse($expectedFieldInfo, 200, [
            'Access-Control-Allow-Origin' => 'iamtheorigin.com',
            'Access-Control-Allow-Credentials' => 'true'
        ]);

        $response = $this->controller->infoAction('sexyHandle');
        $this->assertEquals($expectedResponse, $response);
    }

    private function givenASetOfFieldInfo(bool $includeRelationships = false): array
    {
        $fieldInfos = [];
        $fields = $this->givenASetOfFieldsForASection($includeRelationships);

        foreach ($fields as $field) {
            $fieldInfo = [
                (string)$field->getHandle() => $field->getConfig()->toArray()['field']
            ];

            $fieldInfos[] = $fieldInfo;
        }

        return $fieldInfos;
    }

    private function givenASetOfFieldsForASection(bool $includeRelationships = false): Collection
    {
        $fields = new ArrayCollection();

        $fields->add(
            (new Field())
                ->setId(1)
                ->setConfig([
                    'field' => [
                        'name' => 'Fieldje',
                        'handle' => 'fieldje'
                    ]
                ])
                ->setHandle('someHandle')
                ->setFieldType(
                    (new FieldType())
                        ->setFullyQualifiedClassName('Some\\Fully\\Qualified\\Classname')
                        ->setType('TextInput')
                        ->setId(1)
                )
                ->setName('Some name field')
        );

        $fields->add(
            (new Field())
                ->setId(2)
                ->setConfig([
                    'field' => [
                        'name' => 'Nog een fieldje',
                        'handle' => 'nogEenFieldje'
                    ]
                ])
                ->setHandle('someOtherHandle')
                ->setFieldType(
                    (new FieldType())
                        ->setFullyQualifiedClassName('I\\Am\\The\\Fully\\Qualified\\Classname')
                        ->setType('TextInput')
                        ->setId(2)
                )
                ->setName('Give me text')
        );

        if ($includeRelationships) {
            $fields->add(
                (new Field())
                    ->setId(3)
                    ->setConfig([
                        'field' => [
                            'name' => 'Relatie veld',
                            'handle' => 'someRelationshipFieldHandle',
                            'to' => 'whatever',
                            'form' => [
                                'sexy-field-instructions' => [
                                    'relationship' => [
                                        'name-expression' => 'getFoo|getBar|getName',
                                        'limit' => 75,
                                        'offset' => 10,
                                        'field' => 'foo',
                                        'value' => 'bar,baz'
                                    ]
                                ]
                            ]
                        ]
                    ])
                    ->setHandle('someRelationshipFieldHandle')
                    ->setFieldType(
                        (new FieldType())
                            ->setFullyQualifiedClassName(Relationship::class)
                            ->setType('Relationship')
                            ->setId(3)
                    )
                    ->setName('Relatie veld')
            );
        }

        return $fields;
    }
}

class SomeCommonSectionEntity implements CommonSectionInterface {

    const FIELDS = [];

    public function getId(): ?int
    {
        return 10;
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
    }

    public function onPrePersist(): void
    {
    }

    public function onPreUpdate(): void
    {
    }

    public function getCreated(): ?\DateTime
    {
    }

    public function getUpdated(): ?\DateTime
    {
    }

    public function getSlug(): \Tardigrades\SectionField\ValueObject\Slug
    {
    }

    public function getDefault(): string
    {
    }

    public static function fieldInfo(): array
    {
    }
}

namespace Space\Entity;

class AnotherHandle {

    const FIELDS = [];

}
