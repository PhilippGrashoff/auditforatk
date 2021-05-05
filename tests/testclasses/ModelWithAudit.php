<?php

declare(strict_types=1);

namespace auditforatk\tests\testclasses;

use Atk4\Data\Model;
use Atk4\Ui\Dropdown;
use auditforatk\ModelWithAuditTrait;

class ModelWithAudit extends Model
{

    use ModelWithAuditTrait;

    public $table = 'model_with_audit';

    protected function init(): void
    {
        parent::init();

        $this->addAuditRefAndAuditHooks();

        $this->addField('name');
        $this->addField('other_field');
        $this->addField('time', ['type' => 'time']);
        $this->addField('date', ['type' => 'date']);
        $this->addField('datetime', ['type' => 'datetime']);
        $this->addField(
            'values',
            [
                'caption' => 'ValuesTest',
                'values' => [0 => 'SomeValue', 1 => 'SomeOtherValue']
            ]
        );
        $this->addField(
            'dropdown',
            [
                'caption' => 'DropDownTest',
                'ui' => [
                    'form' => [
                        Dropdown::class,
                        'values' => [0 => 'SomeValue', 1 => 'SomeOtherValue'],
                        'empty' => ''
                    ]
                ]
            ]
        );

        $this->hasOne('user_id', ['model' => [User::class], 'caption' => 'Benutzer']);
    }

    public function setSkipFields(array $fields): void
    {
        $this->skipFieldsFromAudit = $fields;
    }
}