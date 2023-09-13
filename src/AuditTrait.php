<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Audit;

use Atk4\Core\Exception;
use Atk4\Data\Model;
use Atk4\Data\Reference;
use Atk4\Data\Reference\HasOne;
use PhilippR\Atk4\SecondaryModel\SecondaryModel;
use ReflectionClass;

/**
 * @extends Model<Model>
 */
trait AuditTrait
{
    /**
     * @var bool
     * possibility to completely disable Audit, e.g. if a base class has audit but some extended class shouldn't.
     */
    protected bool $noAudit = false;

    /**
     * @var array<string, mixed>
     * in here, the fields which were dirty before save are stored to create the audit after save.
     */
    protected array $dirtyBeforeSave = [];

    /**
     * @var array<int, string>
     * A list of field names that should be excluded from audit
     */
    protected array $skipFieldsFromAudit = [];

    /**
     * @var AuditRendererInterface Used to create a human-readable string for each audit. Implement your own to create
     * strings in your desired format.
     */
    protected AuditRendererInterface $auditRenderer;

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
                    return (new Audit($this->getPersistence(), ['auditRenderer' => $this->auditRenderer]))
                        ->addCondition('model_class', get_class($this));
                },
                'theirField' => 'model_id'
            ]
        );

        //save which fields were dirty before save to have them available after save when audit is created
        //todo: check if his is still needed in atk4/data v4
        $this->onHook(
            Model::HOOK_BEFORE_SAVE,
            function (self $entity) {
                $entity->dirtyBeforeSave = clone $entity->getDirtyRef();
            },
            [],
            999
        );

        //after each save, create Audit
        $this->onHook(
            Model::HOOK_AFTER_SAVE,
            function (self $entity, bool $isUpdate) {
                if (!$isUpdate) {
                    $this->addCreateAudit();
                }
                $entity->addFieldValuesAudit();
            }
        );

        //after delete, create Audit
        $this->onHook(
            Model::HOOK_AFTER_DELETE,
            function (self $entity) {
                $entity->addDeleteAudit();
            }
        );

        return $ref;
    }

    /**
     *  Save any change in Model Fields to Audit
     *
     * @return void
     * @throws Exception
     * @throws \Atk4\Data\Exception
     */
    public function addCreateAudit(): void
    {
        if ($this->noAudit()) {
            return;
        }

        $audit = new Audit($this->getPersistence(), ['auditRenderer' => $this->auditRenderer]);
        $audit->set('type', 'CREATE');
        $audit->save();
    }

    /**
     * @return void
     * @throws Exception
     * @throws \Atk4\Data\Exception
     */
    public function addDeleteAudit(): void
    {
        if (!$this->noAudit()) {
            return;
        }
        
        $audit = new Audit($this->getPersistence(), ['auditRenderer' => $this->auditRenderer]);
        $audit->set('type', 'DELETE');
        $audit->save();
    }

    /**
     *  Save any change in Model Fields to Audit
     *
     * @return void
     * @throws Exception
     * @throws \Atk4\Data\Exception
     */
    public function addFieldValuesAudit(): void
    {
        if ($this->noAudit()) {
            return;
        }
        foreach ($this->dirtyBeforeSave as $fieldName => $dirtyValue) {
            //only audit non system fields and fields that go to persistence
            if (
                in_array($fieldName, $this->skipFieldsFromAudit)
                || !$this->hasField($fieldName)
                || $fieldName === $this->idField
                || $this->getField($fieldName)->neverPersist
            ) {
                continue;
            }
            //check if any "real" value change happened
            if ($dirtyValue === $this->get($fieldName)) {
                continue;
            }
            $this->addFieldAudit($fieldName, $dirtyValue);
        }
    }

    /**
     * @param string $fieldName
     * @param $dirtyValue
     * @return void
     * @throws Exception
     * @throws \Atk4\Data\Exception
     */
    protected function addFieldAudit(string $fieldName, $dirtyValue): void
    {


        $audit = new Audit($this->getPersistence(), ['auditRenderer' => $this->auditRenderer]);
        $audit->set('type', 'FIELD');
        /*
        //strings special treatment; money due to GermanMoneyFormatFieldTrait
        if (
            in_array($this->getField($fieldName)->type, [null, 'string', 'text', 'money'])
            && $dirtyValue == $this->get($fieldName)
        ) {
            return;
        }*/

        //time fields
        if ($this->getField($fieldName)->type === 'time') {
            $audit->set('data', $this->_timeFieldAudit($fieldName, $dirtyValue));
        } //date fields
        elseif ($this->getField($fieldName)->type === 'date') {
            $audit->set('data', $this->_dateFieldAudit($fieldName, $dirtyValue));
        } //datetime fields
        elseif ($this->getField($fieldName)->type === 'datetime') {
            $audit->set('data', $this->_dateTimeFieldAudit($fieldName, $dirtyValue));
        } //hasOne relationship
        elseif (
            $this->hasReference($fieldName)
            && $this->getReference($fieldName) instanceof HasOne
        ) {
            $audit->set('data', $this->_hasOneAudit($fieldName, $dirtyValue));
        } //fields with key-value lists
        elseif (
            is_array($this->getField($fieldName)->values)
            && count($this->getField($fieldName)->values) > 0
        ) {
            $audit->set('data', $this->_dropDownAudit($fieldName, $dirtyValue));
        } //any other field
        else {
            $audit->set('data', $this->_normalFieldAudit($fieldName, $dirtyValue));
        }

        $audit->save();
    }

    /**
     *  used to create a array containing the audit data for a normal field
     */
    protected function _normalFieldAudit(string $fieldName, $dirtyValue): array
    {
        return [
            'fieldName' => $this->getField($fieldName)->getCaption(),
            'oldValue' => $dirtyValue,
            'newValue' => $this->get($fieldName),
        ];
    }


    /**
     * TODO: It seems more sensible to store serialized DateTime Object!
     */
    protected function _dateFieldAudit(string $fieldName, $dirtyValue): array
    {
        return [
            'fieldName' => $this->getField($fieldName)->getCaption(),
            'oldValue' => ($dirtyValue instanceof \DateTime) ? date_format($dirtyValue, 'd.m.Y') : '',
            'newValue' => ($this->get($fieldName) instanceof \DateTime) ? date_format(
                $this->get($fieldName),
                'd.m.Y'
            ) : '',
        ];
    }

    protected function _dateTimeFieldAudit(string $fieldName, $dirtyValue): array
    {
        return [
            'fieldName' => $this->getField($fieldName)->getCaption(),
            'oldValue' => ($dirtyValue instanceof \DateTime) ? date_format($dirtyValue, 'd.m.Y H:i') : '',
            'newValue' => ($this->get($fieldName) instanceof \DateTime) ? date_format(
                $this->get($fieldName),
                'd.m.Y H:i'
            ) : '',
        ];
    }

    protected function _timeFieldAudit(string $fieldName, $dirtyValue): array
    {
        return [
            'fieldName' => $this->getField($fieldName)->getCaption(),
            'oldValue' => ($dirtyValue instanceof \DateTime) ? date_format($dirtyValue, 'H:i') : '',
            'newValue' => ($this->get($fieldName) instanceof \DateTime) ?
                date_format($this->get($fieldName), 'H:i') : '',
        ];
    }

    /**
     * used to create an array containing the audit data for a one-to-many relation field
     */
    protected function _hasOneAudit(string $fieldName, $dirtyValue): array
    {
        $old = $this->ref($fieldName)->newInstance();
        $old->tryLoad($dirtyValue);
        $new = $this->ref($fieldName)->newInstance();
        $new->tryLoad($this->get($fieldName));

        $newValue = $new->loaded() ? $new->get($new->title_field) : $this->get($fieldName);
        $oldValue = $old->loaded() ? $old->get($new->title_field) : $dirtyValue;

        return [
            'fieldName' => $this->getField($fieldName)->getCaption(),
            'oldValue' => $oldValue,
            'newValue' => $newValue,
        ];
    }

    protected function _dropDownAudit(string $fieldName, $dirtyValue): array
    {
        $old_value = $new_value = '';
        if (isset($this->getField($fieldName)->values[$dirtyValue])) {
            $old_value = $this->getField($fieldName)->values[$dirtyValue];
        } elseif (isset($this->getField($fieldName)->ui['form']['values'][$dirtyValue])) {
            $old_value = $this->getField($fieldName)->ui['form']['values'][$dirtyValue];
        } elseif (isset($this->getField($fieldName)->ui['form']['empty'])) {
            $old_value = $this->getField($fieldName)->ui['form']['empty'];
        }

        if (isset($this->getField($fieldName)->values[$this->get($fieldName)])) {
            $new_value = $this->getField($fieldName)->values[$this->get($fieldName)];
        } elseif (isset($this->getField($fieldName)->ui['form']['values'][$this->get($fieldName)])) {
            $new_value = $this->getField($fieldName)->ui['form']['values'][$this->get($fieldName)];
        } elseif (isset($this->getField($fieldName)->ui['form']['empty'])) {
            $new_value = $this->getField($fieldName)->ui['form']['empty'];
        }

        return [
            'fieldName' => $this->getField($fieldName)->getCaption(),
            'oldValue' => $old_value,
            'newValue' => $new_value,
        ];
    }

    /**
     * creates an Audit for secondary models like emails, if it was added, changed or removed
     *
     * @param string $type
     * @param SecondaryModel $model
     * @param string $field
     * @param string|null $modelClass
     * @param $modelId
     * @return void
     */
    public function addSecondaryAudit(
        string $type,
        SecondaryModel $model,
        string $field = 'value',
        string $modelClass = null,
        $modelId = null
    ): void {
        if (!$this->noAudit()) {
            return;
        }
        $audit = new Audit($this->getPersistence(), ['parentObject' => $this, 'auditRenderer' => $this->auditRenderer]);
        //TODO: Why shortname? store full, easier, cant lead to unintended errors
        //In general, ADD_SECONDARY and storing the class name in data array is more sensible!
        $audit->set('value', $type . '_' . strtoupper((new ReflectionClass($model))->getShortName()));
        if ($modelClass && $modelId) {
            $audit->set('model_class', $modelClass);
            $audit->set('model_id', $modelId);
        }

        $data = [];
        //only save if some value is there or some change happened
        if ($model->get($field) || isset($model->dirty[$field])) {
            $data = [
                'oldValue' => ($model->dirty[$field] ?? ''),
                'newValue' => $model->get($field)
            ];
        }
        if ($data) {
            $audit->set('data', $data);
            $audit->save();
        }
    }

    /**
     * @param string $type
     * @param Model $model
     * @param string $fieldName
     * @return void
     */
    public function addMToMAudit(string $type, Model $model, string $fieldName = 'name'): void
    {
        if (!$this->noAudit()) {
            return;
        }

        $audit = new Audit($this->getPersistence(), ['parentObject' => $this, 'auditRenderer' => $this->auditRenderer]);
        //TODO: Why shortname? store full, easier, cant lead to unintended errors
        //In general, ADD_MTOM and storing the class name in data array is more sensible!
        $audit->set('value', $type . '_' . strtoupper((new ReflectionClass($model))->getShortName()));

        $data = [
            'id' => $model->get($model->id_field),
            'name' => $model->get($fieldName),
            'model' => get_class($model)
        ];

        $audit->set('data', $data);
        $audit->save();
    }

    /**
     * @param string $type
     * @param array $data
     * @return void
     * Adds an audit entry which is not related to one of the model's fields
     */
    public function addAdditionalAudit(string $type, array $data): void
    {
        if (!$this->noAudit()) {
            return;
        }
        $audit = new Audit($this->getPersistence(), ['parentObject' => $this, 'auditRenderer' => $this->auditRenderer]);
        $audit->set('type', $type);
        $audit->set('data', $data);
        $audit->save();
    }


    protected function noAudit(): bool
    {
        //add possibility to skip auditing in ENV, e.g. to speed up tests
        if (isset($_ENV['noAudit']) && $_ENV['noAudit']) {
            return false;
        }

        if ($this->noAudit) {
            return false;
        }

        return true;
    }
}