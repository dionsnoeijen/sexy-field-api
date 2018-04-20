<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Api\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Guzzle\Http\Message\Header\HeaderCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tardigrades\Entity\Field;
use Tardigrades\Entity\FieldType;
use Tardigrades\Entity\SectionInterface;
use Tardigrades\FieldType\Relationship\Relationship;
use Tardigrades\SectionField\Event\ApiBeforeEntrySavedAfterValidated;
use Tardigrades\SectionField\Event\ApiBeforeEntryUpdatedAfterValidated;
use Tardigrades\SectionField\Event\ApiCreateEntry;
use Tardigrades\SectionField\Event\ApiDeleteEntry;
use Tardigrades\SectionField\Event\ApiEntriesFetched;
use Tardigrades\SectionField\Event\ApiEntryCreated;
use Tardigrades\SectionField\Event\ApiEntryDeleted;
use Tardigrades\SectionField\Event\ApiEntryFetched;
use Tardigrades\SectionField\Event\ApiEntryUpdated;
use Tardigrades\SectionField\Event\ApiUpdateEntry;
use Tardigrades\SectionField\Form\FormInterface;
use Symfony\Component\Form\FormInterface as SymfonyFormInterface;
use Tardigrades\SectionField\Generator\CommonSectionInterface;
use Tardigrades\SectionField\Service\CreateSectionInterface;
use Tardigrades\SectionField\Service\DeleteSectionInterface;
use Tardigrades\SectionField\Service\EntryNotFoundException;
use Tardigrades\SectionField\Service\ReadOptions;
use Tardigrades\SectionField\Service\ReadSectionInterface;
use Tardigrades\SectionField\Service\SectionManagerInterface;
use Mockery;
use Tardigrades\SectionField\Service\SectionNotFoundException;
use Tardigrades\SectionField\ValueObject\Handle;
use Tardigrades\SectionField\ValueObject\Name;
use Tardigrades\SectionField\ValueObject\SectionConfig;

/**
 * @coversDefaultClass Tardigrades\SectionField\Api\Controller\RestController
 * @covers ::<private>
 */
class RestControllerTest extends TestCase
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

    /** @var  RestController */
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

        $this->controller = new RestController(
            $this->createSection,
            $this->readSection,
            $this->deleteSection,
            $this->form,
            $this->sectionManager,
            $this->requestStack,
            $this->dispatcher
        );
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::getSectionInfo
     * @covers ::getEntryById
     * @covers ::getEntryBySlug
     * @covers ::getEntriesByFieldValue
     * @covers ::getEntries
     * @covers ::createEntry
     * @covers ::updateEntryById
     * @covers ::updateEntryBySlug
     * @covers ::deleteEntryById
     * @covers ::deleteEntryBySlug
     */
    public function it_returns_options_listings()
    {
        $testCases = [
            // method name,    arguments,      allowed HTTP methods
            ['getSectionInfo', ['foo', "0"], 'OPTIONS, GET'],
            ['getEntryById', ['foo', "0"], 'OPTIONS, GET'],
            ['getEntryBySlug', ['foo', 'bar'], 'OPTIONS, GET'],
            ['getEntriesByFieldValue', ['foo', 'bar'], 'OPTIONS, GET'],
            ['getEntries', ['foo'], 'OPTIONS, GET'],
            ['createEntry', ['foo'], 'OPTIONS, POST'],
            ['updateEntryById', ['foo', 0], 'OPTIONS, PUT'],
            ['updateEntryBySlug', ['foo', 'bar'], 'OPTIONS, PUT'],
            ['deleteEntryById', ['foo', 0], 'OPTIONS, DELETE'],
            ['deleteEntryBySlug', ['foo', 'bar'], 'OPTIONS, DELETE']
        ];
        foreach ($testCases as [$method, $args, $allowMethods]) {
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('getMethod')
                ->andReturn('options');
            $this->requestStack->shouldReceive('getCurrentRequest')
                ->once()
                ->andReturn($request);
            $response = new JsonResponse([], JsonResponse::HTTP_OK, [
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
        $expectedFieldInfo = [
            'name' => $sectionName,
            'handle' => $sectionHandle
        ];

        $request = new Request([], [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->once()
            ->andReturn($request);

        $entryMock = Mockery::mock(CommonSectionInterface::class);

        $mockedForm = Mockery::mock(SymfonyFormInterface::class)->shouldDeferMissing();
        $mockedForm->shouldReceive('getData')
            ->once()
            ->andReturn($entryMock);

        $this->form->shouldReceive('buildFormForSection')
            ->once()
            ->with($sectionHandle, $this->requestStack, null, false)
            ->andReturn($mockedForm);

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

        $sectionConfig = SectionConfig::fromArray([
            'section' => [
                'name' => 'Some section',
                'handle' => 'Some handle',
                'fields' => [
                    'someHandle',
                    'someOtherHandle'
                ],
                'default' => 'default',
                'namespace' => 'NameSpace'
            ]
        ]);
        $section->shouldReceive('getConfig')
            ->once()
            ->andReturn($sectionConfig);

        $expectedFieldInfo['fields'] = $fields;

        $expectedFieldInfo = array_merge($expectedFieldInfo, $sectionConfig->toArray());

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
            ->andThrow(SectionNotFoundException::class);

        $expectedResponse = new JsonResponse(['message' => 'Section not found'], 404, [
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

        $expectedResponse = new JsonResponse(['message' => 'Uh-oh'], 400, [
            'Access-Control-Allow-Origin' => 'iamtheorigin.com',
            'Access-Control-Allow-Credentials' => 'true'
        ]);

        $response = $this->controller->getSectionInfo('foo');
        $this->assertEquals($expectedResponse, $response);
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::getEntryById
     * @covers ::getEntryBySlug
     * @covers ::getEntriesByFieldValue
     * @covers ::getEntries
     * @covers ::deleteEntryById
     * @covers ::deleteEntryBySlug
     * @covers ::updateEntryById
     * @covers ::updateEntryBySlug
     */
    public function it_does_not_find_entries()
    {
        $testCases = [
            // method name,  arguments,     GET query, expect dispatch, expect build form
            ['getEntryById', ['foo', '10'], [], false, false],
            ['getEntryBySlug', ['foo', 'bar'], [], false, false],
            ['getEntriesByFieldValue', ['foo', 'bar'], ['value' => 23], false, false],
            ['getEntries', ['foo'], [], false, false],
            ['deleteEntryById', ['foo', 12], [], true, false],
            ['deleteEntryBySlug', ['foo', 'bar'], [], true, false],
            ['updateEntryById', ['foo', 13], [], true, true],
            ['updateEntryBySlug', ['foo', 'bar'], [], true, true]
        ];
        foreach ($testCases as [$method, $args, $query, $expectDispatch, $expectBuildForm]) {
            $request = new Request($query, [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);

            $this->requestStack->shouldReceive('getCurrentRequest')->andReturn($request);
            $this->readSection->shouldReceive('read')
                ->once()
                ->andThrow(EntryNotFoundException::class);


            if ($expectDispatch) {
                $this->dispatcher->shouldReceive('dispatch')->once();
            }
            if ($expectBuildForm) {
                $this->form->shouldReceive('buildFormForSection')->once();
            }

            $response = $this->controller->$method(...$args);
            $expectedResponse = new JsonResponse([
                'message' => 'Entry not found'
            ], 404, [
                'Access-Control-Allow-Origin' => 'iamtheorigin.com',
                'Access-Control-Allow-Credentials' => 'true'
            ]);
            $this->assertEquals($expectedResponse, $response);
        }
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::getEntryById
     * @covers ::getEntryBySlug
     * @covers ::getEntriesByFieldValue
     * @covers ::getEntries
     * @covers ::deleteEntryBySlug
     * @covers ::deleteEntryById
     */
    public function it_fails_getting_entries_while_reading()
    {
        $testCases = [
            // method name,  arguments,     GET query, expect dispatch
            ['getEntryById', ['foo', '10'], [],        false],
            ['getEntryBySlug', ['foo', 'bar'], [], false],
            ['getEntriesByFieldValue', ['foo', 'bar'], ['value' => 23], false],
            ['getEntries', ['foo'], [], false],
            ['deleteEntryBySlug', ['foo', 'bar'], [], true],
            ['deleteEntryById', ['foo', 247], [], true]
        ];
        foreach ($testCases as [$method, $args, $query, $expectDispatch]) {
            $request = new Request($query, [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);

            $this->requestStack->shouldReceive('getCurrentRequest')
                ->once()
                ->andReturn($request);

            $this->readSection->shouldReceive('read')
                ->once()
                ->andThrow(\Exception::class, "Something exceptional happened");


            $expectedResponse = new JsonResponse(['message' => "Something exceptional happened"], 400, [
                'Access-Control-Allow-Origin' => 'iamtheorigin.com',
                'Access-Control-Allow-Credentials' => 'true'
            ]);

            if ($expectDispatch) {
                $this->dispatcher->shouldReceive('dispatch')->once();
            }

            $response = $this->controller->$method(...$args);
            $this->assertEquals($expectedResponse, $response);
        }
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::createEntry
     * @covers ::updateEntryById
     * @covers ::updateEntryBySlug
     */
    public function it_fails_getting_entries_while_building_a_form()
    {
        $testCases = [
            // method name,  arguments,     GET query, expect dispatch
            ['createEntry', ['foo'], ['baz' => 'bat'], true],
            ['updateEntryById', ['foo', 14], [], true],
            ['updateEntryBySlug', ['foo', 'bar'], [], true]
        ];
        foreach ($testCases as [$method, $args, $query, $expectDispatch]) {
            $request = new Request($query, [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);

            $this->requestStack->shouldReceive('getCurrentRequest')
                ->andReturn($request);

            $this->form->shouldReceive('buildFormForSection')
                ->once()
                ->andThrow(\Exception::class, "Something exceptional happened");

            $expectedResponse = new JsonResponse(['message' => "Something exceptional happened"], 400, [
                'Access-Control-Allow-Origin' => 'iamtheorigin.com',
                'Access-Control-Allow-Credentials' => 'true'
            ]);

            if ($expectDispatch) {
                $this->dispatcher->shouldReceive('dispatch')->once();
            }

            $response = $this->controller->$method(...$args);
            $this->assertEquals($expectedResponse, $response);
        }
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

        $expectedFieldInfo = [
            'name' => $sectionName,
            'handle' => $sectionHandle
        ];

        $request = new Request([
            'options' => 'someRelationshipFieldHandle|limit:100|offset:0'
        ], [], [], [], [], [
            'HTTP_ORIGIN' => 'iamtheorigin.com'
        ]);

        $entryMock = Mockery::mock(CommonSectionInterface::class);

        $mockedForm = Mockery::mock(SymfonyFormInterface::class)->shouldDeferMissing();
        $mockedForm->shouldReceive('getData')
            ->once()
            ->andReturn($entryMock);

        $this->form->shouldReceive('buildFormForSection')
            ->once()
            ->andReturn($mockedForm);

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
                'handle' => 'Some handle',
                'fields' => [
                    'someHandle',
                    'someOtherHandle',
                    'someRelationshipFieldHandle'
                ],
                'default' => 'default',
                'namespace' => 'NameSpace',
                'sexy-field-instructions' => ['relationship' => 'getName']
            ]
        ]);
        $section->shouldReceive('getConfig')
            ->once()
            ->andReturn($sectionConfig);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->once()
            ->andReturn($request);

        $sectionEntitiesTo = new \ArrayIterator();
        $formattedRecords = $this->givenSomeFormattedToRecords();

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

        $expectedFieldInfo = array_merge($expectedFieldInfo, $sectionConfig->toArray());

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

        $expectedFieldInfo = [
            'name' => $sectionName,
            'handle' => $sectionHandle
        ];

        $request = new Request([], [], [], [], [], [
            'HTTP_ORIGIN' => 'iamtheorigin.com'
        ]);

        $entryMock = Mockery::mock(CommonSectionInterface::class);
        $mockedForm = Mockery::mock(SymfonyFormInterface::class)->shouldDeferMissing();
        $mockedForm->shouldReceive('getData')
            ->once()
            ->andReturn($entryMock);

        $this->form->shouldReceive('buildFormForSection')
            ->once()
            ->andReturn($mockedForm);

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
                'handle' => 'Some handle',
                'fields' => [
                    'someHandle',
                    'someOtherHandle',
                    'someRelationshipFieldHandle'
                ],
                'default' => 'default',
                'namespace' => 'NameSpace'
            ]
        ]);
        $section->shouldReceive('getConfig')
            ->once()
            ->andReturn($sectionConfig);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->once()
            ->andReturn($request);

        $expectedFieldInfo['fields'] = $this->givenASetOfFieldInfo(true);
        $expectedFieldInfo['fields'][2]['someRelationshipFieldHandle']['whatever'] = ['error' => 'Entry not found'];

        $expectedFieldInfo = array_merge($expectedFieldInfo, $sectionConfig->toArray());


        $this->readSection->shouldReceive('read')->andThrow(EntryNotFoundException::class);

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

    /**
     * @test
     * @covers ::__construct
     * @covers ::getEntryById
     * @covers ::getEntryBySlug
     * @covers \Tardigrades\SectionField\Api\Serializer\DepthExclusionStrategy
     */
    public function it_should_get_entry_by_id()
    {
        $request = new Request([], [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->andReturn($request);

        $this->readSection
            ->shouldReceive('read')
            ->andReturn(new \ArrayIterator([Mockery::mock(CommonSectionInterface::class)]));

        $this->dispatcher->shouldReceive('dispatch')
            ->twice()
            ->withArgs([
                ApiEntryFetched::NAME,
                Mockery::type(ApiEntryFetched::class)
            ]);

        $response = $this->controller->getEntryById('sexyHandle', '90000');
        $this->assertSame('[]', $response->getContent());

        $response = $this->controller->getEntryBySlug('sexyHandle', 'slug');
        $this->assertSame('[]', $response->getContent());
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::getEntriesByFieldValue
     * @covers \Tardigrades\SectionField\Api\Serializer\DepthExclusionStrategy
     */
    public function it_should_get_entries_by_field_value()
    {
        $sectionHandle = 'rockets';
        $fieldHandle = 'uuid';
        $fieldValue = '719d72d7-4f0c-420b-993f-969af9ad34c1';
        $offset = 0;
        $limit = 100;
        $orderBy = 'name';
        $sort = 'desc';

        $request = new Request([
            'value' => $fieldValue,
            'offset' => $offset,
            'limit' => $limit,
            'orderBy' => $orderBy,
            'sort' => $sort,
            'fields' => ['id']
        ]);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->once()
            ->andReturn($request);

        $this->readSection->shouldReceive('read')
            ->once()
            ->andReturn(
                new \ArrayIterator([
                    Mockery::mock(CommonSectionInterface::class),
                    Mockery::mock(CommonSectionInterface::class)
                ])
            );

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs([
                ApiEntriesFetched::NAME,
                Mockery::type(ApiEntriesFetched::class)
            ]);

        $response = $this->controller->getEntriesByFieldValue($sectionHandle, $fieldHandle);

        $this->assertSame('[[],[]]', $response->getContent());
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::getEntriesByFieldValue
     * @covers \Tardigrades\SectionField\Api\Serializer\DepthExclusionStrategy
     */
    public function it_should_get_entries_by_multiple_field_values()
    {
        $sectionHandle = 'rockets';
        $fieldHandle = 'uuid';
        $fieldValue = '719d72d7-4f0c-420b-993f-969af9ad34c1,9d716145-eef6-442c-acea-93acf3990b6d';
        $offset = 0;
        $limit = 100;
        $orderBy = 'name';
        $sort = 'desc';

        $request = new Request([
            'value' => $fieldValue,
            'offset' => $offset,
            'limit' => $limit,
            'orderBy' => $orderBy,
            'sort' => $sort,
            'fields' => ['id']
        ]);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->once()
            ->andReturn($request);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs([
                ApiEntriesFetched::NAME,
                Mockery::type(ApiEntriesFetched::class)
            ]);

        $this->readSection->shouldReceive('read')
            ->andReturn(new \ArrayIterator([
                Mockery::mock(CommonSectionInterface::class),
                Mockery::mock(CommonSectionInterface::class)
            ]));

        $response = $this->controller->getEntriesByFieldValue($sectionHandle, $fieldHandle);

        $this->assertSame('[[],[]]', $response->getContent());
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::getEntries
     * @covers \Tardigrades\SectionField\Api\Serializer\DepthExclusionStrategy
     */
    public function it_should_get_the_entries()
    {
        $mockRequest = Mockery::mock(Request::class)->makePartial();
        $mockRequest->shouldReceive('getMethod')
            ->once()
            ->andReturn('NOT_OPTIONS');

        $mockRequest->shouldReceive('get')
            ->with('offset', 0)
            ->andReturn(10);

        $mockRequest->shouldReceive('get')
            ->with('limit', 100)
            ->andReturn(1);

        $mockRequest->shouldReceive('get')
            ->with('orderBy', 'created')
            ->andReturn('name');

        $mockRequest->shouldReceive('get')
            ->with('sort', 'DESC')
            ->andReturn('DESC');

        $mockRequest->shouldReceive('get')
            ->with('fields', ['id'])
            ->andReturn('');

        $mockRequest->shouldReceive('get')
            ->with('depth', 20)
            ->andReturn(20);

        $mockRequest->headers = Mockery::mock(HeaderCollection::class);
        $mockRequest->headers->shouldReceive('get')
            ->with('Origin')
            ->once()
            ->andReturn('someorigin.com');

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->andReturn($mockRequest);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs([
                ApiEntriesFetched::NAME,
                Mockery::type(ApiEntriesFetched::class)
            ])
        ;

        $this->readSection->shouldReceive('read')
            ->andReturn(new \ArrayIterator([
                    Mockery::mock(CommonSectionInterface::class),
                    Mockery::mock(CommonSectionInterface::class)
                ])
            );

        $response = $this->controller->getEntries('sexy');

        $this->assertSame('[[],[]]', $response->getContent());
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::createEntry
     */
    public function it_creates_an_entry()
    {
        $request = new Request([], [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);

        $this->requestStack
            ->shouldReceive('getCurrentRequest')
            ->once()
            ->andReturn($request);

        $entryMock = Mockery::mock(CommonSectionInterface::class);

        $mockedForm = Mockery::mock(SymfonyFormInterface::class)->shouldDeferMissing();

        $mockedForm->shouldReceive('submit')->once();
        $mockedForm->shouldReceive('getName')->once();
        $mockedForm->shouldReceive('isValid')
            ->andReturn(true);
        $mockedForm->shouldReceive('getData')
            ->andReturn($entryMock);

        $this->form->shouldReceive('buildFormForSection')
            ->with('sexy', $this->requestStack, false, false)
            ->andReturn($mockedForm);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs([
                ApiCreateEntry::NAME,
                Mockery::type(ApiCreateEntry::class)
            ]);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs([
                ApiBeforeEntrySavedAfterValidated::NAME,
                Mockery::type(ApiBeforeEntrySavedAfterValidated::class)
            ]);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs([
                ApiEntryCreated::NAME,
                Mockery::type(ApiEntryCreated::class)
            ]);

        $mockedRequest = Mockery::mock(Request::class)->makePartial();
        $mockedRequest->shouldReceive('get')->with('form')
            ->andReturn(['no']);

        $this->createSection->shouldReceive('save')
            ->with($entryMock)
            ->once()
            ->andReturn(true);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->andReturn($mockedRequest);

        $response = $this->controller->createEntry('sexy');
        $this->assertSame(
            '{"code":200,"success":true,"errors":false}',
            $response->getContent()
        );
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::createEntry
     */
    public function it_fails_creating_an_entry_during_save_and_returns_the_correct_response()
    {
        $request = new Request([], [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->once()
            ->andReturn($request);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs([
                ApiCreateEntry::NAME,
                Mockery::type(ApiCreateEntry::class)
            ]);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs([
                ApiBeforeEntrySavedAfterValidated::NAME,
                Mockery::type(ApiBeforeEntrySavedAfterValidated::class)
            ]);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs([
                ApiEntryCreated::NAME,
                Mockery::type(ApiEntryCreated::class)
            ]);

        $entryMock = Mockery::mock(CommonSectionInterface::class);

        $mockedForm = Mockery::mock(SymfonyFormInterface::class)->shouldDeferMissing();

        $mockedForm->shouldReceive('submit')->once();
        $mockedForm->shouldReceive('getName')->once();
        $mockedForm->shouldReceive('isValid')->andReturn(true);
        $mockedForm->shouldReceive('getData')
            ->andReturn($entryMock);

        $this->form->shouldReceive('buildFormForSection')
            ->with('sexy', $this->requestStack, false, false)
            ->once()
            ->andReturn($mockedForm);

        $mockedRequest = Mockery::mock(Request::class)->makePartial();
        $mockedRequest->shouldReceive('get')->with('form')
            ->andReturn(['no']);

        $this->createSection->shouldReceive('save')
            ->with($entryMock)
            ->once()
            ->andThrow(\Exception::class, "Something woeful occurred");

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->andReturn($mockedRequest);

        $response = $this->controller->createEntry('sexy');
        $this->assertSame(
            '{"code":500,"exception":"Something woeful occurred"}',
            $response->getContent()
        );
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::createEntry
     */
    public function it_does_not_create_an_entry_and_returns_correct_response()
    {
        $mockedForm = Mockery::mock(SymfonyFormInterface::class)->shouldDeferMissing();

        $mockedForm->shouldReceive('submit')->once();
        $mockedForm->shouldReceive('getName')->once();
        $mockedForm->shouldReceive('isValid')->andReturn(false);
        $mockedForm->shouldReceive('getName')->andReturn('name of form');
        $mockedForm->shouldReceive('getIterator')->andReturn(new \ArrayIterator([$mockedForm]));

        $error = Mockery::mock(FormError::class)->makePartial();
        $error->shouldReceive('getMessage')->andReturn('you are wrong!');
        $mockedForm->shouldReceive('getErrors')
            ->andReturn([$error]);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs([
                ApiCreateEntry::NAME,
                Mockery::type(ApiCreateEntry::class)
            ]);

        $this->form->shouldReceive('buildFormForSection')
            ->andReturn($mockedForm);

        $mockedRequest = Mockery::mock(Request::class)->makePartial();
        $mockedRequest->shouldReceive('get')->once();
        $mockedRequest->shouldReceive('getMethod')
            ->andReturn('not options');

        $mockedRequest->headers = Mockery::mock(HeaderBag::class);
        $mockedRequest->headers
            ->shouldReceive('get')
            ->with('Origin')
            ->andReturn('Some origin');

        $this->createSection
            ->shouldReceive('save')
            ->never();

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->andReturn($mockedRequest);

        $response = $this->controller->createEntry('sexy');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            '{"code":400,"errors":{"0":"you are wrong!","name of form":["you are wrong!"]}}',
            $response->getContent()
        );
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::updateEntryById
     * @covers ::updateEntryBySlug
     */
    public function it_updates_entries()
    {
        $request = new Request([], [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->times(4)
            ->andReturn($request);

        $originalEntryMock = Mockery::mock(CommonSectionInterface::class);
        $iteratorMock = Mockery::mock(\ArrayIterator::class);
        $iteratorMock->shouldReceive('current')
            ->twice()
            ->andReturn($originalEntryMock);

        $newEntryMock = Mockery::mock(CommonSectionInterface::class);
        $this->readSection->shouldReceive('read')
            ->twice()
            ->with(
                Mockery::on(
                    function (ReadOptions $readOptions) {
                        $this->assertSame('sexy', (string)$readOptions->getSection()[0]);
                        if ($readOptions->getId()) {
                            $this->assertSame(9, $readOptions->getId()->toInt());
                        } elseif ($readOptions->getSlug()) {
                            $this->assertSame('snail', (string)$readOptions->getSlug());
                        }

                        return true;
                    }
                )
            )
            ->andReturn($iteratorMock);

        $this->dispatcher->shouldReceive('dispatch')
            ->twice()
            ->withArgs([ApiUpdateEntry::NAME, Mockery::type(ApiUpdateEntry::class)]);

        $this->dispatcher->shouldReceive('dispatch')
            ->twice()
            ->withArgs([
                ApiBeforeEntryUpdatedAfterValidated::NAME,
                Mockery::type(ApiBeforeEntryUpdatedAfterValidated::class)]
            );

        $this->dispatcher->shouldReceive('dispatch')
            ->twice()
            ->withArgs([ApiEntryUpdated::NAME, Mockery::type(ApiEntryUpdated::class)]);


        $mockedForm = Mockery::mock(SymfonyFormInterface::class)->shouldDeferMissing();
        $mockedForm->shouldReceive('submit')->twice();
        $mockedForm->shouldReceive('getName')->twice();
        $mockedForm->shouldReceive('isValid')->andReturn(true);
        $mockedForm->shouldReceive('getData')
            ->andReturn($newEntryMock);

        $this->form->shouldReceive('buildFormForSection')
            ->twice()
            ->andReturn($mockedForm);

        $this->createSection->shouldReceive('save')
            ->with($newEntryMock)
            ->twice()
            ->andReturn(true);

        $response = $this->controller->updateEntryById('sexy', 9);
        $this->assertSame(
            '{"code":200,"success":true,"errors":false}',
            $response->getContent()
        );

        $response = $this->controller->updateEntryBySlug('sexy', 'snail');
        $this->assertSame(
            '{"code":200,"success":true,"errors":false}',
            $response->getContent()
        );
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::updateEntryById
     * @covers ::updateEntryBySlug
     */
    public function it_does_not_update_entries_and_returns_correct_response()
    {
        $mockedForm = Mockery::mock(SymfonyFormInterface::class)->shouldDeferMissing();

        $mockedForm->shouldReceive('handleRequest')->never();
        $mockedForm->shouldReceive('isValid')->andReturn(false);
        $mockedForm->shouldReceive('getName')->andReturn('name of form');
        $mockedForm->shouldReceive('getIterator')->andReturn(new \ArrayIterator([$mockedForm]));
        $mockedForm->shouldReceive('submit')->with('foo', false);

        $originalEntryMock = Mockery::mock(CommonSectionInterface::class);
        $iteratorMock = Mockery::mock(\ArrayIterator::class);
        $iteratorMock->shouldReceive('current')
            ->twice()
            ->andReturn($originalEntryMock);

        $this->readSection->shouldReceive('read')
            ->twice()
            ->with(
                Mockery::on(
                    function (ReadOptions $readOptions) {
                        $this->assertSame('sexy', (string)$readOptions->getSection()[0]);
                        if ($readOptions->getId()) {
                            $this->assertSame(9, $readOptions->getId()->toInt());
                        } elseif ($readOptions->getSlug()) {
                            $this->assertSame('snail', (string)$readOptions->getSlug());
                        }

                        return true;
                    }
                )
            )
            ->andReturn($iteratorMock);

        $error = Mockery::mock(FormError::class)->makePartial();
        $error->shouldReceive('getMessage')->andReturn('you are wrong!');
        $mockedForm->shouldReceive('getErrors')
            ->andReturn([$error]);

        $this->form->shouldReceive('buildFormForSection')
            ->twice()
            ->andReturn($mockedForm);

        $mockedRequest = Mockery::mock(Request::class)->makePartial();
        $mockedRequest->shouldReceive('get')->with('form')
            ->andReturn(['no']);
        $mockedRequest->shouldReceive('getMethod')
            ->andReturn('not options');
        $mockedRequest->shouldReceive('get')
            ->with('name of form')
            ->andReturn('foo');

        $mockedRequest->headers = Mockery::mock(HeaderBag::class);
        $mockedRequest->headers->shouldReceive('get')->with('Origin')
            ->andReturn('Some origin');

        $this->createSection->shouldReceive('save')
            ->never();

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->andReturn($mockedRequest);

        $this->dispatcher->shouldReceive('dispatch')
            ->twice()
            ->withArgs([ApiUpdateEntry::NAME, Mockery::type(ApiUpdateEntry::class)]);

        $response = $this->controller->updateEntryById('sexy', 9);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            '{"code":400,"errors":{"0":"you are wrong!","name of form":["you are wrong!"]}}',
            $response->getContent()
        );

        $response = $this->controller->updateEntryBySlug('sexy', 'snail');
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            '{"code":400,"errors":{"0":"you are wrong!","name of form":["you are wrong!"]}}',
            $response->getContent()
        );
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::deleteEntryById
     * @covers ::deleteEntryBySlug
     * @runInSeparateProcess
     */
    public function it_deletes_entries()
    {
        $entryMock = Mockery::mock(CommonSectionInterface::class);

        $request = new Request([], [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->twice()
            ->andReturn($request);

        $this->readSection->shouldReceive('read')
            ->twice()
            ->andReturn(new \ArrayIterator([$entryMock]));

        $this->deleteSection->shouldReceive('delete')
            ->twice()
            ->with($entryMock)
            ->andReturn(true);

        $this->dispatcher->shouldReceive('dispatch')
            ->twice()
            ->withArgs([ApiEntryDeleted::NAME, Mockery::type(ApiEntryDeleted::class)]);

        $this->dispatcher->shouldReceive('dispatch')
            ->twice()
            ->withArgs([ApiDeleteEntry::NAME, Mockery::type(ApiDeleteEntry::class)]);

        $response = $this->controller->deleteEntryById('notsexy', 1);
        $this->assertSame('{"success":true}', $response->getContent());

        $response = $this->controller->deleteEntryBySlug('notsexy', 'snail');
        $this->assertSame('{"success":true}', $response->getContent());
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::deleteEntryById
     * @covers ::deleteEntryBySlug
     * @runInSeparateProcess
     */
    public function it_does_not_delete_entries_and_return_the_correct_response()
    {
        $entryMock = Mockery::mock(CommonSectionInterface::class);

        $request = new Request([], [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->twice()
            ->andReturn($request);

        $this->readSection->shouldReceive('read')
            ->twice()
            ->andReturn(new \ArrayIterator([$entryMock]));

        $this->deleteSection->shouldReceive('delete')
            ->twice()
            ->with($entryMock)
            ->andReturn(false);

        $this->dispatcher->shouldReceive('dispatch')
            ->twice()
            ->withArgs([ApiEntryDeleted::NAME, Mockery::type(ApiEntryDeleted::class)]);

        $this->dispatcher->shouldReceive('dispatch')
            ->twice()
            ->withArgs([ApiDeleteEntry::NAME, Mockery::type(ApiDeleteEntry::class)]);

        $response = $this->controller->deleteEntryById('notsexy', 1);
        $this->assertSame('{"success":false}', $response->getContent());

        $response = $this->controller->deleteEntryBySlug('notsexy', 'snail');
        $this->assertSame('{"success":false}', $response->getContent());
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
