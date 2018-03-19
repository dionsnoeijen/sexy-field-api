<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Api\Controller;

use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormInterface as SymfonyFormInterface;
use Tardigrades\Entity\FieldInterface;
use Tardigrades\SectionField\Api\Serializer\FieldsExclusionStrategy;
use Tardigrades\SectionField\Service\CreateSectionInterface;
use Tardigrades\SectionField\Service\DeleteSectionInterface;
use Tardigrades\SectionField\Form\FormInterface;
use Tardigrades\SectionField\Service\ReadSectionInterface;
use Tardigrades\SectionField\Service\SectionManagerInterface;
use Tardigrades\SectionField\Service\ReadOptions;
use Tardigrades\SectionField\ValueObject\Handle;
use Tardigrades\SectionField\ValueObject\SectionFormOptions;

/**
 * Class RestController
 *
 * The REST Controller provides a simple REST implementation for Sections.
 *
 * @package Tardigrades\SectionField\Api\Controller
 */
class RestController implements RestControllerInterface
{
    /** @var ReadSectionInterface */
    private $readSection;

    /** @var CreateSectionInterface */
    private $createSection;

    /** @var DeleteSectionInterface */
    private $deleteSection;

    /** @var FormInterface */
    private $form;

    /** @var SectionManagerInterface */
    private $sectionManager;

    /** @var RequestStack */
    private $requestStack;

    /**
     * RestController constructor.
     * @param CreateSectionInterface $createSection
     * @param ReadSectionInterface $readSection
     * @param DeleteSectionInterface $deleteSection
     * @param FormInterface $form
     * @param SectionManagerInterface $sectionManager
     * @param RequestStack $requestStack
     */
    public function __construct(
        CreateSectionInterface $createSection,
        ReadSectionInterface $readSection,
        DeleteSectionInterface $deleteSection,
        FormInterface $form,
        SectionManagerInterface $sectionManager,
        RequestStack $requestStack
    ) {
        $this->readSection = $readSection;
        $this->createSection = $createSection;
        $this->deleteSection = $deleteSection;
        $this->form = $form;
        $this->sectionManager = $sectionManager;
        $this->requestStack = $requestStack;
    }

    /**
     * OPTIONS (get) information about the section so you can build
     * awesome forms in your spa, or whatever you need it for.
     * @param string $sectionHandle
     * @return JsonResponse
     */
    public function getSectionInfo(string $sectionHandle): JsonResponse
    {
        $response = [];

        $section = $this->sectionManager->readByHandle(Handle::fromString($sectionHandle));

        $response['name'] = (string) $section->getName();
        $response['handle'] = (string) $section->getHandle();

        /** @var FieldInterface $field */
        foreach ($section->getFields() as $field) {
            $fieldInfo = [
                (string) $field->getHandle() => $field->getConfig()->toArray()['field']
            ];
            $response['fields'][] = $fieldInfo;
        }

        return new JsonResponse($response, 200, [
            'Access-Control-Allow-Methods' => 'OPTIONS',
            'Access-Control-Allow-Origin' => '*'
        ]);
    }

    /**
     * GET an entry by id
     * @param string $sectionHandle
     * @param string $id
     * @return Response
     */
    public function getEntryById(string $sectionHandle, string $id): Response
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => $sectionHandle,
            ReadOptions::ID => (int) $id
        ]);

        $entry = $this->readSection->read($readOptions)[0];

        $serializer = SerializerBuilder::create()->build();
        $jsonContent = $serializer->serialize($entry, 'json', $this->getContext());

        return new Response($jsonContent, 200, [
            'Content-Type' => 'application/json'
        ]);
    }

    /**
     * GET an entry by it's slug
     * @param string $sectionHandle
     * @param string $slug
     * @return Response
     */
    public function getEntryBySlug(string $sectionHandle, string $slug): Response
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => $sectionHandle,
            ReadOptions::SLUG => $slug
        ]);

        $entry = $this->readSection->read($readOptions)[0];
        $serializer = SerializerBuilder::create()->build();
        $jsonContent = $serializer->serialize($entry, 'json', $this->getContext());

        return new Response($jsonContent, 200, [
            'Content-Type' => 'application/json'
        ]);
    }

    /**
     * GET an entry or entries by one of it's field values
     * Example:
     * /v1/section/someSectionHandle/uuid?value=719d72d7-4f0c-420b-993f-969af9ad34c1
     *
     * @param string $sectionHandle
     * @param string $fieldHandle
     * @return Response
     */
    public function getEntriesByFieldValue(string $sectionHandle, string $fieldHandle): Response
    {
        $request = $this->requestStack->getCurrentRequest();

        // Theoretically you could have many results on a field value, so add some control over the results with limit, offset and also sorting
        $fieldValue = $request->get('value');
        $offset = $request->get('offset', 0);
        $limit = $request->get('limit', 100);
        $orderBy = $request->get('orderBy', 'created');
        $sort = $request->get('sort', 'DESC');

        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => $sectionHandle,
            ReadOptions::FIELD => [ $fieldHandle => $fieldValue ],
            ReadOptions::OFFSET => $offset,
            ReadOptions::LIMIT => $limit,
            ReadOptions::ORDER_BY => [ $orderBy => $sort ]
        ]);

        $entries = $this->readSection->read($readOptions);
        $serializer = SerializerBuilder::create()->build();

        $result = [];
        foreach ($entries as $entry) {
            $result[] = $serializer->serialize($entry, 'json', $this->getContext());
        }

        return new Response(
            '[' . implode(',', $result) . ']', 200,
            [
                'Content-Type' => 'application/json',
                'Access-Control-Allow-Origin' => '*'
            ]
        );
    }

    /**
     * GET Multiple entries
     * @param string $sectionHandle
     * @return Response
     */
    public function getEntries(
        string $sectionHandle
    ): Response {

        $request = $this->requestStack->getCurrentRequest();

        $offset = $request->get('offset', 0);
        $limit = $request->get('limit', 100);
        $orderBy = $request->get('orderBy', 'created');
        $sort = $request->get('sort', 'DESC');

        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => $sectionHandle,
            ReadOptions::OFFSET => $offset,
            ReadOptions::LIMIT => $limit,
            ReadOptions::ORDER_BY => [ $orderBy => $sort ]
        ]);

        $entries = $this->readSection->read($readOptions);
        $serializer = SerializerBuilder::create()->build();

        $result = [];
        foreach ($entries as $entry) {
            $result[] = $serializer->serialize($entry, 'json', $this->getContext());
        }

        return new Response(
            '[' . implode(',', $result) . ']', 200,
            [
                'Content-Type' => 'application/json',
                'Access-Control-Allow-Origin' => '*'
            ]
        );
    }

    /**
     * POST a new entry
     * @param string $sectionHandle
     * @return JsonResponse
     */
    public function createEntry(string $sectionHandle): JsonResponse
    {
        $response = [];

        /** @var \Symfony\Component\Form\FormInterface $form */
        $form = $this->form->buildFormForSection(
            $sectionHandle,
            $this->requestStack,
            null,
            false
        );
        $form->handleRequest();

        if ($form->isValid()) {
            $response = $this->save($form);
        } else {
            $response['errors'] = $this->getFormErrors($form);
            $response['code'] = 400;
        }

        return new JsonResponse(
            $response,
            $response['code'],
            ['Access-Control-Allow-Origin' => '*']
        );
    }

    /**
     * PUT (Update) an entry by it's id
     *
     * @param string $sectionHandle
     * @param int $id
     * @return JsonResponse
     */
    public function updateEntryById(string $sectionHandle, int $id): JsonResponse
    {
        $response = [];
        $this->putToPost();

        $form = $this->form->buildFormForSection(
            $sectionHandle,
            $this->requestStack,
            SectionFormOptions::fromArray([
                ReadOptions::ID => (int) $id
            ]),
            false
        );

        $form->handleRequest();
        if ($form->isValid()) {
            $response = $this->save($form);
        } else {
            $response['errors'] = $this->getFormErrors($form);
            $response['code'] = 400;
        }

        return new JsonResponse(
            $response,
            $response['code'],
            ['Access-Control-Allow-Origin' => '*']
        );
    }

    /**
     * PUT (Update) an entry by it's id.
     * This is for internal calls, service to service when you cannot
     * send along all fields that belong to the section you are updating
     *
     * @param string $sectionHandle
     * @param int $id
     * @return JsonResponse
     */
    public function updateEntryByIdInternal(string $sectionHandle, int $id): JsonResponse
    {
        $response = [];
        $this->putToPost();
        $request = $this->requestStack->getCurrentRequest();
        $form = $this->form->buildFormForSection(
            $sectionHandle,
            $this->requestStack,
            SectionFormOptions::fromArray([
                ReadOptions::ID => $id
            ]),
            false
        );
        $form->submit($request->get($form->getName()), false);

        if ($form->isValid()) {
            $response = $this->save($form);
        } else {
            $response['errors'] = $this->getFormErrors($form);
            $response['code'] = 400;
        }

        return new JsonResponse(
            $response,
            $response['code'],
            ['Access-Control-Allow-Origin' => '*']
        );
    }

    /**
     * PUT (Update) an entry by one of it's field values
     * Use this with a slug
     *
     * @param string $sectionHandle
     * @param string $slug
     * @return JsonResponse
     */
    public function updateEntryBySlug(string $sectionHandle, string $slug): JsonResponse
    {
        $response = [];

        $form = $this->form->buildFormForSection(
            $sectionHandle,
            $this->requestStack,
            SectionFormOptions::fromArray([
                ReadOptions::SLUG => $slug
            ]),
            false
        );
        $form->handleRequest();

        if ($form->isValid()) {
            $response = $this->save($form);
        } else {
            $response['errors'] = $this->getFormErrors($form);
            $response['code'] = 400;
        }

        return new JsonResponse(
            $response,
            $response['code'],
            ['Access-Control-Allow-Origin' => '*']
        );
    }

    /**
     * PUT (Update) an entry by it's slug.
     * This is for internal calls, service to service when you cannot
     * send along all fields that belong to the section you are updating
     *
     * @param string $sectionHandle
     * @param string $slug
     * @return JsonResponse
     */
    public function updateEntryBySlugInternal(string $sectionHandle, string $slug): JsonResponse
    {
        $response = [];
        $this->putToPost();
        $request = $this->requestStack->getCurrentRequest();
        $form = $this->form->buildFormForSection(
            $sectionHandle,
            $this->requestStack,
            SectionFormOptions::fromArray([
                ReadOptions::SLUG => $slug
            ]),
            false
        );
        $form->submit($request->get($form->getName()), false);

        if ($form->isValid()) {
            $response = $this->save($form);
        } else {
            $response['errors'] = $this->getFormErrors($form);
            $response['code'] = 400;
        }

        return new JsonResponse(
            $response,
            $response['code'],
            ['Access-Control-Allow-Origin' => '*']
        );
    }

    /**
     * DELETE an entry by it's id
     * @param string $sectionHandle
     * @param int $id
     * @return JsonResponse
     */
    public function deleteEntryById(string $sectionHandle, int $id): JsonResponse
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => $sectionHandle,
            ReadOptions::ID => (int) $id
        ]);

        $entry = $this->readSection->read($readOptions)[0];
        $success = $this->deleteSection->delete($entry);

        return new JsonResponse([
            'success' => $success,
        ], $success ? 200 : 404,
            ['Access-Control-Allow-Origin' => '*']
        );
    }

    /**
     * DELETE an entry by it's slug
     * @param string $sectionHandle
     * @param string $slug
     * @return JsonResponse
     */
    public function deleteEntryBySlug(string $sectionHandle, string $slug): JsonResponse
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => $sectionHandle,
            ReadOptions::SLUG => $slug
        ]);

        $entry = $this->readSection->read($readOptions)[0];
        $success = $this->deleteSection->delete($entry);

        return new JsonResponse([
            'success' => $success,
        ], $success ? 200 : 404,
            ['Access-Control-Allow-Origin' => '*']
        );
    }

    private function getContext(): SerializationContext
    {
        $request = $this->requestStack->getCurrentRequest();
        $fields = $request->get('fields', ['id']);

        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }

        $context = new SerializationContext();
        $context->addExclusionStrategy(new FieldsExclusionStrategy($fields));

        return $context;
    }

    /**
     * @param SymfonyFormInterface $form
     * @return array
     */
    private function save(SymfonyFormInterface $form): array
    {
        $response = [];
        $data = $form->getData();

        $request = $this->requestStack->getCurrentRequest();

        try {
            $this->createSection->save($data);
            $response['success'] = true;
            $response['errors'] = false;
            $response['code'] = 200;
        } catch (\Exception $exception) {
            $response['code'] = 500;
            $response['exception'] = $exception->getMessage();
        }

        return $response;
    }

    /**
     * @param SymfonyFormInterface $form
     * @return array
     */
    private function getFormErrors(SymfonyFormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true, true) as $field=>$formError) {
            $errors[$field] = $formError->getMessage();
        }

        /** @var SymfonyFormInterface $child */
        foreach ($form as $child) {
            if (!$child->isValid()) {
                foreach ($child->getErrors() as $error) {
                    $errors[$child->getName()][] = $error->getMessage();
                }
            }
        }

        return $errors;
    }

    /**
     * Symfony doesn't know how to handle put.
     * Transform put data to POST.
     */
    private function putToPost(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $put = $request->getContent();
        parse_str($put, $_POST);

        $_SERVER['REQUEST_METHOD'] = 'POST';
    }
}
