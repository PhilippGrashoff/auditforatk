<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Audit;

use Atk4\Data\Model;
use Atk4\Data\Reference;

/**
 * @extends Model<Model>
 */
trait AuditTrait
{

    /**
     * @var class-string<AuditController>
     */
    protected string $auditControllerClass = AuditController::class;

    /**
     * @var array<string, mixed>
     * in here, the fields which were dirty before save are stored to create the audit after save.
     */
    public array $dirtyBeforeSave = [];

    /**
     * @var array<int, string>
     * A list of field names that should be excluded from audit
     */
    protected array $noAuditFields = [];

    /**
     * add this method to Model::init() to quickly set up reference and hooks
     * @return Reference
     */
    protected function addAuditRefAndAuditHooks(): Reference
    {
        $ref = $this->hasMany(
            Audit::class,
            [
                'model' => function () {
                    return (new Audit($this->getPersistence()))
                        ->addCondition('model_class', get_class($this));
                },
                'theirField' => 'model_id'
            ]
        );

        //save which fields were dirty before save to have them available after save when audit is created
        $this->onHook(
            Model::HOOK_BEFORE_SAVE,
            function (self $entity) {
                $entity->dirtyBeforeSave = array_replace([], $entity->getDirtyRef());
            },
            [],
            999
        );

        //after each save, create Audit
        $this->onHook(
            Model::HOOK_AFTER_SAVE,
            function (self $entity, bool $isUpdate) {
                if (!$isUpdate) {
                    (new $this->auditControllerClass())->addCreatedAudit($entity);
                }
                (new $this->auditControllerClass())->addFieldsChangedAudit($entity);
            }
        );

        //after delete, create Audit
        $this->onHook(
            Model::HOOK_AFTER_DELETE,
            function (self $entity) {
                (new $this->auditControllerClass())->addDeletedAudit($entity);
            }
        );

        return $ref;
    }

    protected function addNoAuditFields(array $fieldnames): void {
        $this->noAuditFields = array_merge($this->noAuditFields, $fieldnames);
    }

    public function getNoAuditFields(): array {
        return $this->noAuditFields;
    }
}