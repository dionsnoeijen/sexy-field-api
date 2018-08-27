<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Api\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Guzzle\Http\Message\Header\HeaderCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Tardigrades\Entity\Field;
use Tardigrades\Entity\FieldType;
use Tardigrades\Entity\SectionInterface;
use Tardigrades\FieldType\Relationship\Relationship;
use Tardigrades\SectionField\Api\Serializer\SerializeToArrayInterface;
use Tardigrades\SectionField\Event\ApiSectionInfoFetched;
use Tardigrades\SectionField\Form\FormInterface;
use Symfony\Component\Form\FormInterface as SymfonyFormInterface;
use Tardigrades\SectionField\Generator\CommonSectionInterface;
use Tardigrades\SectionField\Service\CacheInterface;
use Tardigrades\SectionField\Service\CreateSectionInterface;
use Tardigrades\SectionField\Service\DeleteSectionInterface;
use Tardigrades\SectionField\Service\EntryNotFoundException;
use Tardigrades\SectionField\Service\ReadSectionInterface;
use Tardigrades\SectionField\Service\SectionManagerInterface;
use Mockery;
use Tardigrades\SectionField\Service\SectionNotFoundException;
use Tardigrades\SectionField\ValueObject\Handle;
use Tardigrades\SectionField\ValueObject\Name;
use Tardigrades\SectionField\ValueObject\SectionConfig;

/**
 * @coversDefaultClass Tardigrades\SectionField\Api\Controller\RestInfoController
 *
 * @covers ::<private>
 * @covers ::<protected>
 */
class RestInfoControllerTest extends TestCase
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

    /** @var RestInfoController */
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

        $this->controller = new RestInfoController(
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
     * @covers ::__construct
     * @covers ::getSectionInfo
     */
    public function it_returns_options_listings()
    {
        $allowedMethods = 'OPTIONS, GET, POST, PUT, DELETE';
        $testCases = [
            // method name,    arguments,      allowed HTTP methods
            ['getSectionInfo', ['foo', "0"], $allowedMethods]
        ];
        foreach ($testCases as [$method, $args, $allowMethods]) {
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('getMethod')
                ->andReturn('options');
            $this->requestStack->shouldReceive('getCurrentRequest')
                ->once()
                ->andReturn($request);

            $request->headers = Mockery::mock(HeaderCollection::class);
            $request->headers->shouldReceive('get')
                ->with('Origin')
                ->once()
                ->andReturn('someorigin.com');

            $response = new JsonResponse([], JsonResponse::HTTP_OK, [
                'Access-Control-Allow-Origin' => 'someorigin.com',
                'Access-Control-Allow-Methods' => $allowMethods,
                'Access-Control-Allow-Credentials' => 'true'
            ]);
            $this->assertEquals($this->controller->$method(...$args), $response);
        }
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::getSectionInfo
     */
    public function it_gets_section_info_of_a_section_without_relationships()
    {
        $sectionName = 'Sexy';
        $sectionHandle = 'sexyHandle';
        $section = Mockery::mock(SectionInterface::class);

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
            ->twice()
            ->andReturn($sectionConfig);

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

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->twice()
            ->andReturn($request);

        $entryMock = Mockery::mock(new SomeSectionEntity())->makePartial();

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

        $response = $this->controller->getSectionInfo('sexyHandle');
        $this->assertEquals($expectedResponse, $response);
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::getSectionInfo
     */
    public function it_does_not_find_sections()
    {
        $request = new Request([], [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->once()
            ->andReturn($request);

        $this->sectionManager->shouldReceive('readByHandle')
            ->once()
            ->andThrow(SectionNotFoundException::class, 'Section not found');

        $expectedResponse = new JsonResponse(['error' => 'Section not found'], 404, [
            'Access-Control-Allow-Origin' => 'iamtheorigin.com',
            'Access-Control-Allow-Credentials' => 'true'
        ]);

        $response = $this->controller->getSectionInfo('foo');
        $this->assertEquals($expectedResponse, $response);
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::getSectionInfo
     */
    public function it_fails_finding_sections_for_another_reason()
    {
        $request = new Request([], [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->once()
            ->andReturn($request);

        $this->sectionManager->shouldReceive('readByHandle')
            ->once()
            ->andThrow(\Exception::class, "Uh-oh");

        $expectedResponse = new JsonResponse(['error' => 'Uh-oh'], 400, [
            'Access-Control-Allow-Origin' => 'iamtheorigin.com',
            'Access-Control-Allow-Credentials' => 'true'
        ]);

        $response = $this->controller->getSectionInfo('foo');
        $this->assertEquals($expectedResponse, $response);
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::getSectionInfo
     * @runInSeparateProcess
     */
    public function it_gets_section_info_of_a_section_with_relationships()
    {
        $sectionName = 'Even more sexy';
        $sectionHandle = 'evenMoreSexy';
        $section = Mockery::mock(SectionInterface::class);

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

        $request = new Request([
            'options' => 'someRelationshipFieldHandle|limit:100|offset:0'
        ], [], [], [], [], [
            'HTTP_ORIGIN' => 'iamtheorigin.com'
        ]);

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
            ->andReturn($this->givenASetOfFieldsForASection(true));

        $sectionConfig = SectionConfig::fromArray([
            'section' => [
                'name' => 'Some section',
                'handle' => 'someHandle',
                'fields' => [
                    'someHandle',
                    'someOtherHandle',
                    'someRelationshipFieldHandle'
                ],
                'default' => 'default',
                'namespace' => 'Space',
                'sexy-field-instructions' => ['relationship' => 'getName']
            ]
        ]);
        $section->shouldReceive('getConfig')
            ->times(3)
            ->andReturn($sectionConfig);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->times(3)
            ->andReturn($request);

        $sectionEntitiesTo = new \ArrayIterator();
        $formattedRecords = $this->givenSomeFormattedToRecords();

        $this->dispatcher->shouldReceive('dispatch')
            ->once();

        foreach ($formattedRecords as $formattedRecord) {
            $section = Mockery::mock(CommonSectionInterface::class);
            $otherSection = Mockery::mock(CommonSectionInterface::class);
            $yetAnotherSection = Mockery::mock(CommonSectionInterface::class);

            $section->shouldReceive('getFoo')
                ->once()
                ->andReturn($otherSection);

            $otherSection->shouldReceive('getBar')
                ->once()
                ->andReturn($yetAnotherSection);

            $yetAnotherSection->shouldReceive('getName')
                ->once()
                ->andReturn($formattedRecord['name']);

            $section->shouldReceive('getId')
                ->once()
                ->andReturn($formattedRecord['id']);

            $section->shouldReceive('getSlug')
                ->once()
                ->andReturn($formattedRecord['slug']);

            $section->shouldReceive('getDefault')
                ->once()
                ->andReturn($formattedRecord['name']);

            $section->shouldReceive('getCreated')
                ->once()
                ->andReturn($formattedRecord['created']);

            $section->shouldReceive('getUpdated')
                ->once()
                ->andReturn($formattedRecord['updated']);

            $sectionEntitiesTo->append($section);
        }

        $expectedFieldInfo['fields'] = $this->givenASetOfFieldInfo(true);
        $expectedFieldInfo['fields'][2]['someRelationshipFieldHandle']['whatever'] = $formattedRecords;

        $expectedResponse = new JsonResponse(
            $expectedFieldInfo,
            200,
            [
                'Access-Control-Allow-Origin' => 'iamtheorigin.com',
                'Access-Control-Allow-Credentials' => 'true'
            ]
        );

        $this->readSection->shouldReceive('read')->andReturn($sectionEntitiesTo);

        $response = $this->controller->getSectionInfo('sexyHandle');

        $this->assertEquals($expectedResponse, $response);
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::getSectionInfo
     * @runInSeparateProcess
     */
    public function it_fails_getting_section_info_of_a_section_with_relationships()
    {
        $sectionName = 'Even more sexy';
        $sectionHandle = 'evenMoreSexy';
        $section = Mockery::mock(SectionInterface::class);

        $sectionConfig = SectionConfig::fromArray([
            'section' => [
                'name' => 'Some section',
                'handle' => 'someHandle',
                'fields' => [
                    'someHandle',
                    'someOtherHandle',
                    'someRelationshipFieldHandle'
                ],
                'default' => 'default',
                'namespace' => 'Space'
            ]
        ]);
        $section->shouldReceive('getConfig')
            ->times(3)
            ->andReturn($sectionConfig);

        $this->sectionManager->shouldReceive('readByHandle')
            ->once()
            ->andReturn($section);

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

        $request = new Request([], [], [], [], [], [
            'HTTP_ORIGIN' => 'iamtheorigin.com'
        ]);

        $section->shouldReceive('getName')
            ->once()
            ->andReturn(Name::fromString($sectionName));

        $section->shouldReceive('getHandle')
            ->once()
            ->andReturn(Handle::fromString($sectionHandle));

        $section->shouldReceive('getFields')
            ->once()
            ->andReturn($this->givenASetOfFieldsForASection(true));

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->times(3)
            ->andReturn($request);

        $this->dispatcher->shouldReceive('dispatch')
            ->once();

        $expectedFieldInfo['fields'] = $this->givenASetOfFieldInfo(true);
        $expectedFieldInfo['fields'][2]['someRelationshipFieldHandle']['whatever'] = ['error' => 'Entry not found'];
        $this->readSection->shouldReceive('read')->andThrow(EntryNotFoundException::class, 'Entry not found');
        $this->readSection->shouldReceive('read')->andThrow(EntryNotFoundException::class, 'Entry not found');

        $response = $this->controller->getSectionInfo('sexyHandle');
        $expectedResponse = new JsonResponse(
            $expectedFieldInfo,
            200,
            [
                'Access-Control-Allow-Origin' => 'iamtheorigin.com',
                'Access-Control-Allow-Credentials' => 'true'
            ]
        );

        $this->assertEquals($expectedResponse, $response);
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

    private function givenSomeFormattedToRecords(): array
    {
        return [
            [
                'id' => 1,
                'slug' => 'sleepy-sluggg',
                'name' => 'Sleepy Slugg',
                'created' => new \DateTime(),
                'updated' => new \DateTime(),
                'selected' => false
            ],
            [
                'id' => 2,
                'slug' => 'some-slug-slack',
                'name' => 'Some slack slug',
                'created' => new \DateTime(),
                'updated' => new \DateTime(),
                'selected' => false
            ],
            [
                'id' => 3,
                'slug' => 'slack-slug-slog',
                'name' => 'Slack slug slog',
                'created' => new \DateTime(),
                'updated' => new \DateTime(),
                'selected' => false
            ]
        ];
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
}

class SomeSectionEntity implements CommonSectionInterface {

    const FIELDS = [];

    public function getId(): ?int
    {
        // TODO: Implement getId() method.
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        // TODO: Implement loadValidatorMetadata() method.
    }

    public function onPrePersist(): void
    {
        // TODO: Implement onPrePersist() method.
    }

    public function onPreUpdate(): void
    {
        // TODO: Implement onPreUpdate() method.
    }
}

namespace Space\Entity;

class SomeHandle {

    const FIELDS = [];

}


