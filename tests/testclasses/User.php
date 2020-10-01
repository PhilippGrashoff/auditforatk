<?php

declare(strict_types=1);

namespace auditforatk\tests\testclasses;

use atk4\data\Model;

class User extends Model {

    public $table = 'user';

    protected function init(): void
    {
        parent::init();
        $this->addField('name');
    }
}
