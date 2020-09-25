<?php

declare(strict_types=1);

namespace auditforatk\tests\testclasses;

use atk4\data\Model;
use atk4\ui\Dropdown;
use atkmodelwithapp\AtkModelWithAppTrait;
use auditforatk\ModelWithAuditTrait;

class ModelWithAudit extends Model
{

    use ModelWithAuditTrait;
    use AtkModelWithAppTrait;

    public $table = 'model_with_audit';

    public function init(): void
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
                'ui' => ['form' => [Dropdown::class, 'values' => [0 => 'SomeValue', 1 => 'SomeOtherValue'], 'empty' => '']]
            ]
        );

        $this->hasOne('user_id', [User::class, 'caption' => 'Benutzer']);
    }
}