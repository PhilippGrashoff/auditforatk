<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests\Testclasses;

use Atk4\Ui\App;

class AppWithAuth extends App
{

    public \stdClass $auth;

    protected bool $alwaysRun = false;

    public function __construct()
    {
        $this->auth = new \stdClass();
    }

}