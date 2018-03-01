<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Api\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tardigrades\Entity\Field;
use Tardigrades\Entity\Section;
use Tardigrades\SectionField\Form\FormInterface;
use Symfony\Component\Form\FormInterface as SymfonyFormInterface;
use Tardigrades\SectionField\Generator\CommonSectionInterface;
use Tardigrades\SectionField\Service\CreateSectionInterface;
use Tardigrades\SectionField\Service\DeleteSectionInterface;
use Tardigrades\SectionField\Service\ReadOptions;
use Tardigrades\SectionField\Service\ReadSectionInterface;
use Tardigrades\SectionField\Service\SectionManagerInterface;
use Mockery;
use Tardigrades\SectionField\ValueObject\Handle;
use Tardigrades\SectionField\ValueObject\Name;

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

        $this->controller = new RestController(
            $this->createSection,
            $this->readSection,
            $this->deleteSection,
            $this->form,
            $this->sectionManager,
            $this->requestStack
        );
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::getSectionInfo
     * @runInSeparateProcess
     */
    public function it_gets_section_info()
    {
        $mockedField = Mockery::mock(new Field())->makePartial();
        $mockedField->shouldReceive('getHandle')->andReturn(Handle::fromString('FieldHandle'));

        $mockedFieldConfig = Mockery::mock('alias:Tardigrades\SectionField\ValueObject\FieldConfig')->makePartial();
        $mockedFieldConfig->shouldReceive('toArray')->andReturn(['field' => 'thisfield']);

        $mockedField->shouldReceive('getConfig')->andReturn($mockedFieldConfig);

        $mockedSection = Mockery::mock(new Section())->makePartial();
        $mockedSection->shouldReceive('getName')
            ->andReturn(Name::fromString('sexyName'));
        $mockedSection->shouldReceive('getHandle')
            ->andReturn(Handle::fromString('sexyHandle'));
        $mockedSection->shouldReceive('getFields')
            ->andReturn(new ArrayCollection([$mockedField]));

        $this->sectionManager->shouldReceive('readByHandle')->once()
            ->andReturn($mockedSection);
        $response = $this->controller->getSectionInfo('sexyHandle');
        $this->assertSame(
            '{"name":"sexyName","handle":"sexyHandle","fields":[{"FieldHandle":"thisfield"}]}',
            $response->getContent());
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::getEntryById
     * @covers ::getEntryBySlug
     * @runInSeparateProcess
     */
    public function it_should_get_entry_by_id()
    {
        $this->readSection->shouldReceive('read')
            ->andReturn(new \ArrayIterator(['albatros', 'frogfish']));

        $mockRequest = Mockery::mock(Request::class)->makePartial();
        $mockRequest->shouldReceive('get')
            ->with('fields', ['id'])
            ->andReturn('farm, dog, www');

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->andReturn($mockRequest);

        $response = $this->controller->getEntryById('sexyHandle', '90000');
        $this->assertSame('"albatros"', $response->getContent());

        $response = $this->controller->getEntryBySlug('sexyHandle', 'slug');
        $this->assertSame('"albatros"', $response->getContent());
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::getEntriesByFieldValue
     * @runInSeparateProcess
     */
    public function it_should_get_entries_by_field_value()
    {
        $sectionHandle = 'rockets';
        $fieldHandle = 'uuid';
        $fieldValue = '719d72d7-4f0c-420b-993f-969af9ad34c1';
        $offset = 0;
        $limit = 100;
        $orderBy = 'name';
        $sort = 'DESC';

        $mockRequest = Mockery::mock(Request::class)->makePartial();
        $mockRequest->shouldReceive('get')->with('value')->andReturn($fieldValue);
        $mockRequest->shouldReceive('get')->with('offset', 0)->andReturn($offset);
        $mockRequest->shouldReceive('get')->with('limit', 100)->andReturn($limit);
        $mockRequest->shouldReceive('get')->with('orderBy', 'created')->andReturn($orderBy);
        $mockRequest->shouldReceive('get')->with('sort', 'DESC')->andReturn($sort);
        $mockRequest->shouldReceive('get')->with('fields', ['id'])->andReturn('');

        $this->requestStack->shouldReceive('getCurrentRequest')->andReturn($mockRequest);

        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => $sectionHandle,
            ReadOptions::FIELD => [ $fieldHandle => $fieldValue ],
            ReadOptions::OFFSET => $offset,
            ReadOptions::LIMIT => $limit,
            ReadOptions::ORDER_BY => [ $orderBy => $sort ]
        ]);

        $this->readSection->shouldReceive('read')
            ->with(equalTo($readOptions))
            ->andReturn(new \ArrayIterator(['this', 'that']));

        $response = $this->controller->getEntriesByFieldValue($sectionHandle, $fieldHandle);

        $this->assertSame('["this","that"]', $response->getContent());
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::getEntries
     * @runInSeparateProcess
     */
    public function it_should_get_the_entries()
    {
        $mockRequest = Mockery::mock(Request::class)->makePartial();
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

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->andReturn($mockRequest);

        $this->readSection->shouldReceive('read')
            ->andReturn(new \ArrayIterator(['this', 'that']));

        $response = $this->controller->getEntries('sexy');

        $this->assertSame('["this","that"]', $response->getContent());
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::createEntry
     * @runInSeparateProcess
     */
    public function it_creates_an_entry()
    {
        $entryMock = Mockery::mock(CommonSectionInterface::class);

        $mockedForm = Mockery::mock(SymfonyFormInterface::class)->shouldDeferMissing();

        $mockedForm->shouldReceive('handleRequest')->once();
        $mockedForm->shouldReceive('isValid')->andReturn(true);
        $mockedForm->shouldReceive('getData')
            ->andReturn($entryMock);

        $this->form->shouldReceive('buildFormForSection')
            ->with('sexy', $this->requestStack, false, false)
            ->andReturn($mockedForm);

        $mockedRequest = Mockery::mock(Request::class)->makePartial();
        $mockedRequest->shouldReceive('get')->with('form')
            ->andReturn(['no']);

        $this->form->shouldReceive('hasRelationship')
            ->andReturn(['relation']);

        $this->createSection->shouldReceive('save')
            ->with($entryMock, ['relation'])
            ->once()
            ->andReturn(true);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->andReturn($mockedRequest);

        $response = $this->controller->createEntry('sexy');
        $this->assertSame(
            '{"success":true,"errors":false,"code":200}',
            $response->getContent()
        );
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::createEntry
     * @runInSeparateProcess
     */
    public function it_fails_creating_an_entry_during_save_and_returns_the_correct_response()
    {
        $entryMock = Mockery::mock(CommonSectionInterface::class);

        $mockedForm = Mockery::mock(SymfonyFormInterface::class)->shouldDeferMissing();

        $mockedForm->shouldReceive('handleRequest')->once();
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

        $this->form->shouldReceive('hasRelationship')
            ->andReturn(['relation']);

        $this->createSection->shouldReceive('save')
            ->with($entryMock, ['relation'])
            ->once()
            ->andThrow(\Exception::class, "Exception message");

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->andReturn($mockedRequest);

        $response = $this->controller->createEntry('sexy');
        $this->assertSame(
            '{"code":500,"exception":"Exception message"}',
            $response->getContent()
        );
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::createEntry
     * @runInSeparateProcess
     */
    public function it_does_not_create_an_entry_and_returns_correct_response()
    {
        $mockedForm = Mockery::mock(SymfonyFormInterface::class)->shouldDeferMissing();

        $mockedForm->shouldReceive('handleRequest')->once();
        $mockedForm->shouldReceive('isValid')->andReturn(false);
        $mockedForm->shouldReceive('getName')->andReturn('name of form');
        $mockedForm->shouldReceive('getIterator')->andReturn(new \ArrayIterator([$mockedForm]));

        $error = Mockery::mock(FormError::class)->makePartial();
        $error->shouldReceive('getMessage')->andReturn('you are wrong!');
        $mockedForm->shouldReceive('getErrors')
            ->andReturn(['one' => $error]);

        $this->form->shouldReceive('buildFormForSection')
            ->andReturn($mockedForm);

        $mockedRequest = Mockery::mock(Request::class)->makePartial();
        $mockedRequest->shouldReceive('get')->with('form')
            ->andReturn(['no']);

        $this->form->shouldReceive('hasRelationship')
            ->andReturn(['relation']);

        $this->createSection->shouldReceive('save')
            ->never();

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->andReturn($mockedRequest);

        $response = $this->controller->createEntry('sexy');
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            '{"errors":{"0":"you are wrong!","name of form":["you are wrong!"]},"code":400}',
            $response->getContent()
        );
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::updateEntryById
     * @covers ::updateEntryBySlug
     * @runInSeparateProcess
     */
    public function it_updates_entries()
    {
        $entryMock = Mockery::mock(CommonSectionInterface::class);

        $mockedForm = Mockery::mock(SymfonyFormInterface::class)->shouldDeferMissing();

        $mockedForm->shouldReceive('handleRequest')->twice();
        $mockedForm->shouldReceive('isValid')->andReturn(true);
        $mockedForm->shouldReceive('getData')
            ->andReturn($entryMock);

        $this->form->shouldReceive('buildFormForSection')
            ->twice()
            ->andReturn($mockedForm);

        $mockedRequest = Mockery::mock(Request::class)->makePartial();
        $mockedRequest->shouldReceive('get')->with('form')
            ->andReturn(['no']);

        $this->form->shouldReceive('hasRelationship')
            ->andReturn(['relation']);

        $this->createSection->shouldReceive('save')
            ->with($entryMock, ['relation'])
            ->twice()
            ->andReturn(true);

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->andReturn($mockedRequest);

        $response = $this->controller->updateEntryById('sexy', 9);
        $this->assertSame(
            '{"success":true,"errors":false,"code":200}',
            $response->getContent()
        );

        $response = $this->controller->updateEntryBySlug('sexy', 'snail');
        $this->assertSame(
            '{"success":true,"errors":false,"code":200}',
            $response->getContent()
        );
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::updateEntryById
     * @covers ::updateEntryBySlug
     * @runInSeparateProcess
     */
    public function it_does_not_update_entries_and_returns_correct_response()
    {
        $mockedForm = Mockery::mock(SymfonyFormInterface::class)->shouldDeferMissing();

        $mockedForm->shouldReceive('handleRequest')->twice();
        $mockedForm->shouldReceive('isValid')->andReturn(false);
        $mockedForm->shouldReceive('getName')->andReturn('name of form');
        $mockedForm->shouldReceive('getIterator')->andReturn(new \ArrayIterator([$mockedForm]));

        $error = Mockery::mock(FormError::class)->makePartial();
        $error->shouldReceive('getMessage')->andReturn('you are wrong!');
        $mockedForm->shouldReceive('getErrors')
            ->andReturn(['one' => $error]);

        $this->form->shouldReceive('buildFormForSection')
            ->twice()
            ->andReturn($mockedForm);

        $mockedRequest = Mockery::mock(Request::class)->makePartial();
        $mockedRequest->shouldReceive('get')->with('form')
            ->andReturn(['no']);

        $this->form->shouldReceive('hasRelationship')
            ->andReturn(['relation']);

        $this->createSection->shouldReceive('save')
            ->never();

        $this->requestStack->shouldReceive('getCurrentRequest')
            ->andReturn($mockedRequest);

        $response = $this->controller->updateEntryById('sexy', 9);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            '{"errors":{"0":"you are wrong!","name of form":["you are wrong!"]},"code":400}',
            $response->getContent()
        );

        $response = $this->controller->updateEntryBySlug('sexy', 'snail');
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            '{"errors":{"0":"you are wrong!","name of form":["you are wrong!"]},"code":400}',
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

        $this->readSection->shouldReceive('read')
            ->twice()
            ->andReturn(new \ArrayIterator([$entryMock]));

        $this->deleteSection->shouldReceive('delete')
            ->twice()
            ->with($entryMock)
            ->andReturn(true);

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
    public function it_does_not_deletes_entries_and_returns_the_correct_response()
    {
        $entryMock = Mockery::mock(CommonSectionInterface::class);

        $this->readSection->shouldReceive('read')
            ->twice()
            ->andReturn(new \ArrayIterator([$entryMock]));

        $this->deleteSection->shouldReceive('delete')
            ->twice()
            ->with($entryMock)
            ->andReturn(false);

        $response = $this->controller->deleteEntryById('notsexy', 1);
        $this->assertSame('{"success":false}', $response->getContent());

        $response = $this->controller->deleteEntryBySlug('notsexy', 'snail');
        $this->assertSame('{"success":false}', $response->getContent());
    }
}
