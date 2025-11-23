<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests\Testclasses;

use Atk4\Data\Model;
use PhilippR\Atk4\Audit\AuditTrait;

class ModelWithAudit extends Model
{

    use AuditTrait;

    public $table = 'model_with_audit';

    public $caption = 'Model with audit';

    protected function init(): void
    {
        parent::init();

        $this->addAuditRefAndAuditHooks();

        $this->addField('string', ['type' => 'string', 'caption' => 'SomeCaption']);
        $this->addField('text', ['type' => 'text']);
        $this->addField('integer', ['type' => 'integer']);
        $this->addField('float', ['type' => 'float']);
        $this->addField('json', ['type' => 'json']);
        $this->addField('time', ['type' => 'time']);
        $this->addField('date', ['type' => 'date']);
        $this->addField('datetime', ['type' => 'datetime']);
        $this->addField('never_persist', ['type' => 'string', 'neverPersist' => true]);
        $this->addField(
            'values_integer_key',
            [
                'type' => 'integer',
                'caption' => 'ValuesTest',
                'values' => [0 => 'SomeValue', 1 => 'SomeOtherValue']
            ]
        );
        $this->addField(
            'values_string_key',
            [
                'type' => 'string',
                'caption' => 'ValuesTest',
                'values' => ['first' => 'SomeValue', 'second' => 'SomeOtherValue']
            ]
        );

        $this->hasOne('user_id', ['model' => [User::class], 'caption' => 'Benutzer']);
    }

    public function setSkipFields(array $fields): void
    {
        $this->noAuditFields = $fields;
    }
}