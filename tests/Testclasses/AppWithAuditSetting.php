<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests\Testclasses;

use Atk4\Ui\App;


class AppWithAuditSetting extends App
{
    public $always_run = false;
    public $createAudit = true;
    public $auth;
}