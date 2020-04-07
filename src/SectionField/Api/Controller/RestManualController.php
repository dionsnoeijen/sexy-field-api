<?php

namespace Tardigrades\SectionField\Api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class RestManualController extends RestInfoAutoController
{
    /**
     * get-me-an-entry-by-id:
     *    path: /v1/whatever-you-want/{id}
     *    controller: Tardigrades\SectionField\Api\RestManualController:get
     *    methods: [ GET, OPTIONS ]
     *    defaults:
     *        sectionHandle: sectionName
     *        id: 10 # <- This is optional, instead of passing it through the
     *               # route pass it hardcoded, it will act as a default, meaning
     *               # that once you do allow it being passed through the route this
     *               # will be used once it's not used in the route.
     *        fields:
     *            - id
     *            - name
     *            - anyField
     *
     * @param string|null $sectionHandle
     * @param string|null $fieldHandle
     * @param string|null $id
     * @param string|null $slug
     * @param string|null $offset
     * @param string|null $limit
     * @param string|null $orderBy
     * @param string|null $sort
     * @param string|null $value
     * @param string|null $depth
     * @param array $fields
     *
     * @return JsonResponse
     */
    public function getAction(
        string $sectionHandle = null,
        string $fieldHandle = null,
        string $id = null,
        string $slug = null,
        string $offset = null,
        string $limit = null,
        string $orderBy = null,
        string $sort = null,
        string $value = null,
        string $depth = null,
        array $fields = []
    ): JsonResponse {
        $request = $this->requestStack->getCurrentRequest();
        $method = $request->getMethod();
        $request->attributes->add([
            'fields' => $fields,
            'offset' => $offset,
            'limit' => $limit,
            'orderBy' => $orderBy,
            'sort' => $sort,
            'value' => $value,
            'depth' => $depth
        ]);
        switch ($method) {
            case Request::METHOD_OPTIONS:
                return $this->preFlightOptions($request);
            case Request::METHOD_GET:
                if (!is_null($id)) {
                    return $this->getEntryByIdAction($sectionHandle, $id);
                }
                if (!is_null($slug)) {
                    return $this->getEntryBySlugAction($sectionHandle, $slug);
                }
                if (!is_null($fieldHandle)) {
                    return $this->getEntriesByFieldValueAction($sectionHandle, $fieldHandle);
                }
                return $this->getEntriesAction($sectionHandle);
            default:
                return JsonResponse::create([
                    'error' => 'invalid_routing_configuration',
                ], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    /**
     * post-a-record:
     *     path: /v1/some/product
     *     controller: Tardigrades\SectionField\Api\RestManualController:post
     *     methods: [ POST, OPTIONS ]
     *     defaults:
     *         sectionHandle: someHandle
     *
     * @param string $sectionHandle
     * @param string|null $depth
     * @param array $fields
     * @return JsonResponse
     */
    public function postAction(
        string $sectionHandle = null,
        string $depth = null,
        array $fields = []
    ): JsonResponse {
        $request = $this->requestStack->getCurrentRequest();
        $method = $request->getMethod();
        $request->attributes->add([
            'fields' => $fields,
            'depth' => $depth
        ]);
        switch ($method) {
            case Request::METHOD_OPTIONS:
                return $this->preFlightOptions($request);
            case Request::METHOD_POST:
                return $this->createEntryAction($sectionHandle);
            default:
                return JsonResponse::create([
                    'error' => 'invalid_routing_configuration'
                ], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    /**
     * put-a-record:
     *     path: /v1/the/path/and/its/arguments/are/for/you/to/decide
     *     controller: Tardigrades\SectionField\Api\RestManualController:put
     *     methods: [ PUT, OPTIONS ]
     *     defaults:
     *        sectionHandle: something
     *
     * @param string|null $sectionHandle
     * @param string|null $id
     * @param string|null $slug ,
     * @param string|null $depth
     * @param array $fields
     * @return JsonResponse
     */
    public function putAction(
        string $sectionHandle = null,
        string $id = null,
        string $slug = null,
        string $depth = null,
        array $fields = []
    ): JsonResponse {
        $request = $this->requestStack->getCurrentRequest();
        $method = $request->getMethod();
        $request->attributes->add([
            'fields' => $fields,
            'depth' => $depth
        ]);
        switch ($method) {
            case Request::METHOD_OPTIONS:
                return $this->preFlightOptions($request);
            case Request::METHOD_PUT:
                if (!is_null($id)) {
                    return $this->updateEntryByIdAction($sectionHandle, $id);
                }
                if (!is_null($slug)) {
                    return $this->updateEntryBySlugAction($sectionHandle, $slug);
                }
                break;
            default:
                return JsonResponse::create([
                    'error' => 'invalid_routing_configuration'
                ], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    /**
     * delete-a-record:
     *     path: /v1/the/path/and/its/arguments/are/for/you/to/decide
     *     controller: Tardigrades\SectionField\Api\RestManualController:delete
     *     methods: [ DELETE, OPTIONS ]
     *     defaults:
     *        sectionHandle: something
     *
     * @param string|null $sectionHandle
     * @param string|null $id
     * @param string|null $slug
     * @return JsonResponse
     */
    public function deleteAction(
        string $sectionHandle = null,
        string $id = null,
        string $slug = null
    ): JsonResponse {
        $request = $this->requestStack->getCurrentRequest();
        $method = $request->getMethod();
        switch ($method) {
            case Request::METHOD_OPTIONS:
                return $this->preFlightOptions($request);
            case Request::METHOD_DELETE:
                if (!is_null($id)) {
                    return $this->deleteEntryByIdAction($sectionHandle, $id);
                }
                if (!is_null($slug)) {
                    return $this->deleteEntryBySlugAction($sectionHandle, $slug);
                }
                break;
            default:
                return JsonResponse::create([
                    'error' => 'invalid_routing_configuration'
                ], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    /**
     * give-me-info:
     *     path: /what/is/that/section/even/like
     *     controller: Tardigrades\SectionField\Api\RestManualController:info
     *     methods: [ GET, OPTIONS ]
     *     defaults:
     *        sectionHandle: something
     *        options: someRelationshipFieldHandle|limit:100|offset:0
     *
     * @param string|null $sectionHandle
     * @param string|null $id
     * @param string|null $slug
     * @param string|null $options
     * @param string|null $depth
     * @param array|null $fields
     * @return JsonResponse
     */
    public function infoAction(
        string $sectionHandle = null,
        string $id = null,
        string $slug = null,
        string $options = null,
        string $depth = null,
        array $fields = null
    ): JsonResponse {
        $request = $this->requestStack->getCurrentRequest();
        $method = $request->getMethod();
        $request->attributes->add([
            'fields' => $fields,
            'depth' => $depth,
            'options' => $options
        ]);
        switch ($method) {
            case Request::METHOD_OPTIONS:
                return $this->preFlightOptions($request);
            case Request::METHOD_GET:
                if (!is_null($id)) {
                    return $this->getSectionInfoByIdAction($sectionHandle, $id);
                }
                if (!is_null($slug)) {
                    return $this->getSectionInfoBySlugAction($sectionHandle, $slug);
                }
                return $this->getSectionInfoByIdAction($sectionHandle);
            default:
                return JsonResponse::create([
                    'error' => 'invalid_routing_configuration'
                ], JsonResponse::HTTP_BAD_REQUEST);
        }
    }
}
