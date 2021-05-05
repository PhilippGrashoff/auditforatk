<?php

declare(strict_types=1);

namespace auditforatk\tests\testclasses;

use Atk4\Data\Model;

class User extends Model {

    public $table = 'user';

    protected function init(): void
    {
        parent::init();
        $this->addField('name');
    }
}
