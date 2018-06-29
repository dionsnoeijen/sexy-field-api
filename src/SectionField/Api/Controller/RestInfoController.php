<?php
declare(strict_types=1);

namespace Tardigrades\SectionField\Api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Tardigrades\Entity\FieldInterface;
use Tardigrades\FieldType\Relationship\Relationship;
use Tardigrades\SectionField\Event\ApiEntryFetched;
use Tardigrades\SectionField\Generator\CommonSectionInterface;
use Tardigrades\SectionField\Service\EntryNotFoundException;
use Tardigrades\SectionField\Service\ReadOptions;
use Tardigrades\SectionField\ValueObject\Handle;
use Tardigrades\SectionField\Service\ReadOptionsInterface;

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
            $section = $this->sectionManager->readByHandle(Handle::fromString($sectionHandle));
            $responseData = [
                'name' => (string) $section->getName(),
                'handle' => (string) $section->getHandle()
            ];

            $showFields = $this->getFields();
            $fieldProperties = $this->getEntityProperties($sectionHandle);

            /** @var FieldInterface $field */
            foreach ($section->getFields() as $field) {
                $fieldHandle = $this->handleToPropertyName((string) $field->getHandle(), $fieldProperties);

                if (is_null($showFields) || in_array($fieldHandle, $showFields)) {
                    // Default initial configuration
                    $fieldInfo = [ $fieldHandle => $field->getConfig()->toArray()['field'] ];

                    // If we have a relationship field, get the entries
                    if ((string) $field->getFieldType()->getFullyQualifiedClassName() === Relationship::class) {
                        $fieldInfo = $this->getRelationshipsTo(
                            $fieldHandle,
                            $fieldInfo,
                            $sectionHandle,
                            (int) $id
                        );
                    }
                    $responseData['fields'][] = $fieldInfo;
                }
            }

            $responseData = array_merge($responseData, $section->getConfig()->toArray());
            $responseData['fields'] = $this->orderFields($responseData, $fieldProperties, $showFields);
            $responseData = $this->cleanFields($responseData);

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

                $responseData = $this->mapEntryToFields($responseData, $entry, $fieldProperties);
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
     * Use the handle to convert it to the actual property name
     *
     * @param string $handle
     * @param array $fieldProperties
     * @return string
     */
    private function handleToPropertyName(string $handle, array $fieldProperties): string
    {
        foreach ($fieldProperties as $propertyName=>$property) {
            if ($property['handle'] === $handle) {
                return $propertyName;
            }
        }

        return $handle;
    }

    /**
     * Make sure the entry values are passed on to the fields
     *
     * @param array $responseData
     * @param CommonSectionInterface $entry
     * @param array $fieldProperties
     * @return array
     */
    private function mapEntryToFields(
        array $responseData,
        CommonSectionInterface $entry,
        array $fieldProperties
    ): array {

        foreach ($responseData['fields'] as $key=>&$field) {
            $value = null;
            $fieldHandle = $field[key($field)]['handle'];
            try {
                $mapsTo = $field[key($field)]['form']['sexy-field-instructions']['maps-to'];
                $mapsTo = explode('|', $mapsTo);
            } catch (\Exception $exception) {
                $mapsTo = $fieldHandle;
            }
            if (is_array($mapsTo)) {
                $find = $entry;
                foreach ($mapsTo as $property) {
                    $method = 'get' . ucfirst($property);
                    if ($find) {
                        $find = $find->$method();
                    }
                }
                $value = $find ? (string) $find : null;
            } else {
                try {
                    if (strpos(strtolower($fieldHandle), 'slug') !== false) {
                        $method = 'getSlug';
                    } else {
                        $method = 'get' . ucfirst($this->handleToPropertyName($fieldHandle, $fieldProperties));
                    }
                    $data = $entry->$method();
                    if ($data instanceof \DateTime) {
                        $value = $data->format('Y-m-d H:i');
                    } else {
                        $value = (string) $data;
                    }
                } catch (\Exception $exception) {
                    //
                }
            }
            $field[$fieldHandle]['value'] = $value;
            $value = null;
        }

        return $responseData;
    }

    /**
     * Make sure the fields are returned in the order you have configured them
     *
     * @todo I feel this method is lacking a lot of elegance, make better when the brain is energised.
     *
     * @param array $fields
     * @param array $fieldProperties
     * @return array
     */
    private function orderFields(array $fields, array $fieldProperties, array $showFields = null): array
    {
        $originalFields = $fields['fields'];
        $desiredFieldsOrder = $fields['section']['fields'];
        $desiredFieldsOrderFiltered = [];

        if (!is_null($showFields)) {
            foreach ($desiredFieldsOrder as $fieldHandle) {
                $propertyName = $this->handleToPropertyName($fieldHandle, $fieldProperties);
                if (in_array($propertyName, $showFields)) {
                    $desiredFieldsOrderFiltered[] = $propertyName;
                }
            }
        } else {
            $desiredFieldsOrderFiltered = $desiredFieldsOrder;
        }

        $originalFieldsOrder = [];
        $result = [];

        foreach ($originalFields as $field) {
            $handle = array_keys($field)[0];
            if (is_null($showFields) || in_array($handle, $showFields)) {
                $originalFieldsOrder[] = $handle;
            }
        }

        foreach ($desiredFieldsOrderFiltered as $handle) {
            if (is_null($showFields) || in_array($handle, $showFields)) {
                $fieldIndex = array_search($handle, $originalFieldsOrder);
                $result[] = $originalFields[$fieldIndex];
            }
        }

        return $result;
    }

    /**
     * Just remove stuff that's not needed for a frontend
     *
     * @todo: I might want to introduce a method in the field that only returns the relevant
     * data for the front-end
     *
     * @param array $fields
     * @return array
     */
    private function cleanFields(array $fields): array
    {
        foreach ($fields['fields'] as &$field) {
            if (array_key_exists('generator', $field[key($field)])) {
                unset($field[key($field)]['generator']);
            }
        }

        unset($fields['section']);

        return $fields;
    }

    /**
     * Get the built in field mapping
     *
     * @param string $sectionHandle
     * @return array
     */
    private function getEntityProperties(string $sectionHandle): array
    {
        $entity = $this->form->buildFormForSection(
            $sectionHandle,
            $this->requestStack,
            null,
            false
        )->getData();

        return $entity::FIELDS;
    }

    /**
     * Get entries to populate a relationships field
     *
     * @param string $fieldHandle
     * @param array $fieldInfo
     * @param string $sectionHandle
     * @param int|null $id
     * @return array|null
     */
    private function getRelationshipsTo(
        string $fieldHandle,
        array $fieldInfo,
        string $sectionHandle,
        int $id = null
    ): ?array {

        if (!empty($fieldInfo[$fieldHandle]['to'])) {
            try {
                $sexyFieldInstructions = $this->getSexyFieldRelationshipInstructions($fieldInfo, $fieldHandle);

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

                $to = $this->readSection->read(
                    $this->getRelationshipReadOptions($fieldHandle, $fieldInfo, $sexyFieldInstructions)
                );
                $fieldInfo[$fieldHandle][$fieldInfo[$fieldHandle]['to']] = [];

                /** @var CommonSectionInterface $entry */
                foreach ($to as $entry) {

                    // Map the name-expression to override naming
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

                    // Add data if configured
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
                $fieldInfo = $this->setSelectedRelationshipsTo(
                    $sectionHandle,
                    $fieldHandle,
                    $fieldInfo,
                    $id
                );
            }
        }

        return $fieldInfo;
    }

    /**
     * We may have special instructions configured
     *
     * @param array $fieldInfo
     * @param string $fieldHandle
     * @return array|null
     */
    private function getSexyFieldRelationshipInstructions(array $fieldInfo, string $fieldHandle): ?array
    {
        return !empty($fieldInfo[$fieldHandle]['form']['sexy-field-instructions']['relationship']) ?
            $fieldInfo[$fieldHandle]['form']['sexy-field-instructions']['relationship'] : null;
    }

    /**
     * For a relationship, we may have options of configured instructions
     *
     * @param string $fieldHandle
     * @param array $fieldInfo
     * @param array|null $sexyFieldInstructions
     * @return ReadOptionsInterface
     */
    private function getRelationshipReadOptions(
        string $fieldHandle,
        array $fieldInfo,
        array $sexyFieldInstructions = null
    ): ReadOptionsInterface {
        $options = $this->getOptions();

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

        return ReadOptions::fromArray($readOptions);
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
            $method = $this->handleToPropertyName($fieldHandle, $editing::FIELDS);
            $relationshipsEntityMethod = 'get' . ucfirst($method);
            $related = $editing->{$relationshipsEntityMethod}();
            if (!is_null($related)) {
                $relatedIds = [];
                if (is_iterable($related)) {
                    /** @var CommonSectionInterface $relation */
                    foreach ($related as $relation) {
                        $relatedIds[] = $relation->getId();
                    }
                } else {
                    $relatedIds[] = $related->getId();
                }
                foreach ($fieldInfo[$fieldHandle][$fieldInfo[$fieldHandle]['to']] as &$relatable) {
                    if (!empty($relatable['id']) && in_array($relatable['id'], $relatedIds)) {
                        $relatable['selected'] = true;
                    }
                }
            }
        } catch (\Exception $exception) {
            // Empty because we can just return the fieldInfo the way it was if the above fails
        }

        return $fieldInfo;
    }

    /**
     * You can restrict the fields you desire to see
     *
     * @return array|null
     */
    private function getFields(): ?array
    {
        $fields = $this->requestStack->getCurrentRequest()->get('fields');

        if (!is_null($fields)) {
            $fields = array_map('trim', explode(',', $fields));
        }

        return $fields;
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
     *
     * @return array|null
     */
    private function getOptions(): ?array
    {
        $requestOptions = $this->requestStack->getCurrentRequest()->get('options');

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
