<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests;

use Atk4\Data\Persistence\Sql;
use Atk4\Data\Schema\TestCase;
use PhilippR\Atk4\Audit\Audit;
use PhilippR\Atk4\Audit\Tests\Testclasses\AuditControllerWithUserGetter;
use PhilippR\Atk4\Audit\Tests\Testclasses\ModelWithAudit;


class LoggedInUserInAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new Sql('sqlite::memory:');
        $this->createMigrator(new ModelWithAudit($this->db))->create();
        $this->createMigrator(new Audit($this->db))->create();
    }

    public function testLoggedInUserIsSaved(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity()->save();
        $audit = $entity->ref(Audit::class)->loadAny()->delete();
        $auditController = new AuditControllerWithUserGetter();
        $auditController->addCreatedAudit($entity);
        $audit = $entity->ref(Audit::class)->loadAny();
        self::assertSame(
            "CREATED",
            $audit->get('type')
        );
        self::assertSame(
            333,
            $audit->get('user_id')
        );
        self::assertSame(
            'Test User',
            $audit->get('user_name')
        );
    }
}
