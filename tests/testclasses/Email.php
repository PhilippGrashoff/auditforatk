<?php

declare(strict_types=1);

namespace auditforatk\tests\testclasses;

use Atk4\Data\Model;
use secondarymodelforatk\SecondaryModel;

class Email extends SecondaryModel {

    public $table = 'email';

    protected function init(): void
    {
        parent::init();
        $this->addField('name');
    }
}
