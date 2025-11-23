<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests;

use Atk4\Data\Persistence\Sql;
use Atk4\Data\Schema\TestCase;
use PhilippR\Atk4\Audit\Audit;
use PhilippR\Atk4\Audit\Tests\Testclasses\ModelWithAudit;

class NoAuditEnvironmentTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new Sql('sqlite::memory:');
        $this->createMigrator(new Audit($this->db))->create();
        $this->createMigrator(new ModelWithAudit($this->db))->create();
    }

    public function testNoAuditInEnv(): void
    {
        $_ENV['noAudit'] = true;
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('text', 'bla');
        $entity->save();
        $clonedEntity = clone $entity;
        $entity->delete();
        self::assertSame(
            0,
            (int)$clonedEntity->ref(Audit::class)->action('count')->getOne()
        );
        $_ENV['noAudit'] = false;
    }
}
