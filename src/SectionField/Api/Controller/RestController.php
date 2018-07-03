<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Api\Controller;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Form\FormInterface as SymfonyFormInterface;
use Tardigrades\SectionField\Api\Serializer\SerializeToArrayInterface;
use Tardigrades\SectionField\Service\CacheInterface;
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
use Tardigrades\SectionField\ValueObject\SectionFormOptions;
use Tardigrades\SectionField\ValueObject\Handle;

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
    protected $readSection;

    /** @var CreateSectionInterface */
    protected $createSection;

    /** @var DeleteSectionInterface */
    protected $deleteSection;

    /** @var FormInterface */
    protected $form;

    /** @var SectionManagerInterface */
    protected $sectionManager;

    /** @var RequestStack */
    protected $requestStack;

    /** @var EventDispatcherInterface */
    protected $dispatcher;

    /** @var SerializeToArrayInterface */
    protected $serialize;

    /** @var CacheInterface */
    protected $cache;

    const DEFAULT_RELATIONSHIPS_LIMIT = 100;
    const DEFAULT_RELATIONSHIPS_OFFSET = 0;
    const OPTIONS_CALL = 'options';

    const CACHE_CONTEXT_GET_ENTRY_BY_ID = 'get.entry.by.id';
    const CACHE_CONTEXT_GET_ENTRY_BY_SLUG = 'get.entry.by.slug';
    const CACHE_CONTEXT_GET_ENTRIES_BY_FIELD_VALUE = 'get.entries.by.field.value';
    const CACHE_CONTEXT_GET_ENTRIES = 'get.entries';

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
     * @param CacheInterface $cache
     */
    public function __construct(
        CreateSectionInterface $createSection,
        ReadSectionInterface $readSection,
        DeleteSectionInterface $deleteSection,
        FormInterface $form,
        SectionManagerInterface $sectionManager,
        RequestStack $requestStack,
        EventDispatcherInterface $dispatcher,
        SerializeToArrayInterface $serialize,
        CacheInterface $cache
    ) {
        $this->readSection = $readSection;
        $this->createSection = $createSection;
        $this->deleteSection = $deleteSection;
        $this->form = $form;
        $this->sectionManager = $sectionManager;
        $this->requestStack = $requestStack;
        $this->dispatcher = $dispatcher;
        $this->serialize = $serialize;
        $this->cache = $cache;
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
            $section = $this->sectionManager->readByHandle(Handle::fromString($sectionHandle));

            try {
                $this->cache->start(
                    $section->getConfig()->getFullyQualifiedClassName(),
                    $this->getFields(),
                    self::CACHE_CONTEXT_GET_ENTRY_BY_ID,
                    $id
                );
            } catch (\Psr\Cache\InvalidArgumentException $exception) {
                //
            }

            if ($this->cache->isHit()) {
                return new JsonResponse(
                    $this->cache->get(),
                    JsonResponse::HTTP_OK,
                    $this->getDefaultResponseHeaders($request)
                );
            }

            $entry = $this->readSection->read(ReadOptions::fromArray([
                ReadOptions::SECTION => $sectionHandle,
                ReadOptions::ID => (int)$id
            ]))->current();

            $responseData = $this->serialize->toArray($request, $entry);
            $jsonResponse = new JsonResponse(
                $responseData,
                JsonResponse::HTTP_OK,
                $this->getDefaultResponseHeaders($request)
            );

            $this->dispatcher->dispatch(
                ApiEntryFetched::NAME,
                new ApiEntryFetched($request, $responseData, $jsonResponse, $entry)
            );

            $this->cache->set($responseData);

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
            $section = $this->sectionManager->readByHandle(Handle::fromString($sectionHandle));

            try {
                $this->cache->start(
                    $section->getConfig()->getFullyQualifiedClassName(),
                    $this->getFields(),
                    self::CACHE_CONTEXT_GET_ENTRY_BY_SLUG,
                    $slug
                );
            } catch (\Psr\Cache\InvalidArgumentException $exception) {
                //
            }

            if ($this->cache->isHit()) {
                return new JsonResponse(
                    $this->cache->get(),
                    JsonResponse::HTTP_OK,
                    $this->getDefaultResponseHeaders($request)
                );
            }

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

            $this->cache->set($responseData);

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

        // We may join and want to select something on what is in the joined table
        // Like this: accountHasRole:role&value=1,2,3
        $fieldHandles = explode(':', $fieldHandle);
        $fieldHandle = array_shift($fieldHandles);

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
            $section = $this->sectionManager->readByHandle(Handle::fromString($sectionHandle));

            try {
                $this->cache->start(
                    $section->getConfig()->getFullyQualifiedClassName(),
                    $this->getFields(),
                    self::CACHE_CONTEXT_GET_ENTRIES_BY_FIELD_VALUE,
                    $fieldHandle
                );
            } catch (\Psr\Cache\InvalidArgumentException $exception) {
                //
            }

            if ($this->cache->isHit()) {
                return new JsonResponse(
                    $this->cache->get(),
                    JsonResponse::HTTP_OK,
                    $this->getDefaultResponseHeaders($request)
                );
            }

            $readOptions = [
                ReadOptions::SECTION => $sectionHandle,
                ReadOptions::FIELD => [ $fieldHandle => $fieldValue ],
                ReadOptions::RELATE => $fieldHandles,
                ReadOptions::OFFSET => (int) $offset,
                ReadOptions::LIMIT => (int) $limit,
                ReadOptions::ORDER_BY => [ $orderBy => strtolower($sort) ],
                ReadOptions::FETCH_FIELDS => $request->get('fields', null)
            ];
            $entries = $this->readSection->read(ReadOptions::fromArray($readOptions));
            $responseData = [];
            foreach ($entries as $entry) {
                if ($entry instanceof CommonSectionInterface) {
                    $responseData[] = $this->serialize->toArray($request, $entry);
                } else {
                    $responseData[] = $entry;
                }
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

            $this->cache->set($responseData);

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
        $fields = $request->get('fields', null);

        try {
            $section = $this->sectionManager->readByHandle(Handle::fromString($sectionHandle));

            try {
                $this->cache->start(
                    $section->getConfig()->getFullyQualifiedClassName(),
                    $this->getFields(),
                    self::CACHE_CONTEXT_GET_ENTRIES
                );
            } catch (\Psr\Cache\InvalidArgumentException $exception) {
                //
            }

            if ($this->cache->isHit()) {
                return new JsonResponse(
                    $this->cache->get(),
                    JsonResponse::HTTP_OK,
                    $this->getDefaultResponseHeaders($request)
                );
            }

            $entries = $this->readSection->read(ReadOptions::fromArray([
                ReadOptions::SECTION => $sectionHandle,
                ReadOptions::OFFSET => (int) $offset,
                ReadOptions::LIMIT => (int) $limit,
                ReadOptions::ORDER_BY => [ $orderBy => strtolower($sort) ],
                ReadOptions::FETCH_FIELDS => $fields
            ]));
            $responseData = [];
            foreach ($entries as $entry) {
                if ($entry instanceof CommonSectionInterface) {
                    $responseData[] = $this->serialize->toArray($request, $entry);
                } else {
                    $responseData[] = $entry;
                }
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

            $this->cache->set($responseData);

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

        $abortCode = $request->get('abort');
        if ($abortCode) {
            return new JsonResponse(
                $request->get('abortMessage'),
                $abortCode,
                $this->getDefaultResponseHeaders($request)
            );
        }

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

            $form->submit($request->get($form->getName()), false);

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

        $abortCode = $request->get('abort');
        if ($abortCode) {
            return new JsonResponse($request->get('abortMessage'), $abortCode, $this->getDefaultResponseHeaders($request));
        }

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

            $form->submit($request->get($form->getName()), false);
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
    protected function preFlightOptions(Request $request, string $allowMethods = 'OPTIONS'): ?JsonResponse
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
    protected function getFormErrors(SymfonyFormInterface $form): array
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
    protected function putToPost(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $put = $request->getContent();
        parse_str($put, $_POST);

        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    /**
     * @todo: Make sure the allow headers are configurable.
     * @param $request
     * @return array
     */
    protected function getDefaultResponseHeaders($request): array
    {
        $origin = $request->headers->get('Origin');
        return [
            'Access-Control-Allow-Origin' => $origin ?: '*',
            'Access-Control-Allow-Credentials' => 'true'
        ];
    }

    /**
     * Build a JSON response to return when an exception occurs.
     * @param Request $request
     * @param \Exception $exception
     * @return JsonResponse
     */
    protected function errorResponse(Request $request, \Exception $exception): JsonResponse
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

    /**
     * You can restrict the fields you desire to see
     *
     * @return array|null
     */
    protected function getFields(): ?array
    {
        $fields = $this->requestStack->getCurrentRequest()->get('fields');

        if (!is_null($fields)) {
            $fields = array_map('trim', explode(',', $fields));
        }

        return $fields;
    }
}
