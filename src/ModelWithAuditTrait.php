<?php

declare(strict_types=1);

namespace auditforatk;

use Atk4\Data\Model;
use Atk4\Data\Reference;
use Atk4\Data\Reference\HasOne;
use ReflectionClass;
use secondarymodelforatk\SecondaryModel;

/**
 * @extends Model<Model>
 */
trait ModelWithAuditTrait
{

    protected array $dirtyBeforeSave = [];

    protected array $skipFieldsFromAudit = [];

    protected AuditRendererInterface $auditRenderer;

    //possibility to disable Audit, e.g. if a base class has audit but some extended class shouldn't
    protected bool $noAudit = false;


    /**
     * use in Model::init() to quickly set up reference and hooks
     */
    protected function addAuditRefAndAuditHooks(): Reference
    {
        $ref = $this->hasMany(
            Audit::class,
            [
                'model' => function () {
                    return (new Audit(
                        $this->getPersistence(),
                        ['parentObject' => $this, 'auditRenderer' => $this->auditRenderer]
                    ))
                        ->addCondition('model_class', get_class($this));
                },
                'theirField' => 'model_id'
            ]
        );

        //after save, create Audit
        $this->onHook(
            Model::HOOK_AFTER_SAVE,
            function (self $model, bool $isUpdate) {
                $model->createAudit($isUpdate ? 'CHANGE' : 'CREATE');
            }
        );

        //after delete, create Audit
        $this->onHook(
            Model::HOOK_AFTER_DELETE,
            function (self $model) {
                $model->createDeleteAudit();
            }
        );

        $this->onHook(
            Model::HOOK_BEFORE_SAVE,
            function (self $model) {
                $model->dirtyBeforeSave = $model->dirty;
            },
            [],
            999
        );
        return $ref;
    }


    /**
     *  Save any change in Model Fields to Audit
     *
     * @param string $type
     * @return void
     * @throws \Atk4\Core\Exception
     * @throws \Atk4\Data\Exception
     */
    public function createAudit(string $type): void
    {
        if (!$this->_checkSkipAudit()) {
            return;
        }

        if ($type == 'CREATE') {
            $audit = new Audit($this->persistence, ['parentObject' => $this, 'auditRenderer' => $this->auditRenderer]);
            $audit->set('value', $type);
            $audit->save();
            $type = 'CHANGE';
        }

        $data = [];
        foreach ($this->dirtyBeforeSave as $fieldName => $dirtyValue) {
            $this->_addFieldToAudit($data, $fieldName, $dirtyValue);
        }

        if ($type == 'CREATE' || $data) {
            $audit = new Audit($this->persistence, ['parentObject' => $this, 'auditRenderer' => $this->auditRenderer]);
            $audit->set('value', $type);
            $audit->set('data', $data);
            $audit->save();
        }
    }

    /**
     * @param array $data
     * @param string $fieldName
     * @param $dirtyValue
     * @return void
     */
    protected function _addFieldToAudit(array &$data, string $fieldName, $dirtyValue): void
    {
        //only audit non system fields and fields that go to persistence
        if (
            in_array($fieldName, $this->skipFieldsFromAudit)
            || !$this->hasField($fieldName)
            || $fieldName === $this->id_field
            || $this->getField($fieldName)->never_persist
        ) {
            return;
        }
        //check if any "real" value change happened
        if ($dirtyValue === $this->get($fieldName)) {
            return;
        }
        //strings special treatment; money due to GermanMoneyFormatFieldTrait
        if (
            in_array($this->getField($fieldName)->type, [null, 'string', 'text', 'money'])
            && $dirtyValue == $this->get($fieldName)
        ) {
            return;
        }

        //time fields
        if ($this->getField($fieldName)->type === 'time') {
            $data[$fieldName] = $this->_timeFieldAudit($fieldName, $dirtyValue);
        } //date fields
        elseif ($this->getField($fieldName)->type === 'date') {
            $data[$fieldName] = $this->_dateFieldAudit($fieldName, $dirtyValue);
        } //datetime fields
        elseif ($this->getField($fieldName)->type === 'datetime') {
            $data[$fieldName] = $this->_dateTimeFieldAudit($fieldName, $dirtyValue);
        } //hasOne relationship
        elseif (
            $this->hasRef($fieldName)
            && $this->getRef($fieldName) instanceof HasOne
        ) {
            $data[$fieldName] = $this->_hasOneAudit($fieldName, $dirtyValue);
        } //dropdowns
        elseif (
            (
                is_array($this->getField($fieldName)->values)
                && count($this->getField($fieldName)->values) > 0
            )
            || (
                isset($this->getField($fieldName)->ui['form'])
                && in_array(Dropdown::class, $this->getField($fieldName)->ui['form'])
            )
        ) {
            $data[$fieldName] = $this->_dropDownAudit($fieldName, $dirtyValue);
        } //any other field
        else {
            $data[$fieldName] = $this->_normalFieldAudit($fieldName, $dirtyValue);
        }
    }

    /**
     * @return void
     */
    public function createDeleteAudit(): void
    {
        if (!$this->_checkSkipAudit()) {
            return;
        }
        $audit = new Audit($this->persistence, ['parentObject' => $this, 'auditRenderer' => $this->auditRenderer]);
        $audit->set('value', 'DELETE');
        $audit->save();
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
        if (!$this->_checkSkipAudit()) {
            return;
        }
        $audit = new Audit($this->persistence, ['parentObject' => $this, 'auditRenderer' => $this->auditRenderer]);
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
                'old_value' => ($model->dirty[$field] ?? ''),
                'new_value' => $model->get($field)
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
        if (!$this->_checkSkipAudit()) {
            return;
        }

        $audit = new Audit($this->persistence, ['parentObject' => $this, 'auditRenderer' => $this->auditRenderer]);
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
     * Adds an additional audit entry which is not related to one of the model's fields
     */
    public function addAdditionalAudit(string $type, array $data)
    {
        if (!$this->_checkSkipAudit()) {
            return;
        }
        $audit = new Audit($this->persistence, ['parentObject' => $this, 'auditRenderer' => $this->auditRenderer]);
        $audit->set('value', $type);
        $audit->set('data', $data);
        $audit->save();
    }

    /**
     *  used to create a array containing the audit data for a normal field
     */
    private function _normalFieldAudit(string $fieldName, $dirtyValue): array
    {
        return [
            'field_name' => $this->getField($fieldName)->getCaption(),
            'old_value' => $dirtyValue,
            'new_value' => $this->get($fieldName),
        ];
    }


    /**
     * TODO: It seems more sensible to store serialized DateTime Object!
     */
    private function _dateFieldAudit(string $fieldName, $dirtyValue): array
    {
        return [
            'field_name' => $this->getField($fieldName)->getCaption(),
            'old_value' => ($dirtyValue instanceof \DateTime) ? date_format($dirtyValue, 'd.m.Y') : '',
            'new_value' => ($this->get($fieldName) instanceof \DateTime) ? date_format(
                $this->get($fieldName),
                'd.m.Y'
            ) : '',
        ];
    }

    private function _dateTimeFieldAudit(string $fieldName, $dirtyValue): array
    {
        return [
            'field_name' => $this->getField($fieldName)->getCaption(),
            'old_value' => ($dirtyValue instanceof \DateTime) ? date_format($dirtyValue, 'd.m.Y H:i') : '',
            'new_value' => ($this->get($fieldName) instanceof \DateTime) ? date_format(
                $this->get($fieldName),
                'd.m.Y H:i'
            ) : '',
        ];
    }

    private function _timeFieldAudit(string $fieldName, $dirtyValue): array
    {
        return [
            'field_name' => $this->getField($fieldName)->getCaption(),
            'old_value' => ($dirtyValue instanceof \DateTime) ? date_format($dirtyValue, 'H:i') : '',
            'new_value' => ($this->get($fieldName) instanceof \DateTime) ?
                date_format($this->get($fieldName), 'H:i') : '',
        ];
    }

    /**
     * used to create a array containing the audit data for a one to many relation field
     */
    private function _hasOneAudit(string $fieldName, $dirtyValue): array
    {
        $old = $this->ref($fieldName)->newInstance();
        $old->tryLoad($dirtyValue);
        $new = $this->ref($fieldName)->newInstance();
        $new->tryLoad($this->get($fieldName));

        $newValue = $new->loaded() ? $new->get($new->title_field) : $this->get($fieldName);
        $oldValue = $old->loaded() ? $old->get($new->title_field) : $dirtyValue;

        return [
            'field_name' => $this->getField($fieldName)->getCaption(),
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ];
    }

    private function _dropDownAudit(string $fieldName, $dirtyValue): array
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
            'field_name' => $this->getField($fieldName)->getCaption(),
            'old_value' => $old_value,
            'new_value' => $new_value,
        ];
    }

    protected function _checkSkipAudit(): bool
    {
        //add possibility to skip auditing App-wide, e.g. to speed up tests
        if (
            isset($this->persistence->app->createAudit)
            && !$this->persistence->app->createAudit
        ) {
            return false;
        }

        if($this->noAudit) {
            return false;
        }

        return true;
    }
}