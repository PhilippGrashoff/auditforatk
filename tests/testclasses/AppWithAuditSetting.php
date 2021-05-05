<?php

declare(strict_types=1);

namespace auditforatk\tests\testclasses;

use Atk4\Ui\App;


class AppWithAuditSetting extends App {
    public $always_run = false;
    public $createAudit = true;
    public $auth;
}