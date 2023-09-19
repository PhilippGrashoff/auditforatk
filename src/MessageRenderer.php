<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Reference\HasOne;
use DateTimeInterface;

class MessageRenderer
{

    public string $timeFormat = 'H:i';
    public string $dateFormat = 'Y-m-d';
    public string $dateTimeFormat = 'Y-m-d H:i';

    /**
     * @param Audit $audit
     * @param Model $entity
     * @return string
     */
    public function renderCreatedMessage(Audit $audit, Model $entity): string
    {
        return 'created ' . $entity->getModelCaption();
    }

    /**
     * @param Audit $audit
     * @param Model $entity
     * @return string
     */
    public function renderDeletedMessage(Audit $audit, Model $entity): string
    {
        return 'deleted ' . $entity->getModelCaption();
    }

    /**
     * @param Audit $audit
     * @param Model $entity
     * @return string
     * @throws \Atk4\Core\Exception
     * @throws Exception
     */
    public function renderFieldAudit(Audit $audit, Model $entity): string
    {
        $auditData = $audit->get('data');
        //hasOne references
        if (
            $entity->hasReference($audit->get('ident'))
            && $entity->getModel()->getReference($audit->get('ident')) instanceof HasOne
        ) {
            return $this->renderHasOneAudit($audit, $entity);
        }
        //fields with key-value lists
        if (
            is_array($entity->getField($audit->get('ident'))->values)
            && count($entity->getField($audit->get('ident'))->values) > 0
        ) {
            $this->renderKeyValueAudit($audit, $entity);
        }

        return match ($auditData->fieldType) {
            "time" => $this->renderDateTimeFieldAudit($audit, $entity, $this->timeFormat),
            "date" => $this->renderDateTimeFieldAudit($audit, $entity, $this->dateFormat),
            "datetime" => $this->renderDateTimeFieldAudit($audit, $entity, $this->dateTimeFormat),
            'json', 'object' => $this->renderJsonFieldAudit($audit, $entity),
            default => $this->renderScalarFieldAudit($audit, $entity),
        };
    }

    /**
     * @param Audit $audit
     * @param Model $entity
     * @param string $format
     * @return string
     * @throws Exception
     * @throws \Atk4\Core\Exception
     */
    public function renderDateTimeFieldAudit(Audit $audit, Model $entity, string $format): string
    {
        $auditData = $audit->get('data');
        if ($auditData->oldValue instanceof DateTimeInterface) {
            $renderedMessage = 'changed "' . $entity->getField($audit->get('ident'))->getCaption()
                . '" from "' . $auditData->oldValue->format($format) . '" to ';
        } else {
            $renderedMessage = 'set "' . $entity->getField($audit->get('ident'))->getCaption() . ' to ';
        }
        if ($auditData->newValue instanceof DateTimeInterface) {
            $renderedMessage .= '"' . $auditData->newValue->format($format) . '"';
        } else {
            $renderedMessage .= '"' . $auditData->newValue . '"';
        }

        return $renderedMessage;
    }

    /**
     * @param Audit $audit
     * @param Model $entity
     * @return string
     * @throws Exception
     * @throws \Atk4\Core\Exception
     */
    public function renderScalarFieldAudit(Audit $audit, Model $entity): string
    {
        $auditData = $audit->get('data');
        if ($auditData->oldValue) {
            $renderedMessage = 'changed "' . $entity->getField($audit->get('ident'))->getCaption()
                . '" from "' . $auditData->oldValue . '" to ';
        } else {
            $renderedMessage = 'set "' . $entity->getField($audit->get('ident'))->getCaption() . ' to ';
        }
        $renderedMessage .= '"' . $auditData->newValue . '"';

        return $renderedMessage;
    }

    /**
     * @param Audit $audit
     * @param Model $entity
     * @return string
     * @throws Exception
     * @throws \Atk4\Core\Exception
     */
    public function renderJsonFieldAudit(Audit $audit, Model $entity): string
    {
        $auditData = $audit->get('data');
        if ($auditData->oldValue) {
            $renderedMessage = 'changed "' . $entity->getField($audit->get('ident'))->getCaption() . '" from "'
                . json_encode($auditData->oldValue) . '" to ';
        } else {
            $renderedMessage = 'set "' . $entity->getField($audit->get('ident'))->getCaption() . ' to ';
        }
        $renderedMessage .= '"' . ($auditData->newValue ? json_encode($auditData->newValue) : '') . '"';

        return $renderedMessage;
    }

    /**
     * as only the ID is stored, we need to load the referenced records to get their title in order to make
     * human-readable Audit
     *
     * @param Audit $audit
     * @param Model $entity
     * @return string
     * @throws Exception
     * @throws \Atk4\Core\Exception
     */
    public function renderHasOneAudit(Audit $audit, Model $entity): string
    {
        $auditData = $audit->get('data');
        $oldEntity = null;
        $newEntity = null;
        if ($auditData->oldValue) {
            $oldEntity = $entity->refModel($audit->get('ident'));
            $oldEntity->onlyFields = [$oldEntity->idField, $oldEntity->titleField];
            $oldEntity = $oldEntity->load($auditData->oldValue);
        }
        if ($auditData->newValue) {
            $newEntity = $entity->refModel($audit->get('ident'));
            $newEntity->onlyFields = [$newEntity->idField, $newEntity->titleField];
            $newEntity = $newEntity->load($auditData->newValue);
        }

        if ($oldEntity !== null) {
            $renderedMessage = 'changed "' . $entity->getField($audit->get('ident'))->getCaption()
                . '" from "' . $oldEntity->getTitle() . '" to ';
        } else {
            $renderedMessage = 'set "' . $entity->getField($audit->get('ident'))->getCaption() . ' to ';
        }
        $renderedMessage .= '"' . ($newEntity !== null ? $newEntity->getTitle() : $auditData->newValue) . '"';

        return $renderedMessage;
    }

    /**
     * @param Audit $audit
     * @param Model $entity
     * @return string
     * @throws Exception
     * @throws \Atk4\Core\Exception
     */
    protected function renderKeyValueAudit(Audit $audit, Model $entity): string
    {
        $values = $entity->getField($audit->get('ident'))->values;
        $auditData = $audit->get('data');
        $oldValueTitle = $newValueTitle = '';

        if (isset($values[$auditData->oldValue])) {
            $oldValueTitle = $values[$auditData->oldValue];
        }
        if (isset($values[$auditData->newValue])) {
            $newValueTitle = $values[$auditData->newValue];
        }

        if ($auditData->oldValue) {
            $renderedMessage = 'changed "' . $entity->getField($audit->get('ident'))->getCaption()
                . '" from "' . $oldValueTitle . '" to ';
        } else {
            $renderedMessage = 'set "' . $entity->getField($audit->get('ident'))->getCaption() . ' to ';
        }
        $renderedMessage .= '"' . $newValueTitle . '"';

        return $renderedMessage;
    }
}
