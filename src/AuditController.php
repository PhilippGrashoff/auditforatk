<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit;

use Atk4\Core\DiContainerTrait;
use Atk4\Core\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use stdClass;

class AuditController
{
    use DiContainerTrait;

    public string $messageRendererClass = MessageRenderer::class;

    public string $skipFieldsControllerClass = SkipFieldsController::class;

    protected MessageRenderer $messageRenderer;
    protected SkipFieldsController $skipFieldsController;

    /**
     * @param array<string,mixed> $defaults
     */
    public function __construct(array $defaults = [])
    {
        $this->setDefaults($defaults);
        $this->messageRenderer = new $this->messageRendererClass();
        $this->skipFieldsController = new $this->skipFieldsControllerClass();
    }

    /**
     * @param Model $entity
     * @return void
     * @throws Exception
     * @throws \Atk4\Data\Exception|\Throwable
     */
    public function addCreatedAudit(Model $entity): void
    {
        if ($this->noAudit()) {
            return;
        }

        $entity->assertIsEntity();
        $audit = $this->getAuditForEntity($entity);
        $audit->set('type', Audit::TYPE_CREATED);
        $audit->set('rendered_message', $this->messageRenderer->renderCreatedMessage($audit, $entity));
        $audit->save();
    }

    /**
     * @param Model $entity
     * @return void
     * @throws Exception
     * @throws \Atk4\Data\Exception|\Throwable
     */
    public function addDeletedAudit(Model $entity): void
    {
        if ($this->noAudit()) {
            return;
        }

        $audit = $this->getAuditForEntity($entity);
        $audit->set('type', Audit::TYPE_DELETED);
        $audit->set('rendered_message', $this->messageRenderer->renderDeletedMessage($audit, $entity));
        $audit->save();
    }

    /**
     *  Save any change in Model Fields to Audit
     *
     * @param Model<Model> $entity
     * @return void
     * @throws Exception
     * @throws \Atk4\Data\Exception|\Throwable
     */
    public function addFieldsChangedAudit(Model $entity): void
    {
        if ($this->noAudit()) {
            return;
        }
        foreach ($entity->dirtyBeforeSave as $fieldName => $dirtyValue) {
            //check if any "real" value change happened
            if ($dirtyValue === $entity->get($fieldName)) {
                continue;
            }
            if ($this->skipFieldsController->skipFieldFromAudit($entity, $fieldName)) {
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
     * @throws \Atk4\Data\Exception|\Throwable
     */
    protected function addFieldChangedAudit(Model $entity, string $fieldName, mixed $dirtyValue): void
    {
        $audit = $this->getAuditForEntity($entity);
        $audit->set('type', Audit::TYPE_FIELD);
        $audit->set('ident', $fieldName);
        $data = new stdClass();
        $data->fieldType = $entity->getField($fieldName)->type;
        $data->oldValue = $dirtyValue;
        $data->newValue = $entity->get($fieldName);
        if ($data->oldValue instanceof \DateTimeInterface) {
            $data->oldValue = $data->oldValue->format(DATE_ATOM);
        }
        if ($data->newValue instanceof \DateTimeInterface) {
            $data->newValue = $data->newValue->format(DATE_ATOM);
        }
        $audit->setData($data);
        $audit->set('rendered_message', $this->messageRenderer->renderFieldAudit($audit, $entity));
        $audit->save();
    }

    /**
     * @return bool
     */
    protected function noAudit(): bool
    {
        //add the possibility to skip auditing in ENV, e.g., to speed up tests
        if (isset($_ENV['noAudit']) && $_ENV['noAudit']) {
            return true;
        }

        return false;
    }

    /**
     * @param Model $entity
     * @return Audit
     * @throws Exception
     * @throws \Atk4\Data\Exception|\Throwable
     */
    protected function getAuditForEntity(Model $entity): Audit
    {
        $entity->assertIsEntity();
        $persistence = $entity->getModel()->getPersistence();
        $audit = (new Audit($persistence))->createEntity();
        $audit->setParentEntity($entity);
        if ($user = $this->getUser($persistence)) {
            $audit->set('user_name', $user->getTitle());
            $audit->set('user_id', $user->getId());
        }

        return $audit;
    }

    /**
     * Implement in child implementation to your needs
     * @return Model|null
     */
    protected function getUser(Persistence $persistence): ?Model
    {
        return null;
    }
}
