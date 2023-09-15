<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests;

use Atk4\Data\Persistence\Sql;
use Atk4\Data\Schema\TestCase;
use PhilippR\Atk4\Audit\Audit;
use PhilippR\Atk4\Audit\Tests\Testclasses\AuditRendererDemo;
use PhilippR\Atk4\Audit\Tests\Testclasses\User;


class SkipFieldsAndNoAuditTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new Sql('sqlite::memory:');
        $this->createMigrator(new Audit($this->db))->create();
        $this->createMigrator(new User($this->db))->create();
    }

    public function testUserInfoOnSave()
    {
        $audit = new Audit($this->getPersistence());
        $audit->save();
        self::assertEquals(
            $audit->get('created_by_name'),
            'SOME NAME'
        );
    }

    public function testAuditMessageRendererIsUsed()
    {
        $audit = new Audit($this->getPersistence(), ['auditRenderer' => new AuditRendererDemo()]);
        $audit->save();
        self::assertEquals(
            $audit->get('rendered_output'),
            'Demo'
        );
    }
}
