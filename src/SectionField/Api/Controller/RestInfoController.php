<?php
declare(strict_types=1);

namespace Tardigrades\SectionField\Api\Controller;

use Doctrine\Common\Util\Inflector;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Tardigrades\Entity\FieldInterface;
use Tardigrades\FieldType\Relationship\Relationship;
use Tardigrades\SectionField\Event\ApiEntryFetched;
use Tardigrades\SectionField\Generator\CommonSectionInterface;
use Tardigrades\SectionField\Service\EntryNotFoundException;
use Tardigrades\SectionField\Service\ReadOptions;
use Tardigrades\SectionField\ValueObject\Handle;

class RestInfoController extends RestController implements RestControllerInterface
{
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
                $fieldInfo = [
                    (string) $field->getHandle() => $field->getConfig()->toArray()['field']
                ];

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
                /** @var CommonSectionInterface $entry */
                $entry = $this->readSection->read(ReadOptions::fromArray([
                    ReadOptions::SECTION => $sectionHandle,
                    ReadOptions::ID => (int) $id
                ]))->current();

                $responseData['entry'] = $this->serialize->toArray($request, $entry);
                $responseData = $this->mapEntryToFields($responseData);
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

    private function mapEntryToFields(array $responseData): array
    {
        foreach ($responseData['fields'] as $key=>&$field) {
            $fieldHandle = $field[key($field)]['handle'];
            if (!empty($field[key($field)]['to'])) {
                $fieldHandle = $field[key($field)]['to'];
            }
            if (!empty($field[key($field)]['as'])) {
                $fieldHandle = $field[key($field)]['as'];
            }

            try {
                $mapsTo = explode('|', $field[key($field)]['form']['sexy-field-instructions']['maps-to']);
            } catch (\Exception $exception) {
                $mapsTo = $fieldHandle;
            }

            if (is_array($mapsTo)) {
                $value = $responseData['entry'];
                foreach ($mapsTo as $map) {
                    if (is_string($map) &&
                        is_array($value) &&
                        !empty($value[$map])
                    ) {
                        $value = $value[$map];
                    } else {
                        $value = '';
                    }
                }
            } else {
                if (!empty($responseData['entry'][$mapsTo])) {
                    $value = $responseData['entry'][$mapsTo];
                } else {
                    $value = '';
                }
            }
            $field[$fieldHandle]['value'] = $value;
        }

        return $responseData;
    }

    private function orderFields(array $fields): array
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

        if (!empty($fieldInfo[$fieldHandle]['to'])) {
            try {
                $options = $this->getOptions($request);
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

                // You can add limitations for the relationship through config
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

                // You can add limitations for the relationship through get options
                if (!empty($options) &&
                    !empty($options[$fieldHandle]) &&
                    !empty($options[$fieldHandle]['field']) &&
                    !empty($options[$fieldHandle]['value'])
                ) {
                    if (strpos((string) $options[$fieldHandle]['value'], ',') !== false) {
                        $options[$fieldHandle]['value'] = explode(',', $options[$fieldHandle]['value']);
                    }
                    $readOptions[ReadOptions::FIELD] = [
                        $options[$fieldHandle]['field'] => $options[$fieldHandle]['value']
                    ];
                }

                // You can add limitations for the relationship through get options
                if (!empty($options) &&
                    !empty($options[$fieldHandle]) &&
                    !empty($options[$fieldHandle]['join']) &&
                    !empty($options[$fieldHandle]['value'])
                ) {
                    if (strpos((string) $options[$fieldHandle]['value'], ',') !== false) {
                        $options[$fieldHandle]['value'] = explode(',', $options[$fieldHandle]['value']);
                    }
                    $readOptions[ReadOptions::JOIN] = [
                        $options[$fieldHandle]['join'] => $options[$fieldHandle]['value']
                    ];
                }

                // You can have a different name for elements through config
                $nameExpression = [];
                if (!empty($sexyFieldInstructions) &&
                    !empty($sexyFieldInstructions['name-expression'])
                ) {
                    $nameExpression = explode('|', $sexyFieldInstructions['name-expression']);
                }

                // Maybe you want to add some more fields to the relationship content
                $addFields = [];
                if (!empty($sexyFieldInstructions) &&
                    !empty($sexyFieldInstructions['add-fields'])
                ) {
                    $addFields = $sexyFieldInstructions['add-fields'];
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

                    // Default relationship data
                    $data = [
                        'id' => $entry->getId(),
                        'slug' => (string) $entry->getSlug(),
                        'name' => $name,
                        'created' => $entry->getCreated(),
                        'updated' => $entry->getUpdated(),
                        'selected' => false
                    ];

                    foreach ($addFields as $field) {
                        $method = 'get' . ucfirst($field);
                        $data[$field] = (string) $entry->{$method}();
                    }

                    $fieldInfo[$fieldHandle][$fieldInfo[$fieldHandle]['to']][] = $data;
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

    /**
     * @param string $needle
     * @param array $search
     * @return null|string
     */
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

        if (is_null($requestOptions)) {
            return null;
        }

        $requestOptions = explode(',', $requestOptions);
        $options = [];
        foreach ($requestOptions as $requestOption) {
            $requestOption = explode('|', $requestOption);
            $fieldHandle = array_shift($requestOption);
            $options[$fieldHandle] = [];
            foreach ($requestOption as $option) {
                $keyValue = explode(':', $option);
                $options[$fieldHandle][$keyValue[0]] = $keyValue[1];
            }
        }

        return $options;
    }
}
