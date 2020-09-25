<?php

declare(strict_types=1);

namespace auditforatk\tests\testclasses;

use atk4\data\Model;
use secondarymodelforatk\SecondaryModel;

class Email extends SecondaryModel {

    public $table = 'email';

    public function init(): void
    {
        parent::init();
        $this->addField('name');
    }
}
