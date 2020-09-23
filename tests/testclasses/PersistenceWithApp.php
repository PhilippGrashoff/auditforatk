<?php

declare(strict_types=1);

namespace auditforatk\tests\testclasses;


use atk4\core\AppScopeTrait;
use atk4\data\Persistence;

class PersistenceWithApp extends Persistence {
    use AppScopeTrait;
}