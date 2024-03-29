<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests;

use Atk4\Data\Persistence;
use Atk4\Data\Schema\TestCase;
use PhilippR\Atk4\Audit\Audit;
use PhilippR\Atk4\Audit\Tests\Testclasses\AppWithAuth;
use PhilippR\Atk4\Audit\Tests\Testclasses\ModelWithAudit;
use PhilippR\Atk4\Audit\Tests\Testclasses\PersistenceWithApp;
use PhilippR\Atk4\Audit\Tests\Testclasses\User;


class LoggedInUserInAuditTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new PersistenceWithApp('sqlite::memory:');
        $this->createMigrator(new Audit($this->db))->create();
        $this->createMigrator(new ModelWithAudit($this->db))->create();
        $this->createMigrator(new User($this->db))->create();
    }

    public function testLoggedInUserIsSaved(): void
    {
        $persistence = $this->getPersistenceWithAppAndAuth();
        $entity = (new ModelWithAudit($persistence))->createEntity();
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        self::assertSame(
            "CREATED",
            $audit->get('type')
        );
        self::assertSame(
            $persistence->getApp()->auth->user->getId(),
            $audit->get('user_id')
        );
        self::assertSame(
            $persistence->getApp()->auth->user->get('name'),
            $audit->get('user_name')
        );
    }

    protected function getPersistenceWithAppAndAuth(): Persistence
    {
        $this->db->setApp(new AppWithAuth());
        $this->db->getApp()->auth->user = (new User($this->db))->createEntity();
        $this->db->getApp()->auth->user->set('name', 'SOME NAME');
        $this->db->getApp()->auth->user->save();

        return $this->db;
    }
}
