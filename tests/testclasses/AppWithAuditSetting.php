<?php

declare(strict_types=1);

namespace auditforatk\tests\testclasses;

use atk4\ui\App;


class AppWithAuditSetting extends App {
    public $always_run = false;
    public $createAudit = true;
    public $auth;
}