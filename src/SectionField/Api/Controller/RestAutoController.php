<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Api\Controller;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Form\FormInterface as SymfonyFormInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Tardigrades\SectionField\Api\Serializer\SerializeToArrayInterface;
use Tardigrades\SectionField\Api\Utils\AccessControlAllowOrigin;
use Tardigrades\SectionField\Event\ApiFetchEntries;
use Tardigrades\SectionField\Event\ApiFetchEntry;
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
 * Class RestAutoController
 *
 * The REST Auto Controller provides a simple REST implementation for Sections.
 * Predominantly meant for during development purposes or admin user interfaces.
 *
 * @package Tardigrades\SectionField\Api\Controller
 */
class RestAutoController implements RestControllerInterface
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

    /** @var TokenStorageInterface */
    protected $tokenStorage;

    /** @var \HTMLPurifier */
    private $purifier;

    const DEFAULT_RELATIONSHIPS_LIMIT = 100;
    const DEFAULT_RELATIONSHIPS_OFFSET = 0;

    const OPTIONS_CALL = 'options';
    const CACHE_CONTEXT_GET_ENTRY_BY_ID = 'get.entry.by.id';
    const CACHE_CONTEXT_GET_ENTRY_BY_SLUG = 'get.entry.by.slug';
    const CACHE_CONTEXT_GET_ENTRIES_BY_FIELD_VALUE = 'get.entries.by.field.value';

    const CACHE_CONTEXT_GET_ENTRIES = 'get.entries';
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
     * @param TokenStorageInterface $tokenStorage
     * @param \HTMLPurifier $purifier
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
        CacheInterface $cache,
        TokenStorageInterface $tokenStorage,
        \HTMLPurifier $purifier
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
        $this->tokenStorage = $tokenStorage;
        $this->purifier = $purifier;
    }

    /**
     * GET an entry by id
     * @param string $sectionHandle
     * @param string $id
     * @return JsonResponse
     */
    public function getEntryByIdAction(string $sectionHandle, string $id): JsonResponse
    {
        $request = $this->requestStack->getCurrentRequest();

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        $this->dispatcher->dispatch(
            new ApiFetchEntry($request, $sectionHandle),
            ApiFetchEntry::NAME
        );

        if ($abort = $this->shouldAbort($request)) {
            return $abort;
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
            $this->purifyResponseData($responseData);
            $jsonResponse = new JsonResponse(
                $responseData,
                JsonResponse::HTTP_OK,
                $this->getDefaultResponseHeaders($request)
            );

            $this->dispatcher->dispatch(
                new ApiEntryFetched($request, $responseData, $jsonResponse, $entry),
                ApiEntryFetched::NAME
            );

            if ($abort = $this->shouldAbort($request)) {
                return $abort;
            }

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
    public function getEntryBySlugAction(string $sectionHandle, string $slug): JsonResponse
    {
        $request = $this->requestStack->getCurrentRequest();

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        $this->dispatcher->dispatch(
            new ApiFetchEntry($request, $sectionHandle),
            ApiFetchEntry::NAME
        );

        if ($abort = $this->shouldAbort($request)) {
            return $abort;
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
            $this->purifyResponseData($responseData);
            $jsonResponse = new JsonResponse(
                $responseData,
                JsonResponse::HTTP_OK,
                $this->getDefaultResponseHeaders($request)
            );

            $this->dispatcher->dispatch(
                new ApiEntryFetched($request, $responseData, $jsonResponse, $entry),
                ApiEntryFetched::NAME
            );

            if ($abort = $this->shouldAbort($request)) {
                return $abort;
            }

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
    public function getEntriesByFieldValueAction(string $sectionHandle, string $fieldHandle): JsonResponse
    {
        $request = $this->requestStack->getCurrentRequest();

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        $this->dispatcher->dispatch(
            new ApiFetchEntries($request, $sectionHandle),
            ApiFetchEntries::NAME
        );

        if ($abort = $this->shouldAbort($request)) {
            return $abort;
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

        // The second argument is the default value,
        // but if the request arguments are set with null
        // explicitly, it will return null. Therefore also
        // have a definite fallback on null.
        $offset = $request->get('offset', 0);
        $offset = empty($offset) ? 0 : $offset;
        $limit = $request->get('limit', 100);
        $limit = empty($limit) ? 100 : $limit;
        $orderBy = $request->get('orderBy', 'created');
        $orderBy = empty($orderBy) ? 'created' : $orderBy;
        $sort = $request->get('sort', 'DESC');
        $sort = empty($sort) ? 'DESC' : $sort;

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
                ReadOptions::ORDER_BY => [ $orderBy => strtolower($sort) ]
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
            $this->purifyResponseData($responseData);
            $jsonResponse = new JsonResponse(
                $responseData,
                JsonResponse::HTTP_OK,
                $this->getDefaultResponseHeaders($request)
            );
            $this->dispatcher->dispatch(
                new ApiEntriesFetched(
                    $request,
                    $responseData,
                    $jsonResponse,
                    $entries
                ),
                ApiEntriesFetched::NAME
            );

            if ($abort = $this->shouldAbort($request)) {
                return $abort;
            }

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
    public function getEntriesAction(
        string $sectionHandle
    ): JsonResponse {
        $request = $this->requestStack->getCurrentRequest();

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        $this->dispatcher->dispatch(
            new ApiFetchEntries($request, $sectionHandle),
            ApiFetchEntries::NAME
        );

        if ($abort = $this->shouldAbort($request)) {
            return $abort;
        }

        // The second argument is the default value,
        // but if the request arguments are set with null
        // explicitly, it will return null. Therefore also
        // have a definite fallback on null.
        $offset = $request->get('offset', 0);
        $offset = empty($offset) ? 0 : $offset;
        $limit = $request->get('limit', 100);
        $limit = empty($limit) ? 100 : $limit;
        $orderBy = $request->get('orderBy', 'created');
        $orderBy = empty($orderBy) ? 'created' : $orderBy;
        $sort = $request->get('sort', 'DESC');
        $sort = empty($sort) ? 'DESC' : $sort;
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
                ReadOptions::ORDER_BY => [ $orderBy => strtolower($sort) ]
            ]));
            $responseData = [];
            foreach ($entries as $entry) {
                if ($entry instanceof CommonSectionInterface) {
                    $responseData[] = $this->serialize->toArray($request, $entry);
                } else {
                    $responseData[] = $entry;
                }
            }
            $this->purifyResponseData($responseData);
            $jsonResponse = new JsonResponse(
                $responseData,
                JsonResponse::HTTP_OK,
                $this->getDefaultResponseHeaders($request)
            );
            $this->dispatcher->dispatch(
                new ApiEntriesFetched(
                    $request,
                    $responseData,
                    $jsonResponse,
                    $entries
                ),
                ApiEntriesFetched::NAME
            );

            if ($abort = $this->shouldAbort($request)) {
                return $abort;
            }

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
    public function createEntryAction(string $sectionHandle): JsonResponse
    {
        $request = $this->requestStack->getCurrentRequest();

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        $this->dispatcher->dispatch(
            new ApiCreateEntry($request, $sectionHandle),
            ApiCreateEntry::NAME
        );

        if ($abort = $this->shouldAbort($request)) {
            return $abort;
        }

        try {
            $responseData = [ 'code' => JsonResponse::HTTP_OK ];

            $form = $this->form->buildFormForSection(
                $sectionHandle,
                $this->requestStack,
                null,
                false
            );
            $form->submit($request->get($form->getName()));

            $this->purifyResponseData($responseData);
            $jsonResponse = new JsonResponse(
                $responseData,
                $responseData['code'],
                $this->getDefaultResponseHeaders($request)
            );

            if ($form->isSubmitted() && $form->isValid()) {
                $this->dispatcher->dispatch(
                    new ApiBeforeEntrySavedAfterValidated($request, $responseData, $jsonResponse, $form->getData()),
                    ApiBeforeEntrySavedAfterValidated::NAME
                );
                $abortCode = $request->get('abort');
                if ($abortCode) {
                    return new JsonResponse(
                        $request->get('abortMessage'),
                        $abortCode,
                        $this->getDefaultResponseHeaders($request)
                    );
                }

                $responseData = $this->save($form, $jsonResponse, $request);
                $jsonResponse->setData($responseData);
                $this->dispatcher->dispatch(
                    new ApiEntryCreated($request, $responseData, $jsonResponse, $form->getData()),
                    ApiEntryCreated::NAME
                );

                if ($abort = $this->shouldAbort($request)) {
                    return $abort;
                }
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
    public function updateEntryByIdAction(string $sectionHandle, int $id): JsonResponse
    {
        $request = $this->requestStack->getCurrentRequest();

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        $this->dispatcher->dispatch(
            new ApiUpdateEntry($request, $sectionHandle),
            ApiUpdateEntry::NAME
        );

        if ($abort = $this->shouldAbort($request)) {
            return $abort;
        }

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
            $this->purifyResponseData($responseData);
            $jsonResponse = new JsonResponse(
                $responseData,
                $responseData['code'],
                $this->getDefaultResponseHeaders($request)
            );

            if ($form->isSubmitted() && $form->isValid()) {
                $newEntry = $form->getData();
                $this->dispatcher->dispatch(
                    new ApiBeforeEntryUpdatedAfterValidated(
                        $request,
                        $responseData,
                        $jsonResponse,
                        $originalEntry,
                        $newEntry
                    ),
                    ApiBeforeEntryUpdatedAfterValidated::NAME
                );

                if ($abort = $this->shouldAbort($request)) {
                    return $abort;
                }

                $responseData = $this->save($form, $jsonResponse, $request);
                $this->purifyResponseData($responseData);
                $jsonResponse->setData($responseData);
                $this->dispatcher->dispatch(
                    new ApiEntryUpdated(
                        $request,
                        $responseData,
                        $jsonResponse,
                        $originalEntry,
                        $newEntry
                    ),
                    ApiEntryUpdated::NAME
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
    public function updateEntryBySlugAction(string $sectionHandle, string $slug): JsonResponse
    {
        $request = $this->requestStack->getCurrentRequest();

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        $this->dispatcher->dispatch(
            new ApiUpdateEntry($request, $sectionHandle),
            ApiUpdateEntry::NAME
        );

        if ($abort = $this->shouldAbort($request)) {
            return $abort;
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

            if ($form->isSubmitted() && $form->isValid()) {
                $newEntry = $form->getData();
                $this->dispatcher->dispatch(
                    new ApiBeforeEntryUpdatedAfterValidated(
                        $request,
                        $responseData,
                        $jsonResponse,
                        $originalEntry,
                        $newEntry
                    ),
                    ApiBeforeEntryUpdatedAfterValidated::NAME
                );

                if ($abort = $this->shouldAbort($request)) {
                    return $abort;
                }

                $responseData = $this->save($form, $jsonResponse, $request);
                $this->purifyResponseData($responseData);
                $jsonResponse->setData($responseData);
                $this->dispatcher->dispatch(
                    new ApiEntryUpdated(
                        $request,
                        $responseData,
                        $jsonResponse,
                        $originalEntry,
                        $newEntry
                    ),
                    ApiEntryUpdated::NAME
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
    public function deleteEntryByIdAction(string $sectionHandle, int $id): JsonResponse
    {
        $request = $this->requestStack->getCurrentRequest();

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        $this->dispatcher->dispatch(
            new ApiDeleteEntry($request, $sectionHandle),
            ApiDeleteEntry::NAME
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
                new ApiEntryDeleted($request, $responseData, $jsonResponse, $entry),
                ApiEntryDeleted::NAME
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
    public function deleteEntryBySlugAction(string $sectionHandle, string $slug): JsonResponse
    {
        $request = $this->requestStack->getCurrentRequest();

        $optionsResponse = $this->preFlightOptions($request, self::ALLOWED_HTTP_METHODS);
        if ($optionsResponse) {
            return $optionsResponse;
        }

        $this->dispatcher->dispatch(
            new ApiDeleteEntry($request, $sectionHandle),
            ApiDeleteEntry::NAME
        );

        if ($abort = $this->shouldAbort($request)) {
            return $abort;
        }

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
                new ApiEntryDeleted($request, $responseData, $jsonResponse, $entry),
                ApiEntryDeleted::NAME
            );

            if ($abort = $this->shouldAbort($request)) {
                return $abort;
            }

            return $jsonResponse;
        } catch (\Exception $exception) {
            return $this->errorResponse($request, $exception);
        }
    }

    /**
     * Send along on options call
     *
     * @param Request $request
     * @param string $allowMethods
     * @return null|JsonResponse
     */
    protected function preFlightOptions(Request $request, string $allowMethods = 'OPTIONS'): ?JsonResponse
    {
        if (strtolower($request->getMethod()) === self::OPTIONS_CALL) {
            return new JsonResponse([], JsonResponse::HTTP_OK, [
                'Access-Control-Allow-Origin' => AccessControlAllowOrigin::get($request),
                'Access-Control-Allow-Methods' => $allowMethods,
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Allow-Headers' => 'token'
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

        $this->createSection->save($data);
        $responseData['success'] = true;
        $responseData['errors'] = false;
        $responseData['code'] = JsonResponse::HTTP_OK;
        $responseData['entry'] = $this->serialize->toArray($request, $data);

        $responseData = array_merge(json_decode($jsonResponse->getContent(), true), $responseData);

        return $responseData;
    }

    protected function shouldAbort(Request $request): ?JsonResponse
    {
        $abortCode = $request->get('abort');
        if ($abortCode) {
            return new JsonResponse(
                $request->get('abortMessage'),
                $abortCode,
                $this->getDefaultResponseHeaders($request)
            );
        }
        return null;
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
            if ($child->isSubmitted() && !$child->isValid()) {
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
        return [
            'Access-Control-Allow-Origin' => AccessControlAllowOrigin::get($request),
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
            'error' => $exception->getMessage()
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

        if (!is_null($fields) && is_string($fields)) {
            $fields = array_map('trim', explode(',', $fields));
        }

        return $fields;
    }

    protected function purifyResponseData(array &$responseData): void
    {
        array_walk_recursive(
            $responseData,
            function (&$value) {
                $value = is_string($value) ? $this->purifier->purify($value) : $value;
            }
        );
    }
}
