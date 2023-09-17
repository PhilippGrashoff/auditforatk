<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests\Testclasses;

use Atk4\Core\AppScopeTrait;
use Atk4\Data\Persistence\Sql;

class PersistenceWithApp extends Sql
{
    use AppScopeTrait;
}