<?php

declare(strict_types=1);

namespace auditforatk;

use atk4\data\Reference;
use atk4\data\Reference\HasOne;
use atk4\ui\Dropdown;
use secondarymodelforatk\SecondaryModel;
use ReflectionClass;
use atk4\data\Model;


trait ModelWithAuditTrait
{

    /**
     * use in Model::init()
     */
    protected function addAuditRefAndAuditHooks(): Reference
    {
        $ref = $this->hasMany(
            Audit::class,
            [
                function () {
                    return (new Audit($this->persistence, ['parentObject' => $this]))
                        ->addCondition('model_class', get_class($this));
                },
                'their_field' => 'model_id'
            ]
        );

        //after save, create Audit
        $this->onHook(
            Model::HOOK_AFTER_SAVE,
            function ($model, $isUpdate) {
                $model->createAudit($isUpdate ? 'CHANGE' : 'CREATE');
            }
        );

        //after delete, create Audit
        $this->onHook(
            Model::HOOK_AFTER_DELETE,
            function ($model) {
                $model->createDeleteAudit();
            }
        );

        return $ref;
    }

    /**
     * usually returns $this->ref('Audit'). May be overwritten by descendants
     * to add a more complex Audit model (e.g. Coupons and AccountingItems)
     * TODO: Rename, name is misleading
     */
    public function getAuditViewModel(): Audit
    {
        return $this->ref('Audit');
    }

    /**
     * Save any change in Model Fields to Audit
     */
    public function createAudit(string $type): void
    {
        if (!$this->_checkSkipAudit()) {
            return;
        }

        $data = [];
        //TODO: This will fail with atk 2.3 where dirty_after_save behaviour changed
        foreach ($this->dirty as $fieldName => $dirtyValue) {
            $this->_addFieldToAudit($data, $fieldName, $dirtyValue);
        }

        if ($type == 'CREATE' || $data) {
            $audit = new Audit($this->persistence, ['parentObject' => $this]);
            $audit->reload_after_save = false;
            $audit->set('value', $type);
            $audit->set('data', $data);
            $audit->save();
        }
    }

    protected function _addFieldToAudit(array &$data, string $fieldName, $dirtyValue): void
    {
        //only audit non system fields and fields that go to persistence
        if (
            !$this->hasField($fieldName)
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
            in_array($this->getField($fieldName)->type, ['string', 'text', 'money'])
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
                && count($this->getField($fieldName)) > 0
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

    public function createDeleteAudit(): void
    {
        if (!$this->_checkSkipAudit()) {
            return;
        }
        $audit = new Audit($this->persistence, ['parentObject' => $this]);
        $audit->reload_after_save = false;
        $audit->set('value', 'DELETE');
        $audit->save();
    }

    /**
     * creates an Audit for secondary models like emails, if it was added, changed or removed
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

        $audit = new Audit($this->persistence, ['parentObject' => $this]);
        $audit->reload_after_save = false;
        $audit->set('value', $type . '_' . strtoupper((new ReflectionClass($model))->getShortName()));
        if ($modelClass && $modelId) {
            $audit->set('model_class', $modelClass);
            $audit->set('model_id', $modelId);
        }

        $data = [];
        //only save if some value is there or some change happened
        if ($model->get($field) || isset($model->dirty[$field])) {
            $data = [
                'old_value' => (isset($model->dirty[$field]) ? $model->dirty[$field] : ''),
                'new_value' => $model->get($field)
            ];
        }
        if ($data) {
            $audit->set('data', $data);
            $audit->save();
        }
    }

    /**
     * creates an Audit for adding/removing MToM Relations
     */
    public function addMToMAudit(string $type, BaseModel $model, $nameField = 'name')
    {
        if (!$this->app->createAudit) {
            return;
        }

        $audit = new Audit($this->persistence, ['parentObject' => $this]);
        $audit->reload_after_save = false;
        $audit->set('value', $type . '_' . strtoupper((new ReflectionClass($model))->getShortName()));

        $data = ['id' => $model->get('id'), 'name' => $model->get($nameField), 'model' => get_class($model)];

        $audit->set('data', $data);
        $audit->save();
    }

    /**
     * Adds an additional audit entry which is not related to one of the model's fields
     */
    public function addAdditionalAudit(string $type, array $data)
    {
        if (!$this->app->createAudit) {
            return;
        }
        $audit = new Audit($this->persistence, ['parentObject' => $this]);
        $audit->reload_after_save = false;
        $audit->set('value', $type);
        $audit->set('data', $data);
        $audit->save();
    }

    /**
     *  used to create a array containing the audit data for a normal field
     */
    private function _normalFieldAudit($field_name, $dirty_field): array
    {
        return [
            'field_name' => $this->getField($field_name)->getCaption(),
            'old_value' => $dirty_field,
            'new_value' => $this->get($field_name),
        ];
    }


    /**
     *  used to create a array containing the audit data for a date field
     */
    private function _dateFieldAudit($field_name, $dirty_field): array
    {
        return [
            'field_name' => $this->getField($field_name)->getCaption(),
            'old_value' => ($dirty_field instanceof \DateTime) ? date_format($dirty_field, 'd.m.Y') : '',
            'new_value' => ($this->get($field_name) instanceof \DateTime) ? date_format(
                $this->get($field_name),
                'd.m.Y'
            ) : '',
        ];
    }


    /**
     *  used to create a array containing the audit data for a time field
     */
    private function _timeFieldAudit($field_name, $dirty_field): array
    {
        return [
            'field_name' => $this->getField($field_name)->getCaption(),
            'old_value' => ($dirty_field instanceof \DateTime) ? date_format($dirty_field, 'H:i') : '',
            'new_value' => ($this->get($field_name) instanceof \DateTime) ?
                date_format($this->get($field_name), 'H:i') : '',
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

        //both objects loaded, means field had a value before and now
        if ($old->loaded() && $new->loaded()) {
            return [
                'field_name' => $this->getField($fieldName)->getCaption(),
                'old_value' => $old->get('name'),
                'new_value' => $new->get('name'),
            ];
        } //only new object loaded, means field didnt have a value before
        elseif ($new->loaded()) {
            return [
                'field_name' => $this->getField($fieldName)->getCaption(),
                'old_value' => $dirtyValue,
                'new_value' => $new->get('name'),
            ];
        } else {
            return [
                'field_name' => $this->getField($fieldName)->getCaption(),
                'old_value' => $dirtyValue,
                'new_value' => $this->get($fieldName),
            ];
        }
    }


    /**
     *
     */
    private function _dropDownAudit(string $fieldName, $dirtyValue): array
    {
        $old_value = $new_value = '...';
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
            isset($this->app->createAudit)
            && !$this->app->createAudit
        ) {
            return false;
        }

        return true;
    }
}