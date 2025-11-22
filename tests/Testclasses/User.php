<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests\Testclasses;

use Atk4\Data\Model;

class User extends Model
{

    public $table = 'user';

    public $caption = 'User';

    protected function init(): void
    {
        parent::init();
        $this->addField('name');
    }
}
