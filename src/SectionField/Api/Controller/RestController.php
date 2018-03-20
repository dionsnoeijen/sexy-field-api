<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Api\Controller;

use Doctrine\Common\Util\Inflector;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Form\FormInterface as SymfonyFormInterface;
use Tardigrades\Entity\FieldInterface;
use Tardigrades\FieldType\Relationship\Relationship;
use Tardigrades\SectionField\Api\Serializer\FieldsExclusionStrategy;
use Tardigrades\SectionField\Generator\CommonSectionInterface;
use Tardigrades\SectionField\Service\CreateSectionInterface;
use Tardigrades\SectionField\Service\DeleteSectionInterface;
use Tardigrades\SectionField\Form\FormInterface;
use Tardigrades\SectionField\Service\EntryNotFoundException;
use Tardigrades\SectionField\Service\ReadSectionInterface;
use Tardigrades\SectionField\Service\SectionManagerInterface;
use Tardigrades\SectionField\Service\ReadOptions;
use Tardigrades\SectionField\Service\SectionNotFoundException;
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

    const DEFAULT_RELATIONSHIPS_LIMIT = 100;
    const DEFAULT_RELATIONSHIPS_OFFSET = 0;

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
     *
     * You can add options for relationships like this:
     *
     * ?options=someRelationshipFieldHandle|limit:100|offset:0
     *
     * The limit and offset defaults to:
     * limit: 100
     * offset: 0
     *
     * @param string $sectionHandle
     * @param string $id
     * @return JsonResponse
     */
    public function getSectionInfo(
        string $sectionHandle,
        string $id = null
    ): JsonResponse {

        try {
            $response = [];

            $section = $this->sectionManager->readByHandle(Handle::fromString($sectionHandle));

            $response['name'] = (string)$section->getName();
            $response['handle'] = (string)$section->getHandle();

            $fieldProperties = $this->getEntityProperties($sectionHandle);

            /** @var FieldInterface $field */
            foreach ($section->getFields() as $field) {

                $fieldInfo = [(string)$field->getHandle() => $field->getConfig()->toArray()['field']];

                if ((string)$field->getFieldType()->getFullyQualifiedClassName() === Relationship::class) {
                    $fieldInfo = $this->getRelationshipsTo($field, $fieldInfo, $sectionHandle, (int)$id);
                }

                $fieldInfo = $this->matchFormFieldsWithConfig($fieldProperties, $fieldInfo);

                $response['fields'][] = $fieldInfo;
            }

            return new JsonResponse($response, 200, [
                'Access-Control-Allow-Methods' => 'OPTIONS'
            ]);
        } catch (SectionNotFoundException $exception) {

            return new JsonResponse([
                'message' => $exception->getMessage()
            ], $exception->getCode(), [
                'Access-Control-Allow-Methods' => 'OPTIONS'
            ]);
        }
    }

    /**
     * GET an entry by id
     * @param string $sectionHandle
     * @param string $id
     * @return JsonResponse
     */
    public function getEntryById(string $sectionHandle, string $id): JsonResponse
    {
        try {
            $entry = $this->readSection->read(ReadOptions::fromArray([
                ReadOptions::SECTION => $sectionHandle,
                ReadOptions::ID => (int)$id
            ]))->current();

            $serializer = SerializerBuilder::create()->build();
            $jsonContent = $serializer->serialize($entry, 'json', $this->getContext());

            return new JsonResponse($jsonContent, 200);
        } catch (EntryNotFoundException $exception) {
            return new JsonResponse([
                'error' => $exception->getMessage()
            ], $exception->getCode());
        }
    }

    /**
     * GET an entry by it's slug
     * @param string $sectionHandle
     * @param string $slug
     * @return JsonResponse
     */
    public function getEntryBySlug(string $sectionHandle, string $slug): JsonResponse
    {
        try {
            $entry = $this->readSection->read(ReadOptions::fromArray([
                ReadOptions::SECTION => $sectionHandle,
                ReadOptions::SLUG => $slug
            ]))->current();

            $serializer = SerializerBuilder::create()->build();
            $jsonContent = $serializer->serialize($entry, 'json', $this->getContext());

            return new JsonResponse($jsonContent, 200);
        } catch (EntryNotFoundException $exception) {
            return new JsonResponse([
                'message' => $exception->getMessage()
            ], $exception->getCode());
        }
    }

    /**
     * GET an entry or entries by one of it's field values
     * Example:
     * /v1/section/someSectionHandle/uuid?value=719d72d7-4f0c-420b-993f-969af9ad34c1
     *
     * @param string $sectionHandle
     * @param string $fieldHandle
     * @return JsonResponse
     */
    public function getEntriesByFieldValue(string $sectionHandle, string $fieldHandle): JsonResponse
    {
        $request = $this->requestStack->getCurrentRequest();

        // Theoretically you could have many results on a field value, so add some control over the results with limit, offset and also sorting
        $fieldValue = $request->get('value');
        $offset = $request->get('offset', 0);
        $limit = $request->get('limit', 100);
        $orderBy = $request->get('orderBy', 'created');
        $sort = $request->get('sort', 'DESC');

        try {
            $entries = $this->readSection->read(ReadOptions::fromArray([
                ReadOptions::SECTION => $sectionHandle,
                ReadOptions::FIELD => [$fieldHandle => $fieldValue],
                ReadOptions::OFFSET => $offset,
                ReadOptions::LIMIT => $limit,
                ReadOptions::ORDER_BY => [$orderBy => $sort]
            ]));

            $serializer = SerializerBuilder::create()->build();
            $result = [];
            foreach ($entries as $entry) {
                $result[] = $serializer->serialize($entry, 'json', $this->getContext());
            }

            return new JsonResponse($result, 200);
        } catch (EntryNotFoundException $exception) {
            return new JsonResponse([
                'message' => $exception->getMessage()
            ], $exception->getCode());
        }
    }

    /**
     * GET Multiple entries
     * @param string $sectionHandle
     * @return JsonResponse
     */
    public function getEntries(
        string $sectionHandle
    ): JsonResponse {

        $request = $this->requestStack->getCurrentRequest();

        $offset = $request->get('offset', 0);
        $limit = $request->get('limit', 100);
        $orderBy = $request->get('orderBy', 'created');
        $sort = $request->get('sort', 'DESC');

        try {

            $entries = $this->readSection->read(ReadOptions::fromArray([
                ReadOptions::SECTION => $sectionHandle,
                ReadOptions::OFFSET => $offset,
                ReadOptions::LIMIT => $limit,
                ReadOptions::ORDER_BY => [$orderBy => $sort]
            ]));
            $serializer = SerializerBuilder::create()->build();

            $result = [];
            foreach ($entries as $entry) {
                $result[] = $serializer->serialize($entry, 'json', $this->getContext());
            }

            return new JsonResponse($result, 200);
        } catch (EntryNotFoundException $exception) {
            return new JsonResponse([
                'message' => $exception->getMessage()
            ], $exception->getCode());
        }
    }

    /**
     * POST a new entry
     * @param string $sectionHandle
     * @return JsonResponse
     */
    public function createEntry(string $sectionHandle): JsonResponse
    {
        try {
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
                $response['code']
            );
        } catch (\Exception $exception) {
            return new JsonResponse([
                'message' => $exception->getMessage()
            ], $exception->getCode());
        }
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
        try {
            $response = [];
            $this->putToPost();

            $form = $this->form->buildFormForSection(
                $sectionHandle,
                $this->requestStack,
                SectionFormOptions::fromArray([
                    ReadOptions::ID => (int)$id
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
                $response['code']
            );
        } catch (\Exception $exception) {
            return new JsonResponse([
                'message' => $exception->getMessage()
            ], $exception->getCode());
        }
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
        try {
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
                $response['code']
            );
        } catch (\Exception $exception) {
            return new JsonResponse([
                'message' => $exception->getMessage()
            ], $exception->getCode());
        }
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
        try {
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
                $response['code']
            );
        } catch (\Exception $exception) {
            return new JsonResponse([
                'message' => $exception->getMessage()
            ], $exception->getCode());
        }
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
        try {
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
                $response['code']
            );
        } catch (\Exception $exception) {
            return new JsonResponse([
                'message' => $exception->getMessage()
            ], $exception->getCode());
        }
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
        ], $success ? 200 : 404);
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
        ], $success ? 200 : 404);
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

    /**
     * This is tricky, the form requires the properties from the entity
     * as their name="form[entityProperty]" but this doesn't
     * reflect what is configured because of pluralizing and/or relationship
     * fields that would not use the configured handle but the "to" or "as"
     * field. Find a better solution for this problem.
     *
     * @param string $sectionHandle
     * @return array
     */
    private function getEntityProperties(string $sectionHandle): array
    {
        $form = $this->form->buildFormForSection(
            $sectionHandle,
            $this->requestStack,
            null,
            false
        )->getData();
        $reflect = new \ReflectionClass($form);
        $properties = array_map(function($data) {
            return $data->name;
        }, $reflect->getProperties());

        return $properties;
    }

    private function matchFormFieldsWithConfig(array $entityProperties, array $fieldInfo): array
    {
        $newHandle = null;
        $oldHandle = array_keys($fieldInfo)[0];
        $newHandle = !empty($fieldInfo[$oldHandle]['as']) ?
            $this->matchesWithInArray($fieldInfo[$oldHandle]['as'], $entityProperties) : null;
        if (is_null($newHandle)) {
            $newHandle = !empty($fieldInfo[$oldHandle]['to']) ?
                $this->matchesWithInArray($fieldInfo[$oldHandle]['to'], $entityProperties) : null;
        }

        if (!is_null($newHandle)) {
            $update = [];
            $update[$newHandle] = $fieldInfo[array_keys($fieldInfo)[0]];
            $update[$newHandle]['handle'] = $newHandle;
            return $update;
        }

        return $fieldInfo;
    }

    private function matchesWithInArray(string $needle, array $search): ?string
    {
        $match = [];
        foreach ($search as $key => $value) {
            similar_text($needle, $value, $percent);
            $match[$key] = $percent;
        }

        $highestValue = max($match);
        if ($highestValue > 80 && $highestValue < 100) {
            $keyHighest = array_keys($match, $highestValue)[0];
            return $search[$keyHighest];
        }

        return null;
    }

    /**
     * This is gets the potential options from the [OPTIONS] request.
     *
     * It will transform this:
     * ?options=someRelationshipFieldHandle|limit:100|offset:0
     *
     * Into this:
     * ['someRelationshipFieldHandle'] => [
     *    'limit' => 100,
     *    'offset' => 0
     * ]
     */
    private function getOptions(): ?array
    {
        $request = $this->requestStack->getCurrentRequest();
        $requestOptions = $request->get('options');
        if (!empty($requestOptions)) {
            $requestOptions = explode('|', $requestOptions);
            $options = [];
            $fieldHandle = array_shift($requestOptions);
            $options[$fieldHandle] = [];
            foreach ($requestOptions as $option) {
                $keyValue = explode(':', $option);
                $options[$fieldHandle][$keyValue[0]] = $keyValue[1];
            }
            return $options;
        }
        return null;
    }

    /**
     * Get entries to populate a relationships field
     *
     * @param FieldInterface $field
     * @param array $fieldInfo
     * @param string $sectionHandle
     * @param int|null $id
     * @return array|null
     */
    private function getRelationshipsTo(
        FieldInterface $field,
        array $fieldInfo,
        string $sectionHandle,
        int $id = null
    ): ?array {

        $fieldHandle = (string) $field->getHandle();
        $options = $this->getOptions();

        if (!empty($fieldInfo[$fieldHandle]['to'])) {
            try {
                $to = $this->readSection->read(
                    ReadOptions::fromArray([
                        ReadOptions::SECTION => $fieldInfo[$fieldHandle]['to'],
                        ReadOptions::LIMIT => !empty($options[$fieldHandle]['limit']) ?
                            (int) $options[$fieldHandle]['limit'] : self::DEFAULT_RELATIONSHIPS_LIMIT,
                        ReadOptions::OFFSET => !empty($options[$fieldHandle]['offset']) ?
                            $options[$fieldHandle]['offset'] : self::DEFAULT_RELATIONSHIPS_OFFSET
                    ])
                );

                $fieldInfo[$fieldHandle][$fieldInfo[$fieldHandle]['to']] = [];
                /** @var CommonSectionInterface $entry */
                foreach ($to as $entry) {
                    $fieldInfo[$fieldHandle][$fieldInfo[$fieldHandle]['to']][] = [
                        'id' => $entry->getId(),
                        'slug' => (string) $entry->getSlug(),
                        'name' => $entry->getDefault(),
                        'created' => $entry->getCreated(),
                        'updated' => $entry->getUpdated(),
                        'selected' => false
                    ];
                }
            } catch (EntryNotFoundException $exception) {
                $fieldInfo[$fieldHandle][$fieldInfo[$fieldHandle]['to']]['error'] = $exception->getMessage();
            }

            if (!empty($id)) {
                $fieldInfo = $this->setSelectedRelationshipsTo($sectionHandle, $fieldHandle, $fieldInfo, $id);
            }
        }

        return $fieldInfo;
    }

    /**
     * If editing an entry with relationships, mark related as true
     *
     * @param string $sectionHandle
     * @param string $fieldHandle
     * @param array $fieldInfo
     * @param int $id
     * @return array
     */
    private function setSelectedRelationshipsTo(
        string $sectionHandle,
        string $fieldHandle,
        array $fieldInfo,
        int $id
    ): array {

        /** @var CommonSectionInterface $editing */
        $editing = $this->readSection->read(
            ReadOptions::fromArray([
                ReadOptions::SECTION => $sectionHandle,
                ReadOptions::ID => (int) $id
            ])
        )->current();

        try {
            $relationshipsEntityMethod = 'get' .
                ucfirst(Inflector::pluralize(!empty($fieldInfo[$fieldHandle]['as']) ?
                    $fieldInfo[$fieldHandle]['as'] : $fieldInfo[$fieldHandle]['to']));

            $related = $editing->{$relationshipsEntityMethod}();
            $relatedIds = [];
            foreach ($related as $relation) {
                $relatedIds[] = $relation->getId();
            }
            foreach ($fieldInfo[$fieldHandle][$fieldInfo[$fieldHandle]['to']] as &$relatable) {
                if (!empty($relatable['id']) && in_array($relatable['id'], $relatedIds)) {
                    $relatable['selected'] = true;
                }
            }
        } catch (\Exception $exception) {}

        return $fieldInfo;
    }
}
