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
     * @param Model $entity
     * @return void
     * @throws Exception
     * @throws \Atk4\Data\Exception
     */
    public function addCreatedAudit(Model $entity): void
    {
        if ($this->noAudit()) {
            return;
        }

        $entity->assertIsEntity();
        $audit = $this->getAuditForEntity($entity);
        $audit->set('type', 'CREATED');
        $audit->set('rendered_message', $this->messageRenderer->renderCreatedMessage($audit, $entity));
        $audit->save();
    }

    /**
     * @param Model $entity
     * @return void
     * @throws Exception
     * @throws \Atk4\Data\Exception
     */
    public function addDeletedAudit(Model $entity): void
    {
        if ($this->noAudit()) {
            return;
        }

        $audit = $this->getAuditForEntity($entity);
        $audit->set('type', 'DELETED');
        $audit->set('rendered_message', $this->messageRenderer->renderDeletedMessage($audit, $entity));
        $audit->save();
    }

    /**
     *  Save any change in Model Fields to Audit
     *
     * @param Model<Model> $entity
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
            $field = $entity->getField($fieldName);
            //only audit non system fields and fields that go to persistence
            if (
                in_array($fieldName, $entity->skipFieldsFromAudit)
                || !$entity->hasField($fieldName)
                || $fieldName === $entity->idField
                || $field->neverPersist
            ) {
                continue;
            }
            //check if any "real" value change happened
            if ($dirtyValue === $entity->get($fieldName)) {
                continue;
            }
            /*
            //string types do not get any additional audit value regarding null vs empty string.
            // Hence, strings are loosely compared using == only
            if($field->type == 'string' || $field->type == 'text') {
                if ($dirtyValue == $entity->get($fieldName)) {
                    continue;
                }
            }
            */
            $this->addFieldChangedAudit($entity, $fieldName, $dirtyValue);
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
    protected function addFieldChangedAudit(Model $entity, string $fieldName, mixed $dirtyValue): void
    {
        $audit = $this->getAuditForEntity($entity);
        $this->setFieldDataToAudit($audit, $entity, $fieldName, $dirtyValue, 'FIELD');
        $audit->set('rendered_message', $this->messageRenderer->renderFieldAudit($audit, $entity));
        $audit->save();
    }

    protected function setFieldDataToAudit(
        Audit $audit,
        Model $entity,
        string $fieldName,
        mixed $dirtyValue,
        string $type
    ): void {
        $audit->set('type', $type);
        $audit->set('ident', $fieldName);
        $data = new \stdClass();
        $data->fieldType = $entity->getField($fieldName)->type;
        $data->oldValue = $dirtyValue;
        $data->newValue = $entity->get($fieldName);
        $audit->set('data', $data);
    }

    protected function noAudit(): bool
    {
        //add possibility to skip auditing in ENV, e.g. to speed up tests
        if (isset($_ENV['noAudit']) && $_ENV['noAudit']) {
            return true;
        }

        return false;
    }

    protected function getAuditForEntity(Model $entity): Audit
    {
        $audit = (new Audit($entity->getPersistence()))->createEntity();
        $entity->assertIsEntity();
        $audit->setParentEntity($entity);
        if (
            method_exists($entity->getPersistence(), 'getApp')
            && property_exists($entity->getPersistence()->getApp(), 'auth')
            && $entity->getPersistence()->getApp()->auth->user->isLoaded()
        ) {
            $audit->set('user_name', $entity->getPersistence()->getApp()->auth->user->getTitle());
            $audit->set('user_id', $entity->getPersistence()->getApp()->auth->user->getId());
        }

        return $audit;
    }
}
