<?php

declare(strict_types=1);

namespace auditforatk;

use atk4\data\Reference;
use atk4\data\Reference\HasOne;
use DateTime;
use PMRAtk\Data\Audit;
use PMRAtk\Data\BaseModel;
use secondarymodelforatk\SecondaryModel;
use ReflectionClass;
use atk4\data\Model;


trait AuditTrait
{

    /**
     * use in Model::init()
     */
    protected function addAuditRefAndAuditHooks(): Reference
    {
        $ref = $this->hasMany(
            'Audit',
            [
                function () {
                    return (new Audit($this->persistence, ['parentObject' => $this]))->addCondition(
                        'model_class',
                        get_class($this)
                    );
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
    public function getAuditViewModel()
    {
        return $this->ref('Audit');
    }

    /**
     * Save any change in ModelFields to Audit
     */
    public function createAudit(string $type)
    {
        //add possibility to skip auditing App-wide, e.g. to speed up tests
        if (isset($this->app->createAudit)
            && !$this->app->createAudit
        ) {
            return;
        }

        $data = [];
        //TODO: This will fail with atk 2.3 where dirty_after_save behaviour changed
        foreach ($this->dirty as $field_name => $dirty_field) {
            //only audit non system fields and fields that go to persistence
            if (!$this->hasField($field_name)
                || $this->getField($field_name)->system
                || $this->getField($field_name)->never_persist) {
                continue;
            }
            //check if any "real" value change happened
            if ($dirty_field === $this->get($field_name)) {
                continue;
            }
            //strings special treatment
            //money due to GermanMoneyFormatFieldTrait
            if (
                in_array($this->getField($field_name)->type, ['string', 'text', 'money'])
                && $dirty_field == $this->get($field_name)
            ) {
                continue;
            }

            //time fields
            if ($this->getField($field_name)->type === 'time') {
                $data[$field_name] = $this->_timeFieldAudit($field_name, $dirty_field);
            } //date fields
            elseif ($this->getField($field_name)->type === 'date') {
                $data[$field_name] = $this->_dateFieldAudit($field_name, $dirty_field);
            } //hasOne relationship
            elseif (
                $this->hasRef($field_name)
                && $this->getRef($field_name) instanceof HasOne
            ) {
                $old = $this->ref($field_name)->newInstance();
                $old->tryLoad($dirty_field);
                $new = $this->ref($field_name)->newInstance();
                $new->tryLoad($this->get($field_name));
                $data[$field_name] = $this->_hasOneAudit($field_name, $dirty_field, $new, $old);
            } //dropdowns
            elseif (
                isset($this->getField($field_name)->ui['form'])
                && in_array('DropDown', $this->getField($field_name)->ui['form'])
            ) {
                $data[$field_name] = $this->_dropDownAudit($field_name, $dirty_field);
            } //any other field
            else {
                $data[$field_name] = $this->_normalFieldAudit($field_name, $dirty_field);
            }
        }
        if ($type == 'CREATE' || $data) {
            $audit = new Audit($this->persistence, ['parentObject' => $this]);
            $audit->reload_after_save = false;
            $audit->set('value', $type);
            $audit->set('data', $data);
            $audit->save();
        }
    }

    /**
     * save delete to Audit
     */
    public function createDeleteAudit()
    {
        if (!$this->app->createAudit) {
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
    ) {
        if (!$this->app->createAudit) {
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
            'old_value' => ($dirty_field instanceof DateTime) ? date_format($dirty_field, 'd.m.Y') : '',
            'new_value' => ($this->get($field_name) instanceof DateTime) ? date_format(
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
            'old_value' => ($dirty_field instanceof DateTime) ? date_format($dirty_field, 'H:i') : '',
            'new_value' => ($this->get($field_name) instanceof DateTime) ?
                date_format($this->get($field_name), 'H:i') : '',
        ];
    }


    /**
     * used to create a array containing the audit data for a one to many relation field
     */
    private function _hasOneAudit($field_name, $dirty_field, $o_new, $o_old): array
    {
        //both objects loaded, means field had a value before and now
        if ($o_new->loaded() && $o_old->loaded()) {
            return [
                'field_name' => $this->getField($field_name)->getCaption(),
                'old_value' => $o_old->get('name'),
                'new_value' => $o_new->get('name'),
            ];
        } //only new object loaded, means field didnt have a value before
        elseif ($o_new->loaded()) {
            return [
                'field_name' => $this->getField($field_name)->getCaption(),
                'old_value' => $dirty_field,
                'new_value' => $o_new->get('name'),
            ];
        } else {
            return [
                'field_name' => $this->getField($field_name)->getCaption(),
                'old_value' => $dirty_field,
                'new_value' => $this->get($field_name),
            ];
        }
    }


    /**
     *
     */
    private function _dropDownAudit(string $field_name, $dirty_field): array
    {
        $old_value = $new_value = '...';
        if (isset($this->getField($field_name)->values[$dirty_field])) {
            $old_value = $this->getField($field_name)->values[$dirty_field];
        } elseif (isset($this->getField($field_name)->ui['form']['values'][$dirty_field])) {
            $old_value = $this->getField($field_name)->ui['form']['values'][$dirty_field];
        } elseif (isset($this->getField($field_name)->ui['form']['empty'])) {
            $old_value = $this->getField($field_name)->ui['form']['empty'];
        }

        if (isset($this->getField($field_name)->values[$this->get($field_name)])) {
            $new_value = $this->getField($field_name)->values[$this->get($field_name)];
        } elseif (isset($this->getField($field_name)->ui['form']['values'][$this->get($field_name)])) {
            $new_value = $this->getField($field_name)->ui['form']['values'][$this->get($field_name)];
        } elseif (isset($this->getField($field_name)->ui['form']['empty'])) {
            $new_value = $this->getField($field_name)->ui['form']['empty'];
        }
        return [
            'field_name' => $this->getField($field_name)->getCaption(),
            'old_value' => $old_value,
            'new_value' => $new_value,
        ];
    }
}