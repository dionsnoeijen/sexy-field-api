<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Api\Controller;

use Guzzle\Http\Message\Header\HeaderCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Tardigrades\Entity\SectionInterface;
use Tardigrades\SectionField\Api\Serializer\SerializeToArrayInterface;
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
use Tardigrades\SectionField\Service\CacheInterface;
use Tardigrades\SectionField\Service\CreateSectionInterface;
use Tardigrades\SectionField\Service\DeleteSectionInterface;
use Tardigrades\SectionField\Service\EntryNotFoundException;
use Tardigrades\SectionField\Service\ReadOptions;
use Tardigrades\SectionField\Service\ReadSectionInterface;
use Tardigrades\SectionField\Service\SectionManagerInterface;
use Mockery;
use Tardigrades\SectionField\ValueObject\SectionConfig;

/**
 * @coversDefaultClass \Tardigrades\SectionField\Api\Controller\RestAutoController
 *
 * @covers ::<private>
 * @covers ::<protected>
 */
class RestAutoControllerTest extends TestCase
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

    /** @var \HTMLPurifier|Mockery\LegacyMockInterface|Mockery\MockInterface */
    private $purifier;

    /** @var TokenStorageInterface|Mockery\MockInterface */
    private $tokenStorage;

    /** @var RestAutoController */
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
        $this->purifier = Mockery::mock(\HTMLPurifier::class);

        $this->controller = new RestAutoController(
            $this->createSection,
            $this->readSection,
            $this->deleteSection,
            $this->form,
            $this->sectionManager,
            $this->requestStack,
            $this->dispatcher,
            $this->serialize,
            $this->cache,
            $this->tokenStorage,
            $this->purifier
        );
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::getEntryByIdAction
     * @covers ::getEntryBySlugAction
     * @covers ::getEntriesByFieldValueAction
     * @covers ::getEntriesAction
     * @covers ::createEntryAction
     * @covers ::updateEntryByIdAction
     * @covers ::updateEntryBySlugAction
     * @covers ::deleteEntryByIdAction
     * @covers ::deleteEntryBySlugAction
     */
    public function it_returns_options_listings()
    {
        $allowedMethods = 'OPTIONS, GET, POST, PUT, DELETE';
        $testCases = [
            // method name,    arguments,      allowed HTTP methods
            ['getEntryByIdAction', ['foo', "0"], $allowedMethods],
            ['getEntryBySlugAction', ['foo', 'bar'], $allowedMethods],
            ['getEntriesByFieldValueAction', ['foo', 'bar'], $allowedMethods],
            ['getEntriesAction', ['foo'], $allowedMethods],
            ['createEntryAction', ['foo'], $allowedMethods],
            ['updateEntryByIdAction', ['foo', 0], $allowedMethods],
            ['updateEntryBySlugAction', ['foo', 'bar'], $allowedMethods],
            ['deleteEntryByIdAction', ['foo', 0], $allowedMethods],
            ['deleteEntryBySlugAction', ['foo', 'bar'], $allowedMethods]
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

            $expectedResponse = new JsonResponse([], JsonResponse::HTTP_OK, [
                'Access-Control-Allow-Origin' => 'someorigin.com',
                'Access-Control-Allow-Methods' => $allowMethods,
                'Access-Control-Allow-Credentials' => 'true'
            ]);

            $this->assertEquals($expectedResponse, $this->controller->$method(...$args));
        }
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::getEntryByIdAction
     * @covers ::getEntryBySlugAction
     * @covers ::getEntriesByFieldValueAction
     * @covers ::getEntriesAction
     * @covers ::deleteEntryByIdAction
     * @covers ::deleteEntryBySlugAction
     * @covers ::updateEntryByIdAction
     * @covers ::updateEntryBySlugAction
     */
    public function it_does_not_find_entries()
    {
        $testCases = [
            // method name,  arguments,     GET query, expect dispatch, expect build form
            ['getEntryByIdAction', ['foo', '10'], [], false, false],
            ['getEntryBySlugAction', ['foo', 'bar'], [], false, false],
            ['getEntriesByFieldValueAction', ['foo', 'bar'], ['value' => 23], false, false],
            ['getEntriesAction', ['foo'], [], false, false],
            ['deleteEntryByIdAction', ['foo', 12], [], true, false],
            ['deleteEntryBySlugAction', ['foo', 'bar'], [], true, false],
            ['updateEntryByIdAction', ['foo', 13], [], true, true],
            ['updateEntryBySlugAction', ['foo', 'bar'], [], true, true]
        ];
        foreach ($testCases as [$method, $args, $query, $expectDispatch, $expectBuildForm]) {

            $request = new Request($query, [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);
            $this->requestStack->shouldReceive('getCurrentRequest')->andReturn($request);
            $this->dispatcher->shouldReceive('dispatch');

            if (strpos($method, 'get') !== false) {
                $section = Mockery::mock(SectionInterface::class);
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

                $this->sectionManager->shouldReceive('readByHandle')->once()->andReturn($section);
                $this->cache->shouldReceive('start')->once();
                $this->cache->shouldReceive('isHit')->once()->andReturn(false);
            }

            $this->readSection->shouldReceive('read')
                ->once()
                ->andThrow(EntryNotFoundException::class, 'Entry not found');

            if ($expectBuildForm) {
                $this->form->shouldReceive('buildFormForSection')->once();
            }

            $response = $this->controller->$method(...$args);
            $expectedResponse = new JsonResponse([
                'error' => 'Entry not found'
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
     * @covers ::getEntryByIdAction
     * @covers ::getEntryBySlugAction
     * @covers ::getEntriesByFieldValueAction
     * @covers ::getEntriesAction
     * @covers ::deleteEntryBySlugAction
     * @covers ::deleteEntryByIdAction
     */
    public function it_fails_getting_entries_while_reading()
    {
        $testCases = [
            // method name,  arguments,     GET query, expect dispatch
            ['getEntryByIdAction', ['foo', '10'], [],        false],
            ['getEntryBySlugAction', ['foo', 'bar'], [], false],
            ['getEntriesByFieldValueAction', ['foo', 'bar'], ['value' => 23], false],
            ['getEntriesAction', ['foo'], [], false],
            ['deleteEntryBySlugAction', ['foo', 'bar'], [], true],
            ['deleteEntryByIdAction', ['foo', 247], [], true]
        ];
        foreach ($testCases as [$method, $args, $query, $expectDispatch]) {
            $request = new Request($query, [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);

            $this->requestStack
                ->shouldReceive('getCurrentRequest')
                ->andReturn($request);

            $this->dispatcher->shouldReceive('dispatch');

            if (strpos($method, 'get') !== false) {
                $section = Mockery::mock(SectionInterface::class);
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
                $this->sectionManager->shouldReceive('readByHandle')->once()->andReturn($section);
                $this->cache->shouldReceive('start')->once();
                $this->cache->shouldReceive('isHit')->once()->andReturn(false);
            }

            $this->readSection->shouldReceive('read')
                ->once()
                ->andThrow(\Exception::class, "Something exceptional happened");

            $expectedResponse = new JsonResponse(['error' => "Something exceptional happened"], 400, [
                'Access-Control-Allow-Origin' => 'iamtheorigin.com',
                'Access-Control-Allow-Credentials' => 'true'
            ]);

            $response = $this->controller->$method(...$args);
            $this->assertEquals($expectedResponse, $response);
        }
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::createEntryAction
     * @covers ::updateEntryByIdAction
     * @covers ::updateEntryBySlugAction
     */
    public function it_fails_getting_entries_while_building_a_form()
    {
        $testCases = [
            // method name,  arguments,     GET query, expect dispatch
            ['createEntryAction', ['foo'], ['baz' => 'bat'], true],
            ['updateEntryByIdAction', ['foo', 14], [], true],
            ['updateEntryBySlugAction', ['foo', 'bar'], [], true]
        ];
        foreach ($testCases as [$method, $args, $query, $expectDispatch]) {
            $request = new Request($query, [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);

            $this->requestStack->shouldReceive('getCurrentRequest')
                ->andReturn($request);

            $this->form->shouldReceive('buildFormForSection')
                ->once()
                ->andThrow(\Exception::class, "Something exceptional happened");

            $expectedResponse = new JsonResponse(['error' => "Something exceptional happened"], 400, [
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
     * @covers ::getEntryByIdAction
     * @covers ::getEntryBySlugAction
     * @covers \Tardigrades\SectionField\Api\Serializer\DepthExclusionStrategy
     */
    public function it_should_get_entry_by_id()
    {
        $request = new Request([], [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);

        $this->serialize->shouldReceive('toArray')->twice();

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->andReturn($request);

        $section = Mockery::mock(SectionInterface::class);
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
            ->twice()
            ->andReturn($sectionConfig);
        $this->sectionManager->shouldReceive('readByHandle')->twice()->andReturn($section);
        $this->cache->shouldReceive('start')->twice();
        $this->cache->shouldReceive('isHit')->twice()->andReturn(false);
        $this->cache->shouldReceive('set')->twice();

        $this->readSection
            ->shouldReceive('read')
            ->andReturn(new \ArrayIterator([Mockery::mock(CommonSectionInterface::class)]));

        $this->dispatcher->shouldReceive('dispatch');

        $response = $this->controller->getEntryByIdAction('sexyHandle', '90000');
        $this->assertSame('[]', $response->getContent());


        $response = $this->controller->getEntryBySlugAction('sexyHandle', 'slug');
        $this->assertSame('[]', $response->getContent());
    }

    /**
     * TEST IS IGNORED
     * @covers ::__construct
     * @covers ::getEntriesByFieldValueAction
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
            'fields' => 'id'
        ]);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->once()
            ->andReturn($request);

        $section = Mockery::mock(SectionInterface::class);
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
            ->twice()
            ->andReturn($sectionConfig);
        $this->sectionManager->shouldReceive('readByHandle')->twice()->andReturn($section);
        $this->cache->shouldReceive('start')->twice();
        $this->cache->shouldReceive('isHit')->twice()->andReturn(false);
        $this->cache->shouldReceive('set')->twice();

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

        $this->serialize->shouldReceive('toArray')->twice();

        $response = $this->controller->getEntriesByFieldValueAction($sectionHandle, $fieldHandle);

        $this->assertSame('[[],[]]', $response->getContent());
    }

    /**
     * @covers ::__construct
     * @covers ::getEntriesByFieldValueAction
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

        $this->serialize->shouldReceive('toArray')->twice();

        $request = new Request([
            'value' => $fieldValue,
            'offset' => $offset,
            'limit' => $limit,
            'orderBy' => $orderBy,
            'sort' => $sort,
            'fields' => 'id'
        ]);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->andReturn($request);

        $section = Mockery::mock(SectionInterface::class);
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
            ->twice()
            ->andReturn($sectionConfig);
        $this->sectionManager->shouldReceive('readByHandle')->twice()->andReturn($section);
        $this->cache->shouldReceive('start')->twice();
        $this->cache->shouldReceive('isHit')->twice()->andReturn(false);
        $this->cache->shouldReceive('set')->twice();

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

        $response = $this->controller->getEntriesByFieldValueAction($sectionHandle, $fieldHandle);

        $this->assertSame('[[],[]]', $response->getContent());
    }

    /**
     * @covers ::__construct
     * @covers ::getEntriesAction
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
            ->with('fields', null)
            ->andReturn('id');

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

        $section = Mockery::mock(SectionInterface::class);
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
            ->twice()
            ->andReturn($sectionConfig);
        $this->sectionManager->shouldReceive('readByHandle')->twice()->andReturn($section);
        $this->cache->shouldReceive('start')->twice();
        $this->cache->shouldReceive('isHit')->twice()->andReturn(false);
        $this->cache->shouldReceive('set')->twice();

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

        $this->serialize->shouldReceive('toArray')->twice();

        $response = $this->controller->getEntriesAction('sexy');

        $this->assertSame('[[],[]]', $response->getContent());
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::createEntryAction
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
                Mockery::type(ApiCreateEntry::class),
                ApiCreateEntry::NAME
            ]);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs([
                Mockery::type(ApiBeforeEntrySavedAfterValidated::class),
                ApiBeforeEntrySavedAfterValidated::NAME
            ]);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs([
                Mockery::type(ApiEntryCreated::class),
                ApiEntryCreated::NAME
            ]);

        $mockedRequest = Mockery::mock(Request::class)->makePartial();
        $mockedRequest->shouldReceive('get')
            ->with('form')
            ->andReturn(['no']);

        $this->createSection->shouldReceive('save')
            ->with($entryMock)
            ->once()
            ->andReturn(true);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->andReturn($mockedRequest);

        $this->serialize->shouldReceive('toArray')->once();

        $response = $this->controller->createEntryAction('sexy');
        $this->assertSame(
            '{"code":200,"success":true,"errors":false,"entry":[]}',
            $response->getContent()
        );
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::createEntryAction
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
                Mockery::type(ApiCreateEntry::class),
                ApiCreateEntry::NAME
            ]);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs([
                Mockery::type(ApiBeforeEntrySavedAfterValidated::class),
                ApiBeforeEntrySavedAfterValidated::NAME
            ]);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs([
                Mockery::type(ApiEntryCreated::class),
                ApiEntryCreated::NAME
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

        $response = $this->controller->createEntryAction('sexy');
        $this->assertSame(
            '{"code":500,"exception":"Something woeful occurred"}',
            $response->getContent()
        );
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::createEntryAction
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
                Mockery::type(ApiCreateEntry::class),
                ApiCreateEntry::NAME
            ]);

        $this->form->shouldReceive('buildFormForSection')
            ->andReturn($mockedForm);

        $mockedRequest = Mockery::mock(Request::class)->makePartial();
        $mockedRequest->shouldReceive('get')->twice();
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

        $response = $this->controller->createEntryAction('sexy');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            '{"code":400,"errors":{"0":"you are wrong!","name of form":["you are wrong!"]}}',
            $response->getContent()
        );
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::updateEntryByIdAction
     * @covers ::updateEntryBySlugAction
     */
    public function it_updates_entries()
    {
        $request = new Request([], [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->times(4)
            ->andReturn($request);

        $this->serialize->shouldReceive('toArray')->twice();

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
            ->withArgs([
                Mockery::type(ApiUpdateEntry::class),
                ApiUpdateEntry::NAME
            ]);

        $this->dispatcher->shouldReceive('dispatch')
            ->twice()
            ->withArgs([
                Mockery::type(ApiBeforeEntryUpdatedAfterValidated::class),
                ApiBeforeEntryUpdatedAfterValidated::NAME
            ]);

        $this->dispatcher->shouldReceive('dispatch')
            ->twice()
            ->withArgs([
                Mockery::type(ApiEntryUpdated::class),
                ApiEntryUpdated::NAME
            ]);


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

        $response = $this->controller->updateEntryByIdAction('sexy', 9);
        $this->assertSame(
            '{"code":200,"success":true,"errors":false,"entry":[]}',
            $response->getContent()
        );

        $response = $this->controller->updateEntryBySlugAction('sexy', 'snail');
        $this->assertSame(
            '{"code":200,"success":true,"errors":false,"entry":[]}',
            $response->getContent()
        );
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::updateEntryByIdAction
     * @covers ::updateEntryBySlugAction
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
        $mockedRequest->request = Mockery::mock(ParameterBag::class)->makePartial();
        $mockedRequest->shouldReceive('get')
            ->with('form')
            ->andReturn(['no']);

        $mockedRequest->shouldReceive('get')
            ->with('abort')
            ->andReturn(null);

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
            ->withArgs([
                Mockery::type(ApiUpdateEntry::class),
                ApiUpdateEntry::NAME
            ]);

        $response = $this->controller->updateEntryByIdAction('sexy', 9);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            '{"code":400,"errors":{"0":"you are wrong!","name of form":["you are wrong!"]}}',
            $response->getContent()
        );

        $response = $this->controller->updateEntryBySlugAction('sexy', 'snail');
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            '{"code":400,"errors":{"0":"you are wrong!","name of form":["you are wrong!"]}}',
            $response->getContent()
        );
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::deleteEntryByIdAction
     * @covers ::deleteEntryBySlugAction
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
            ->withArgs([
                Mockery::type(ApiEntryDeleted::class),
                ApiEntryDeleted::NAME
            ]);

        $this->dispatcher->shouldReceive('dispatch')
            ->twice()
            ->withArgs([
                Mockery::type(ApiDeleteEntry::class),
                ApiDeleteEntry::NAME
            ]);

        $response = $this->controller->deleteEntryByIdAction('notsexy', 1);
        $this->assertSame('{"success":true}', $response->getContent());

        $response = $this->controller->deleteEntryBySlugAction('notsexy', 'snail');
        $this->assertSame('{"success":true}', $response->getContent());
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::deleteEntryByIdAction
     * @covers ::deleteEntryBySlugAction
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
            ->withArgs([
                Mockery::type(ApiEntryDeleted::class),
                ApiEntryDeleted::NAME
            ]);

        $this->dispatcher->shouldReceive('dispatch')
            ->twice()
            ->withArgs([
                Mockery::type(ApiDeleteEntry::class),
                ApiDeleteEntry::NAME
            ]);

        $response = $this->controller->deleteEntryByIdAction('notsexy', 1);
        $this->assertSame('{"success":false}', $response->getContent());

        $response = $this->controller->deleteEntryBySlugAction('notsexy', 'snail');
        $this->assertSame('{"success":false}', $response->getContent());
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::updateEntryBySlugAction
     */
    public function it_should_abort_with_abort_flag_on_update()
    {
        $request = new Request([], [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);
        $request->setMethod('POST');
        $request->request->set('abort', 409);

        $this->requestStack->shouldReceive('getCurrentRequest')->andReturn($request);

        $this->dispatcher->shouldReceive('dispatch');

        $response = $this->controller->updateEntryBySlugAction('sectionHandle', 'slug');

        $this->assertSame($response->getStatusCode(), 409);
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::createEntryAction
     */
    public function it_should_abort_with_abort_flag_on_create()
    {
        $request = new Request([], [], [], [], [], ['HTTP_ORIGIN' => 'iamtheorigin.com']);
        $request->setMethod('POST');
        $request->request->set('abort', 409);

        $this->requestStack->shouldReceive('getCurrentRequest')->andReturn($request);

        $this->dispatcher->shouldReceive('dispatch');

        $response = $this->controller->createEntryAction('sectionHandle');

        $this->assertSame($response->getStatusCode(), 409);
    }
}
