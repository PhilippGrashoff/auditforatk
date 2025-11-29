<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Reference\HasOne;
use DateTime;
use DateTimeInterface;

class MessageRenderer
{

    public string $timeFormat = 'H:i';
    public string $dateFormat = 'Y-m-d';
    public string $dateTimeFormat = 'Y-m-d H:i';

    // Message templates with named placeholders
    public string $changedWithOldValueTemplate = 'changed "{fieldName}" from "{oldValue}" to "{newValue}"';
    public string $changedNoOldValueTemplate = 'set "{fieldName}" to "{newValue}"';
    public string $changedWithoutValueAuditTemplate = 'changed "{fieldName}"';
    public string $createdTemplate = 'created {modelCaption}';
    public string $deletedTemplate = 'deleted {modelCaption}';

    /**
     * @param Audit $audit
     * @param Model $entity
     * @return string
     */
    public function renderCreatedMessage(Audit $audit, Model $entity): string
    {
        return $this->renderTemplate($this->createdTemplate, ['modelCaption' => $entity->getModel()->getModelCaption()]);
    }

    /**
     * @param Audit $audit
     * @param Model $entity
     * @return string
     */
    public function renderDeletedMessage(Audit $audit, Model $entity): string
    {
        return $this->renderTemplate($this->deletedTemplate, ['modelCaption' => $entity->getModel()->getModelCaption()]);

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
        $auditData = $audit->getData();
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
            return $this->renderKeyValueAudit($audit, $entity);
        }

        return match ($auditData->fieldType) {
            'time' => $this->renderDateTimeFieldAudit($audit, $entity, $this->timeFormat),
            'date' => $this->renderDateTimeFieldAudit($audit, $entity, $this->dateFormat),
            'datetime' => $this->renderDateTimeFieldAudit($audit, $entity, $this->dateTimeFormat),
            'json', 'object' => $this->renderJsonFieldAudit($audit, $entity),
            default => $this->renderScalarFieldAudit($audit, $entity),
        };
    }

    /**
     * @param Audit $audit
     * @param Model $entity
     * @return string
     * @throws Exception
     * @throws \Atk4\Core\Exception
     */
    public function renderFieldAuditWithoutValues(Audit $audit, Model $entity): string
    {
        $fieldCaption = $entity->getField($audit->get('ident'))->getCaption();
        return $this->renderTemplate($this->changedWithoutValueAuditTemplate, ['fieldName' => $fieldCaption]);
    }

    /**
     * Replace named placeholders in the template with actual values
     *
     * @param string $template
     * @param array<string, string> $replacements
     * @return string
     */
    protected function renderTemplate(string $template, array $replacements): string
    {
        foreach ($replacements as $placeholder => $value) {
            $template = str_replace('{' . $placeholder . '}', (string)$value, $template);
        }
        return $template;
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
        $auditData = $audit->getData();
        $fieldCaption = $entity->getField($audit->get('ident'))->getCaption();

        $oldDateTime = DateTime::createFromFormat(DATE_ATOM, (string)$auditData->oldValue);
        $newDateTime = DateTime::createFromFormat(DATE_ATOM, (string)$auditData->newValue);

        if ($oldDateTime instanceof DateTimeInterface) {
            $oldValue = $oldDateTime->format($format);
            $newValue = $newDateTime instanceof DateTimeInterface
                ? $newDateTime->format($format)
                : (string)$auditData->newValue;

            return $this->renderTemplate($this->changedWithOldValueTemplate, [
                'fieldName' => $fieldCaption,
                'oldValue' => $oldValue,
                'newValue' => $newValue
            ]);
        } else {
            $newValue = $newDateTime instanceof DateTimeInterface
                ? $newDateTime->format($format)
                : (string)$auditData->newValue;

            return $this->renderTemplate($this->changedNoOldValueTemplate, [
                'fieldName' => $fieldCaption,
                'newValue' => $newValue
            ]);
        }
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
        $auditData = $audit->getData();
        $fieldCaption = $entity->getField($audit->get('ident'))->getCaption();

        if (strlen((string)$auditData->oldValue) > 0) {
            return $this->renderTemplate($this->changedWithOldValueTemplate, [
                'fieldName' => $fieldCaption,
                'oldValue' => (string)$auditData->oldValue,
                'newValue' => (string)$auditData->newValue
            ]);
        } else {
            return $this->renderTemplate($this->changedNoOldValueTemplate, [
                'fieldName' => $fieldCaption,
                'newValue' => (string)$auditData->newValue
            ]);
        }
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
        $auditData = $audit->getData();
        $fieldCaption = $entity->getField($audit->get('ident'))->getCaption();

        if ($auditData->oldValue) {
            $oldValue = json_encode($auditData->oldValue);
            $newValue = $auditData->newValue ? json_encode($auditData->newValue) : '';

            return $this->renderTemplate($this->changedWithOldValueTemplate, [
                'fieldName' => $fieldCaption,
                'oldValue' => (string)$oldValue,
                'newValue' => (string)$newValue
            ]);
        } else {
            $newValue = $auditData->newValue ? json_encode($auditData->newValue) : '';

            return $this->renderTemplate($this->changedNoOldValueTemplate, [
                'fieldName' => $fieldCaption,
                'newValue' => (string)$newValue
            ]);
        }
    }

    /**
     * Load a referenced entity with only ID and title fields for efficient display
     *
     * @param Model $entity
     * @param string $referenceIdent
     * @param mixed $value
     * @return Model|null
     */
    protected function loadReferencedEntityForDisplay(Model $entity, string $referenceIdent, mixed $value): ?Model
    {
        if (!$value) {
            return null;
        }

        $referencedModel = $entity->getModel()->getReference($referenceIdent)->createTheirModel();
        $referencedModel->onlyFields = [$referencedModel->idField, $referencedModel->titleField];
        return $referencedModel->load($value);
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
        $auditData = $audit->getData();
        $referenceIdent = $audit->get('ident');
        $fieldCaption = $entity->getField($audit->get('ident'))->getCaption();

        $oldEntity = $this->loadReferencedEntityForDisplay($entity, $referenceIdent, $auditData->oldValue);
        $newEntity = $this->loadReferencedEntityForDisplay($entity, $referenceIdent, $auditData->newValue);

        if ($oldEntity !== null) {
            $newValue = $newEntity !== null ? (string)$newEntity->getTitle() : (string)$auditData->newValue;

            return $this->renderTemplate($this->changedWithOldValueTemplate, [
                'fieldName' => $fieldCaption,
                'oldValue' => (string)$oldEntity->getTitle(),
                'newValue' => $newValue
            ]);
        } else {
            $newValue = $newEntity !== null ? (string)$newEntity->getTitle() : (string)$auditData->newValue;

            return $this->renderTemplate($this->changedNoOldValueTemplate, [
                'fieldName' => $fieldCaption,
                'newValue' => $newValue
            ]);
        }
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
        $auditData = $audit->getData();
        $fieldCaption = $entity->getField($audit->get('ident'))->getCaption();

        $oldValueTitle = $values[$auditData->oldValue] ?? '';
        $newValueTitle = $values[$auditData->newValue] ?? '';

        if (strlen((string)$auditData->oldValue) > 0) {
            return $this->renderTemplate($this->changedWithOldValueTemplate, [
                'fieldName' => $fieldCaption,
                'oldValue' => (string)$oldValueTitle,
                'newValue' => (string)$newValueTitle
            ]);
        } else {
            return $this->renderTemplate($this->changedNoOldValueTemplate, [
                'fieldName' => $fieldCaption,
                'newValue' => (string)$newValueTitle
            ]);
        }
    }
}