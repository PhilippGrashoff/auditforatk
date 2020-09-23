<?php

declare(strict_types=1);

namespace auditforatk\tests\testclasses;

use atk4\data\Model;

class ModelWithAudit extends Model {

    public $table = 'user';

    public function init(): void
    {
        parent::init();
        $this->addField('name');
        $this->addField('otherField');
    }
}