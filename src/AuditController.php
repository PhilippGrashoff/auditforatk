<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit;

use Atk4\Core\DiContainerTrait;
use Atk4\Core\Exception;
use Atk4\Data\Model;
use Atk4\Data\Reference\HasOne;

class AuditController
{
    use DiContainerTrait;

    public string $messageRendererClass = MessageRenderer::class;

    protected MessageRenderer $messageRenderer;

    public function __construct(array $defaults = [])
    {
        $this->setDefaults($defaults);
        $this->messageRenderer = new $this->messageRendererClass();
    }

    /**
     *  Save any change in Model Fields to Audit
     *
     * @return void
     * @throws Exception
     * @throws \Atk4\Data\Exception
     */
    public function addCreatedAudit(Model $entity): void
    {
        if ($this->noAudit()) {
            return;
        }
        $audit = $this->getAuditForEntity($entity);
        $audit->set('type', 'CREATED');
        $audit->set('rendered_message', $this->messageRenderer->renderCreatedMessage($audit, $entity));
        $audit->save();
    }

    /**
     * @return void
     * @throws Exception
     * @throws \Atk4\Data\Exception
     */
    public function addDeletedAudit(Model $entity): void
    {
        if (!$this->noAudit()) {
            return;
        }

        $audit = $this->getAuditForEntity($entity);
        $audit->set('type', 'DELETED');
        $audit->set('rendered_message', $this->messageRenderer->renderDeletedMessage($audit));
        $audit->save();
    }

    /**
     *  Save any change in Model Fields to Audit
     *
     * @param Model $entity
     * @return void
     * @throws Exception
     * @throws \Atk4\Data\Exception
     */
    public function addFieldsChangedAudit(Model $entity): void
    {
        if ($this->noAudit()) {
            return;
        }
        foreach ($entity->dirtyBeforeSave as $fieldName => $dirtyValue) {
            //only audit non system fields and fields that go to persistence
            if (
                in_array($fieldName, $entity->skipFieldsFromAudit)
                || !$entity->hasField($fieldName)
                || $fieldName === $entity->idField
                || $entity->getField($fieldName)->neverPersist
            ) {
                continue;
            }
            //check if any "real" value change happened
            if ($dirtyValue === $entity->get($fieldName)) {
                continue;
            }
            $entity->addFieldAudit($entity, $fieldName, $dirtyValue);
        }
    }

    /**
     * @param Model $entity
     * @param string $fieldName
     * @param mixed $dirtyValue
     * @return void
     * @throws Exception
     * @throws \Atk4\Data\Exception
     */
    protected function addFieldAudit(Model $entity, string $fieldName, mixed $dirtyValue): void
    {
        //hasOne references
        if (
            $entity->hasReference($fieldName)
            && $entity->getReference($fieldName) instanceof HasOne
        ) {
            $this->_hasOneAudit($entity, $fieldName, $dirtyValue);
        } //fields with key-value lists
        elseif (
            is_array($entity->getField($fieldName)->values)
            && count($entity->getField($fieldName)->values) > 0
        ) {
            $this->_keyValueAudit($entity, $fieldName, $dirtyValue);
        } //any other field
        else {
            $this->_normalFieldAudit($entity, $fieldName, $dirtyValue);
        }
    }

    /**
     *  used to create an array containing the audit data for a normal field
     */
    protected function _normalFieldAudit(Model $entity, string $fieldName, mixed $dirtyValue): Audit
    {
        $audit = $this->getAuditForEntity($entity);
        $audit->set('type', 'FIELD');
        $audit->set('data', [
            'fieldName' => $entity->getField($fieldName)->getCaption(),
            'oldValue' => $dirtyValue,
            'newValue' => $entity->get($fieldName),
        ]);
        $audit->set('rendered_message', $this->messageRenderer->renderFieldAudit($audit));
        $audit->save();

        return $audit;
    }

    /**
     * used to create an array containing the audit data for a one-to-many relation field
     */
    protected function _hasOneAudit(Model $entity, string $fieldName, $dirtyValue): Audit
    {
        $audit = $this->getAuditForEntity($entity);
        $audit->set('type', 'FIELD_HASONE');

        $oldValue = null;
        if ($dirtyValue) {
            $oldEntity = $entity->refModel($fieldName);
            $oldEntity->onlyFields = [$oldEntity->idField, $oldEntity->titleField];
            $oldEntity->load($dirtyValue);
            $oldValue = [$oldEntity->getId() => $oldEntity->getTitle()];
        }

        $newEntity = $entity->refModel($fieldName);
        $newEntity->onlyFields = [$newEntity->idField, $newEntity->titleField];
        $newEntity->load($entity->get($fieldName));

        $audit->set(
            'data',
            [
                'fieldName' => $entity->getField($fieldName)->getCaption(),
                'oldValue' => $oldValue,
                'newValue' => [$newEntity->getId() => $newEntity->getTitle()]
            ]
        );

        $audit->set('rendered_message', $this->messageRenderer->renderHasOneAudit($audit));
        $audit->save();

        return $audit;
    }

    protected function _keyValueAudit(Model $entity, string $fieldName, $dirtyValue): Audit
    {
        $audit = $this->getAuditForEntity($entity);
        $audit->set('type', 'FIELD');

        $oldValue = $newValue = '';
        if (isset($entity->getField($fieldName)->values[$dirtyValue])) {
            $oldValue = $entity->getField($fieldName)->values[$dirtyValue];
        }

        if (isset($entity->getField($fieldName)->values[$entity->get($fieldName)])) {
            $newValue = $entity->getField($fieldName)->values[$entity->get($fieldName)];
        }

        $audit->set(
            'data',
            [
                'fieldName' => $entity->getField($fieldName)->getCaption(),
                'oldValue' => $oldValue,
                'newValue' => $newValue,
            ]
        );

        $audit->set('rendered_message', $this->messageRenderer->renderFieldAudit($audit));
        $audit->save();

        return $audit;
    }

    protected function noAudit(): bool
    {
        //add possibility to skip auditing in ENV, e.g. to speed up tests
        if (isset($_ENV['noAudit']) && $_ENV['noAudit']) {
            return false;
        }

        return true;
    }

    // add Name of currently logged-in user to "created_by_name" field
    protected function getAuditForEntity(Model $entity): Audit
    {
        $audit = new Audit($entity->getPersistence());
        $audit->setParentEntity($entity);
        if (
            isset($entity->persistence->app->auth->user)
            && $entity->persistence->app->auth->user->loaded()
        ) {
            $audit->set('created_by_name', $entity->persistence->app->auth->user->get('name'));
        }

        return $audit;
    }
}
