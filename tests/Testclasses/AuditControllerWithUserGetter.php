<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests\Testclasses;

use Atk4\Data\Model;
use Atk4\Data\Persistence;
use PhilippR\Atk4\Audit\AuditController;

class AuditControllerWithUserGetter extends AuditController
{
    protected function getUser(Persistence $persistence): ?Model
    {
        return (new User($persistence))->createEntity()
            ->set('name', 'Test User')
            ->set('id', 333);
    }
}