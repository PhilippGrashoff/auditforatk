<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit;

use Atk4\Data\Model;

class MessageRenderer
{

    public string $timeFormat = 'H:i';
    public string $dateFormat = 'Y-m-d';
    public string $dateTimeFormat = 'Y-m-d H:i';

    public function renderCreatedMessage(Audit $audit, ?Model $entity = null): string
    {
        return 'created ' . $entity->getModelCaption();
    }

    public function renderDeletedMessage(Audit $audit, ?Model $entity = null): string
    {
        return 'deleted ' . $entity->getModelCaption();
    }

    public function renderFieldAudit(Audit $audit): string
    {
        $auditData = $audit->get('data');
        switch ($auditData['fieldType']) {
            case "time":
                return $this->renderDateTimeFieldAudit($audit, $this->timeFormat);
            case "date":
                return $this->renderDateTimeFieldAudit($audit, $this->dateFormat);
            case "datetime":
                return $this->renderDateTimeFieldAudit($audit, $this->dateTimeFormat);
            default:
                return $this->renderNormalFieldAudit($audit);
        }
    }

    public function renderDateTimeFieldAudit(Audit $audit, string $format): string
    {
        $auditData = $audit->get('data');
        if ($auditData['oldValue'] instanceof \DateTimeInterface) {
            $renderedMessage = 'changed "' . $auditData['fieldName']
                . '" from "' . $auditData['oldValue']->format($format) . '" to ';
        } else {
            $renderedMessage = 'set "' . $auditData['fieldName'] . ' to ';
        }
        if ($auditData['newValue'] instanceof \DateTimeInterface) {
            $renderedMessage .= '"' . $auditData['newValue']->format($format) . '"';
        } else {
            $renderedMessage .= '"' . $auditData['newValue'] . '"';
        }

        return $renderedMessage;
    }


    public function renderNormalFieldAudit(Audit $audit): string
    {
        $auditData = $audit->get('data');
        if ($auditData['oldValue']) {
            $renderedMessage = 'changed "' . $auditData['fieldName'] . '" from "' . $auditData['oldValue'] . '" to ';
        } else {
            $renderedMessage = 'set "' . $auditData['fieldName'] . ' to ';
        }
        $renderedMessage .= '"' . $auditData['newValue'] . '"';

        return $renderedMessage;
    }

    //as only the ID is stored, we need to load the referenced records to get their title in order to make
    //human-readable Audit
    public function renderHasOneAudit(Audit $audit): string
    {
        $entity = $audit->getParentEntity();
        $auditData = $audit->get('data');
        $oldEntity = null;
        $newEntity = null;
        if ($auditData['oldValue']) {
            $oldEntity = $entity->refModel($auditData['fieldName']);
            $oldEntity->onlyFields = [$oldEntity->idField, $oldEntity->titleField];
            $oldEntity->load($auditData['oldValue']);
        }
        if ($auditData['newValue']) {
            $newEntity = $entity->refModel($auditData['fieldName']);
            $newEntity->onlyFields = [$newEntity->idField, $newEntity->titleField];
            $newEntity->load($entity->get($auditData['newValue']));
        }
        if ($oldEntity) {
            $renderedMessage = 'changed "' . $auditData['fieldName'] . '" from "' . $oldEntity->getTitle() . '" to ';
        } else {
            $renderedMessage = 'set "' . $auditData['fieldName'] . ' to ';
        }
        $renderedMessage .= '"' . ($newEntity ? $newEntity->getTitle() : $auditData['newValue']) . '"';

        return $renderedMessage;
    }
}
