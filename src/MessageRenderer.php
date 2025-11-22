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

    // Message templates with named placeholders
    public string $changedTemplate = 'changed "{fieldName}" from "{oldValue}" to "{newValue}"';
    public string $setTemplate = 'set "{fieldName}" to "{newValue}"';
    public string $createdTemplate = 'created {modelCaption}';
    public string $deletedTemplate = 'deleted {modelCaption}';

    /**
     * @param Audit $audit
     * @param Model $entity
     * @return string
     */
    public function renderCreatedMessage(Audit $audit, Model $entity): string
    {
        return $this->renderTemplate($this->createdTemplate, ['{modelCaption}' => $entity->getModel()->getModelCaption()]);
    }

    /**
     * @param Audit $audit
     * @param Model $entity
     * @return string
     */
    public function renderDeletedMessage(Audit $audit, Model $entity): string
    {
        return $this->renderTemplate($this->deletedTemplate, ['{modelCaption}' => $entity->getModel()->getModelCaption()]);

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
            'time' => $this->renderDateTimeFieldAudit($audit, $entity, $this->timeFormat),
            'date' => $this->renderDateTimeFieldAudit($audit, $entity, $this->dateFormat),
            'datetime' => $this->renderDateTimeFieldAudit($audit, $entity, $this->dateTimeFormat),
            'json', 'object' => $this->renderJsonFieldAudit($audit, $entity),
            default => $this->renderScalarFieldAudit($audit, $entity),
        };
    }

    /**
     * Replace named placeholders in template with actual values
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
        $auditData = $audit->get('data');
        $fieldCaption = $entity->getField($audit->get('ident'))->getCaption();

        if ($auditData->oldValue instanceof DateTimeInterface) {
            $oldValue = $auditData->oldValue->format($format);
            $newValue = $auditData->newValue instanceof DateTimeInterface
                ? $auditData->newValue->format($format)
                : (string)$auditData->newValue;

            return $this->renderTemplate($this->changedTemplate, [
                'fieldName' => $fieldCaption,
                'oldValue' => $oldValue,
                'newValue' => $newValue
            ]);
        } else {
            $newValue = $auditData->newValue instanceof DateTimeInterface
                ? $auditData->newValue->format($format)
                : (string)$auditData->newValue;

            return $this->renderTemplate($this->setTemplate, [
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
        $auditData = $audit->get('data');
        $fieldCaption = $entity->getField($audit->get('ident'))->getCaption();

        if ($auditData->oldValue) {
            return $this->renderTemplate($this->changedTemplate, [
                'fieldName' => $fieldCaption,
                'oldValue' => (string)$auditData->oldValue,
                'newValue' => (string)$auditData->newValue
            ]);
        } else {
            return $this->renderTemplate($this->setTemplate, [
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
        $auditData = $audit->get('data');
        $fieldCaption = $entity->getField($audit->get('ident'))->getCaption();

        if ($auditData->oldValue) {
            $oldValue = json_encode($auditData->oldValue);
            $newValue = $auditData->newValue ? json_encode($auditData->newValue) : '';

            return $this->renderTemplate($this->changedTemplate, [
                'fieldName' => $fieldCaption,
                'oldValue' => (string)$oldValue,
                'newValue' => (string)$newValue
            ]);
        } else {
            $newValue = $auditData->newValue ? json_encode($auditData->newValue) : '';

            return $this->renderTemplate($this->setTemplate, [
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
     * @throws Exception
     * @throws \Atk4\Core\Exception
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
        $auditData = $audit->get('data');
        $referenceIdent = $audit->get('ident');
        $fieldCaption = $entity->getField($audit->get('ident'))->getCaption();

        $oldEntity = $this->loadReferencedEntityForDisplay($entity, $referenceIdent, $auditData->oldValue);
        $newEntity = $this->loadReferencedEntityForDisplay($entity, $referenceIdent, $auditData->newValue);

        if ($oldEntity !== null) {
            $newValue = $newEntity !== null ? (string)$newEntity->getTitle() : (string)$auditData->newValue;

            return $this->renderTemplate($this->changedTemplate, [
                'fieldName' => $fieldCaption,
                'oldValue' => (string)$oldEntity->getTitle(),
                'newValue' => $newValue
            ]);
        } else {
            $newValue = $newEntity !== null ? (string)$newEntity->getTitle() : (string)$auditData->newValue;

            return $this->renderTemplate($this->setTemplate, [
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
        $auditData = $audit->get('data');
        $fieldCaption = $entity->getField($audit->get('ident'))->getCaption();

        $oldValueTitle = $values[$auditData->oldValue] ?? '';
        $newValueTitle = $values[$auditData->newValue] ?? '';

        if ($auditData->oldValue) {
            return $this->renderTemplate($this->changedTemplate, [
                'fieldName' => $fieldCaption,
                'oldValue' => (string)$oldValueTitle,
                'newValue' => (string)$newValueTitle
            ]);
        } else {
            return $this->renderTemplate($this->setTemplate, [
                'fieldName' => $fieldCaption,
                'newValue' => (string)$newValueTitle
            ]);
        }
    }
}