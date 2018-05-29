<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Api\Controller;

use Doctrine\Common\Util\Inflector;
use JMS\Serializer\Naming\IdenticalPropertyNamingStrategy;
use JMS\Serializer\Naming\SerializedNameAnnotationStrategy;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Form\FormInterface as SymfonyFormInterface;
use Tardigrades\Entity\FieldInterface;
use Tardigrades\FieldType\Relationship\Relationship;
use Tardigrades\SectionField\Api\Serializer\DepthExclusionStrategy;
use Tardigrades\SectionField\Api\Serializer\FieldsExclusionStrategy;
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

    /** @var EventDispatcherInterface */
    private $dispatcher;

    /** @var SerializeToArrayInterface */
    private $serialize;

    const DEFAULT_RELATIONSHIPS_LIMIT = 100;
    const DEFAULT_RELATIONSHIPS_OFFSET = 0;

    const OPTIONS_CALL = 'options';

    /** @var string */
    const ALLOWED_HTTP_METHODS = 'OPTIONS, GET, POST, PUT, DELETE';

    /**
     * RestController constructor.
     * @param CreateSectionInterface $createSection
     * @param ReadSectionInterface $readSection
     * @param DeleteSectionInterface $deleteSection
     * @param FormInterface $form
     * @param SectionManagerInterface $sectionManager
     * @param RequestStack $requestStack
     * @param EventDispatcherInterface $dispatcher
     * @param SerializeToArrayInterface $serialize
     */
    public function __construct(
        CreateSectionInterface $createSection,
        ReadSectionInterface $readSection,
        DeleteSectionInterface $deleteSection,
        FormInterface $form,
        SectionManagerInterface $sectionManager,
        RequestStack $requestStack,
        EventDispatcherInterface $dispatcher,
        SerializeToArrayInterface $serialize
    ) {
        $this->readSection = $readSection;
        $this->createSection = $createSection;
        $this->deleteSection = $deleteSection;
        $this->form = $form;
        $this->sectionManager = $sectionManager;
        $this->requestStack = $requestStack;
        $this->dispatcher = $dispatcher;
        $this->serialize = $serialize;
    }

    /**
     * GET information about the section so you can build
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

        $request = $this->requestStack->getCurrentRequest();

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        try {
            $responseData = [];

            $section = $this->sectionManager->readByHandle(Handle::fromString($sectionHandle));

            $responseData['name'] = (string) $section->getName();
            $responseData['handle'] = (string) $section->getHandle();

            $fieldProperties = $this->getEntityProperties($sectionHandle);

            /** @var FieldInterface $field */
            foreach ($section->getFields() as $field) {
                $fieldInfo = [(string) $field->getHandle() => $field->getConfig()->toArray()['field']];

                if ((string) $field->getFieldType()->getFullyQualifiedClassName() === Relationship::class) {
                    $fieldInfo = $this->getRelationshipsTo($request, $field, $fieldInfo, $sectionHandle, (int) $id);
                }

                $fieldInfo = $this->matchFormFieldsWithConfig($fieldProperties, $fieldInfo);

                $responseData['fields'][] = $fieldInfo;
            }


            $responseData = array_merge($responseData, $section->getConfig()->toArray());
            $responseData['fields'] = $this->orderFields($responseData);

            $jsonResponse = new JsonResponse(
                $responseData,
                JsonResponse::HTTP_OK,
                $this->getDefaultResponseHeaders($request)
            );

            if (!is_null($id)) {
                $entry = $this->readSection->read(ReadOptions::fromArray([
                    ReadOptions::SECTION => $sectionHandle,
                    ReadOptions::ID => (int) $id
                ]))->current();

                $responseData['entry'] = $this->serialize->toArray($request, $entry);
                $jsonResponse->setData($responseData);

                $this->dispatcher->dispatch(
                    ApiEntryFetched::NAME,
                    new ApiEntryFetched($request, $responseData, $jsonResponse, $entry)
                );
            }

            return $jsonResponse;
        } catch (\Exception $exception) {
            return $this->errorResponse($request, $exception);
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
        $request = $this->requestStack->getCurrentRequest();

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        try {
            $entry = $this->readSection->read(ReadOptions::fromArray([
                ReadOptions::SECTION => $sectionHandle,
                ReadOptions::ID => (int)$id
            ]))->current();

            $responseData = $this->serialize->toArray($request, $entry);
            $jsonResponse = new JsonResponse($responseData, JsonResponse::HTTP_OK, $this->getDefaultResponseHeaders($request));

            $this->dispatcher->dispatch(
                ApiEntryFetched::NAME,
                new ApiEntryFetched($request, $responseData, $jsonResponse, $entry)
            );

            return $jsonResponse;
        } catch (\Exception $exception) {
            return $this->errorResponse($request, $exception);
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
        $request = $this->requestStack->getCurrentRequest();

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        try {
            $entry = $this->readSection->read(ReadOptions::fromArray([
                ReadOptions::SECTION => $sectionHandle,
                ReadOptions::SLUG => $slug
            ]))->current();

            $responseData = $this->serialize->toArray($request, $entry);
            $jsonResponse = new JsonResponse($responseData, JsonResponse::HTTP_OK, $this->getDefaultResponseHeaders($request));

            $this->dispatcher->dispatch(
                ApiEntryFetched::NAME,
                new ApiEntryFetched($request, $responseData, $jsonResponse, $entry)
            );

            return $jsonResponse;
        } catch (\Exception $exception) {
            return $this->errorResponse($request, $exception);
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

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        // You could have many results on a field value,
        // so add some control over the results with limit, offset and also sorting.
        $fieldValue = (string)$request->get('value');
        if (strpos($fieldValue, ',') !== false) {
            $fieldValue = explode(',', $fieldValue);
        }

        $offset = $request->get('offset', 0);
        $limit = $request->get('limit', 100);
        $orderBy = $request->get('orderBy', 'created');
        $sort = $request->get('sort', 'DESC');

        try {
            $readOptions = [
                ReadOptions::SECTION => $sectionHandle,
                ReadOptions::FIELD => [
                    $fieldHandle => $fieldValue
                ],
                ReadOptions::OFFSET => (int) $offset,
                ReadOptions::LIMIT => (int) $limit,
                ReadOptions::ORDER_BY => [ $orderBy => strtolower($sort) ]
            ];
            $entries = $this->readSection->read(ReadOptions::fromArray($readOptions));
            $responseData = [];

            /** @var CommonSectionInterface $entry */
            foreach ($entries as $entry) {
                $responseData[] = $this->serialize->toArray($request, $entry);
            }
            $jsonResponse = new JsonResponse(
                $responseData,
                JsonResponse::HTTP_OK,
                $this->getDefaultResponseHeaders($request)
            );
            $this->dispatcher->dispatch(
                ApiEntriesFetched::NAME,
                new ApiEntriesFetched(
                    $request,
                    $responseData,
                    $jsonResponse,
                    $entries
                )
            );
            return $jsonResponse;
        } catch (\Exception $exception) {
            return $this->errorResponse($request, $exception);
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

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        $offset = $request->get('offset', 0);
        $limit = $request->get('limit', 100);
        $orderBy = $request->get('orderBy', 'created');
        $sort = $request->get('sort', 'DESC');

        try {
            $entries = $this->readSection->read(ReadOptions::fromArray([
                ReadOptions::SECTION => $sectionHandle,
                ReadOptions::OFFSET => (int) $offset,
                ReadOptions::LIMIT => (int) $limit,
                ReadOptions::ORDER_BY => [ $orderBy => strtolower($sort) ]
            ]));
            $responseData = [];

            /** @var CommonSectionInterface $entry */
            foreach ($entries as $entry) {
                $responseData[] = $this->serialize->toArray($request, $entry);
            }
            $jsonResponse = new JsonResponse(
                $responseData,
                JsonResponse::HTTP_OK,
                $this->getDefaultResponseHeaders($request)
            );
            $this->dispatcher->dispatch(
                ApiEntriesFetched::NAME,
                new ApiEntriesFetched(
                    $request,
                    $responseData,
                    $jsonResponse,
                    $entries
                )
            );
            return $jsonResponse;
        } catch (\Exception $exception) {
            return $this->errorResponse($request, $exception);
        }
    }

    /**
     * POST a new entry
     * @param string $sectionHandle
     * @return JsonResponse
     */
    public function createEntry(string $sectionHandle): JsonResponse
    {
        $request = $this->requestStack->getCurrentRequest();

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        $this->dispatcher->dispatch(
            ApiCreateEntry::NAME,
            new ApiCreateEntry($request, $sectionHandle)
        );

        try {
            $responseData = [ 'code' => JsonResponse::HTTP_OK ];

            /** @var \Symfony\Component\Form\FormInterface $form */
            $form = $this->form->buildFormForSection(
                $sectionHandle,
                $this->requestStack,
                null,
                false
            );
            $form->submit($request->get($form->getName()));

            $jsonResponse = new JsonResponse(
                $responseData,
                $responseData['code'],
                $this->getDefaultResponseHeaders($request)
            );

            if ($form->isValid()) {
                $this->dispatcher->dispatch(
                    ApiBeforeEntrySavedAfterValidated::NAME,
                    new ApiBeforeEntrySavedAfterValidated($request, $responseData, $jsonResponse, $form->getData())
                );
                $responseData = $this->save($form, $jsonResponse, $request);
                $jsonResponse->setData($responseData);
                $this->dispatcher->dispatch(
                    ApiEntryCreated::NAME,
                    new ApiEntryCreated($request, $responseData, $jsonResponse, $form->getData())
                );
            } else {
                $responseData['errors'] = $this->getFormErrors($form);
                $responseData['code'] = JsonResponse::HTTP_BAD_REQUEST;
                $jsonResponse->setData($responseData);
                $jsonResponse->setStatusCode($responseData['code']);
            }

            return $jsonResponse;
        } catch (\Exception $exception) {
            return $this->errorResponse($request, $exception);
        }
    }

    /**
     * PUT (Update) an entry by it's id.
     *
     * @param string $sectionHandle
     * @param int $id
     * @return JsonResponse
     */
    public function updateEntryById(string $sectionHandle, int $id): JsonResponse
    {
        $request = $this->requestStack->getCurrentRequest();

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        $this->dispatcher->dispatch(
            ApiUpdateEntry::NAME,
            new ApiUpdateEntry($request, $sectionHandle)
        );

        try {
            $responseData = [ 'code' => JsonResponse::HTTP_OK ];
            $this->putToPost();
            $form = $this->form->buildFormForSection(
                $sectionHandle,
                $this->requestStack,
                SectionFormOptions::fromArray([
                    ReadOptions::ID => $id
                ]),
                false
            );

            $originalEntry = clone $this->readSection->read(
                ReadOptions::fromArray([
                    ReadOptions::SECTION => $sectionHandle,
                    ReadOptions::ID => $id
                ])
            )->current();

            $form->submit($request->request->get($form->getName()), false);

            $jsonResponse = new JsonResponse(
                $responseData,
                $responseData['code'],
                $this->getDefaultResponseHeaders($request)
            );

            if ($form->isValid()) {
                $newEntry = $form->getData();
                $this->dispatcher->dispatch(
                    ApiBeforeEntryUpdatedAfterValidated::NAME,
                    new ApiBeforeEntryUpdatedAfterValidated(
                        $request,
                        $responseData,
                        $jsonResponse,
                        $originalEntry,
                        $newEntry
                    )
                );
                $responseData = $this->save($form, $jsonResponse, $request);
                $jsonResponse->setData($responseData);
                $this->dispatcher->dispatch(
                    ApiEntryUpdated::NAME,
                    new ApiEntryUpdated(
                        $request,
                        $responseData,
                        $jsonResponse,
                        $originalEntry,
                        $newEntry
                    )
                );
            } else {
                $responseData['errors'] = $this->getFormErrors($form);
                $responseData['code'] = JsonResponse::HTTP_BAD_REQUEST;
                $jsonResponse->setData($responseData);
                $jsonResponse->setStatusCode($responseData['code']);
            }

            return $jsonResponse;
        } catch (\Exception $exception) {
            return $this->errorResponse($request, $exception);
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
    public function updateEntryBySlug(string $sectionHandle, string $slug): JsonResponse
    {
        $request = $this->requestStack->getCurrentRequest();

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        $this->dispatcher->dispatch(
            ApiUpdateEntry::NAME,
            new ApiUpdateEntry($request, $sectionHandle)
        );

        try {
            $responseData = [ 'code' => JsonResponse::HTTP_OK ];
            $this->putToPost();
            $form = $this->form->buildFormForSection(
                $sectionHandle,
                $this->requestStack,
                SectionFormOptions::fromArray([
                    ReadOptions::SLUG => $slug
                ]),
                false
            );

            $originalEntry = clone $this->readSection->read(
                ReadOptions::fromArray([
                    ReadOptions::SECTION => $sectionHandle,
                    ReadOptions::SLUG => $slug
                ])
            )->current();

            $form->submit($request->request->get($form->getName()), false);
            $jsonResponse = new JsonResponse(
                $responseData,
                $responseData['code'],
                $this->getDefaultResponseHeaders($request)
            );

            if ($form->isValid()) {
                $newEntry = $form->getData();
                $this->dispatcher->dispatch(
                    ApiBeforeEntryUpdatedAfterValidated::NAME,
                    new ApiBeforeEntryUpdatedAfterValidated(
                        $request,
                        $responseData,
                        $jsonResponse,
                        $originalEntry,
                        $newEntry
                    )
                );

                $responseData = $this->save($form, $jsonResponse, $request);
                $jsonResponse->setData($responseData);
                $this->dispatcher->dispatch(
                    ApiEntryUpdated::NAME,
                    new ApiEntryUpdated(
                        $request,
                        $responseData,
                        $jsonResponse,
                        $originalEntry,
                        $newEntry
                    )
                );
            } else {
                $responseData['errors'] = $this->getFormErrors($form);
                $responseData['code'] = JsonResponse::HTTP_BAD_REQUEST;
                $jsonResponse->setData($responseData);
                $jsonResponse->setStatusCode($responseData['code']);
            }

            return $jsonResponse;
        } catch (\Exception $exception) {
            return $this->errorResponse($request, $exception);
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
        $request = $this->requestStack->getCurrentRequest();

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        $this->dispatcher->dispatch(
            ApiDeleteEntry::NAME,
            new ApiDeleteEntry($request, $sectionHandle)
        );

        try {
            $entry = $this->readSection->read(ReadOptions::fromArray([
                ReadOptions::SECTION => $sectionHandle,
                ReadOptions::ID => (int)$id
            ]))->current();
            $success = $this->deleteSection->delete($entry);
            $responseData = ['success' => $success];
            $jsonResponse = new JsonResponse(
                $responseData,
                $success ? JsonResponse::HTTP_OK : JsonResponse::HTTP_NOT_FOUND,
                $this->getDefaultResponseHeaders($request)
            );
            $this->dispatcher->dispatch(
                ApiEntryDeleted::NAME,
                new ApiEntryDeleted($request, $responseData, $jsonResponse, $entry)
            );
            return $jsonResponse;
        } catch (\Exception $exception) {
            return $this->errorResponse($request, $exception);
        }
    }

    /**
     * DELETE an entry by it's slug
     * @param string $sectionHandle
     * @param string $slug
     * @return JsonResponse
     */
    public function deleteEntryBySlug(string $sectionHandle, string $slug): JsonResponse
    {
        $request = $this->requestStack->getCurrentRequest();

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        $this->dispatcher->dispatch(
            ApiDeleteEntry::NAME,
            new ApiDeleteEntry($request, $sectionHandle)
        );

        try {
            $entry = $this->readSection->read(ReadOptions::fromArray([
                ReadOptions::SECTION => $sectionHandle,
                ReadOptions::SLUG => $slug
            ]))->current();
            $success = $this->deleteSection->delete($entry);
            $responseData = ['success' => $success];
            $jsonResponse = new JsonResponse(
                $responseData,
                $success ? JsonResponse::HTTP_OK : JsonResponse::HTTP_NOT_FOUND,
                $this->getDefaultResponseHeaders($request)
            );
            $this->dispatcher->dispatch(
                ApiEntryDeleted::NAME,
                new ApiEntryDeleted($request, $responseData, $jsonResponse, $entry)
            );
            return $jsonResponse;
        } catch (\Exception $exception) {
            return $this->errorResponse($request, $exception);
        }
    }

    /**
     * Send along on options call
     * @todo: Make headers configurable
     *
     * @param Request $request
     * @param string $allowMethods
     * @return null|JsonResponse
     */
    private function preFlightOptions(Request $request, string $allowMethods = 'OPTIONS'): ?JsonResponse
    {
        if (strtolower($request->getMethod()) === self::OPTIONS_CALL) {
            $origin = $request->headers->get('Origin');
            return new JsonResponse([], JsonResponse::HTTP_OK, [
                'Access-Control-Allow-Origin' => $origin ?: '*',
                'Access-Control-Allow-Methods' => $allowMethods,
                'Access-Control-Allow-Credentials' => 'true'
            ]);
        }

        return null;
    }

    /**
     * @param SymfonyFormInterface $form
     * @param JsonResponse $jsonResponse
     * @param Request $request
     * @return array
     */
    private function save(SymfonyFormInterface $form, JsonResponse $jsonResponse, Request $request): array
    {
        $responseData = [];

        $data = $form->getData();

        try {
            $this->createSection->save($data);
            $responseData['success'] = true;
            $responseData['errors'] = false;
            $responseData['code'] = JsonResponse::HTTP_OK;
            $responseData['entry'] = $this->serialize->toArray($request, $data);
        } catch (\Exception $exception) {
            $responseData['code'] = JsonResponse::HTTP_INTERNAL_SERVER_ERROR;
            $responseData['exception'] = $exception->getMessage();
        }

        $responseData = array_merge(json_decode($jsonResponse->getContent(), true), $responseData);

        return $responseData;
    }

    /**
     * @param SymfonyFormInterface $form
     * @return array
     */
    private function getFormErrors(SymfonyFormInterface $form): array
    {
        $errors = [];

        /**
         * @var string $field
         * @var FormError $formError
         */
        foreach ($form->getErrors(true, true) as $field => $formError) {
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
        try {
            $reflect = new \ReflectionClass($form);
            $properties = array_map(function ($data) {
                return $data->name;
            }, $reflect->getProperties());

            return $properties;
        } catch (\ReflectionException $exception) {
            //
        }
    }

    private function matchFormFieldsWithConfig(array $entityProperties, array $fieldInfo): array
    {
        $newHandle = null;
        $oldHandle = array_keys($fieldInfo)[0];

        // In case of a faux field, we just want to keep the originally defined handle
        $useOriginalHandle = false;
        try {
            $useOriginalHandle = $fieldInfo[$oldHandle]['generator']['entity']['ignore'];
        } catch (\Exception $exception) {
            // Empty because the try merely exists as an advanced isset
        }

        if (!$useOriginalHandle) {
            $newHandle = !empty($fieldInfo[$oldHandle]['as']) ?
                $this->matchesWithInArray($fieldInfo[$oldHandle]['as'], $entityProperties) : null;
            if (is_null($newHandle)) {
                $newHandle = !empty($fieldInfo[$oldHandle]['to']) ?
                    $this->matchesWithInArray($fieldInfo[$oldHandle]['to'], $entityProperties) : null;
            }
        }

        if (!is_null($newHandle)) {
            $update = [];
            $update[$newHandle] = $fieldInfo[array_keys($fieldInfo)[0]];
            $update[$newHandle]['handle'] = $newHandle;
            $update[$newHandle]['originalHandle'] = $oldHandle;
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
     * This is gets the potential parameters from the section info request.
     *
     * It will transform this:
     * ?options=someRelationshipFieldHandle|limit:100|offset:0
     *
     * Into this:
     * ['someRelationshipFieldHandle'] => [
     *    'limit' => 100,
     *    'offset' => 0
     * ]
     * @param Request $request
     * @return array|null
     */
    private function getOptions(Request $request): ?array
    {
        $requestOptions = $request->get('options');
        if (!is_null($requestOptions)) {
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
     * @param Request $request
     * @param array $fieldInfo
     * @param string $sectionHandle
     * @param int|null $id
     * @return array|null
     */
    private function getRelationshipsTo(
        Request $request,
        FieldInterface $field,
        array $fieldInfo,
        string $sectionHandle,
        int $id = null
    ): ?array {

        $fieldHandle = (string) $field->getHandle();
        $options = $this->getOptions($request);

        if (!empty($fieldInfo[$fieldHandle]['to'])) {
            try {
                $sexyFieldInstructions =
                    !empty($fieldInfo[$fieldHandle]['form']['sexy-field-instructions']['relationship']) ?
                        $fieldInfo[$fieldHandle]['form']['sexy-field-instructions']['relationship'] : null;

                $readOptions = [
                    ReadOptions::SECTION => $fieldInfo[$fieldHandle]['to'],
                    ReadOptions::LIMIT => !empty($options[$fieldHandle]['limit']) ?
                        (int)$options[$fieldHandle]['limit'] :
                        ((!empty($sexyFieldInstructions) && !empty($sexyFieldInstructions['limit'])) ?
                            (int)$sexyFieldInstructions['limit'] :
                            self::DEFAULT_RELATIONSHIPS_LIMIT),
                    ReadOptions::OFFSET => !empty($options[$fieldHandle]['offset']) ?
                        (int)$options[$fieldHandle]['offset'] :
                        ((!empty($sexyFieldInstructions) && !empty($sexyFieldInstructions['offset'])) ?
                            (int)$sexyFieldInstructions['offset'] :
                            self::DEFAULT_RELATIONSHIPS_OFFSET)
                ];

                if (!empty($sexyFieldInstructions) &&
                    !empty($sexyFieldInstructions['field']) &&
                    !empty($sexyFieldInstructions['value'])
                ) {
                    if (strpos((string) $sexyFieldInstructions['value'], ',') !== false) {
                        $sexyFieldInstructions['value'] = explode(',', $sexyFieldInstructions['value']);
                    }
                    $readOptions[ReadOptions::FIELD] = [
                        $sexyFieldInstructions['field'] => $sexyFieldInstructions['value']
                    ];
                }

                $nameExpression = [];
                if (!empty($sexyFieldInstructions) &&
                    !empty($sexyFieldInstructions['name-expression'])
                ) {
                    $nameExpression = explode('|', $sexyFieldInstructions['name-expression']);
                }

                $to = $this->readSection->read(ReadOptions::fromArray($readOptions));
                $fieldInfo[$fieldHandle][$fieldInfo[$fieldHandle]['to']] = [];

                /** @var CommonSectionInterface $entry */
                foreach ($to as $entry) {
                    $name = $entry->getDefault();
                    if ($nameExpression) {
                        $find = $entry;
                        foreach ($nameExpression as $method) {
                            if ($find) {
                                $find = $find->$method();
                            }
                        }
                        if ($find) {
                            $name = $find;
                        }
                    }

                    $fieldInfo[$fieldHandle][$fieldInfo[$fieldHandle]['to']][] = [
                        'id' => $entry->getId(),
                        'slug' => (string) $entry->getSlug(),
                        'name' => $name,
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
     * @todo: Make sure the allow headers are configurable.
     * @param $request
     * @return array
     */
    private function getDefaultResponseHeaders($request): array
    {
        $origin = $request->headers->get('Origin');
        return [
            'Access-Control-Allow-Origin' => $origin ?: '*',
            'Access-Control-Allow-Credentials' => 'true'
        ];
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
                ReadOptions::ID => (int)$id
            ])
        )->current();

        try {

            $method = !empty($fieldInfo[$fieldHandle]['as']) ?
                $fieldInfo[$fieldHandle]['as'] :
                $fieldInfo[$fieldHandle]['to'];

            $relationshipsEntityMethod = 'get' . ucfirst(Inflector::pluralize($method));
            if ($fieldInfo['kind'] !== Relationship::MANY_TO_ONE &&
                $fieldInfo['kind'] !== Relationship::ONE_TO_ONE) {
                $relationshipsEntityMethod = 'get' . ucfirst($method);
            }

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
        } catch (\Exception $exception) {
            // Empty because we can just return the fieldInfo the way it was if the above fails
        }

        return $fieldInfo;
    }

    private function orderFields($fields): array
    {
        $originalFields = $fields['fields'];
        $desiredFieldsOrder = $fields['section']['fields'];
        $originalFieldsOrder = [];
        $result = [];

        foreach ($originalFields as $field) {
            $handle = array_keys($field)[0];
            $handle = !empty($field[$handle]['originalHandle']) ? $field[$handle]['originalHandle'] : $handle;
            $originalFieldsOrder[] = $handle;
        }

        foreach ($desiredFieldsOrder as $handle) {
            $fieldIndex = array_search($handle, $originalFieldsOrder);
            $result[] = $originalFields[$fieldIndex];
        }

        return $result;
    }

    /**
     * Build a JSON response to return when an exception occurs.
     * @param Request $request
     * @param \Exception $exception
     * @return JsonResponse
     */
    private function errorResponse(Request $request, \Exception $exception): JsonResponse
    {
        if ($exception instanceof EntryNotFoundException || $exception instanceof SectionNotFoundException) {
            $statusCode = JsonResponse::HTTP_NOT_FOUND;
        } else {
            $statusCode = JsonResponse::HTTP_BAD_REQUEST;
        }
        return new JsonResponse([
            'message' => $exception->getMessage()
        ], $statusCode, $this->getDefaultResponseHeaders($request));
    }
}
