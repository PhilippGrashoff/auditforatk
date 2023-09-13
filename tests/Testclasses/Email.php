<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests\Testclasses;


use PhilippR\Atk4\SecondaryModel\SecondaryModel;

class Email extends SecondaryModel
{

    public $table = 'email';

    protected function init(): void
    {
        parent::init();
        $this->addField('name');
    }
}
