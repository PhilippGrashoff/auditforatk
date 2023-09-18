<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests;

use Atk4\Data\Persistence\Sql;
use Atk4\Data\Schema\TestCase;
use PhilippR\Atk4\Audit\Audit;
use PhilippR\Atk4\Audit\Tests\Testclasses\ModelWithAudit;

class SkipFieldsAndNoAuditTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new Sql('sqlite::memory:');
        $this->createMigrator(new Audit($this->db))->create();
        $this->createMigrator(new ModelWithAudit($this->db))->create();
    }

    public function testIdFieldIsIgnored(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->save();
        self::assertSame(
            1,
            (int)$entity->ref(Audit::class)->action('count')->getOne()
        );
        self::assertSame(
            "CREATED",
            $entity->ref(Audit::class)->loadAny()->get('type')
        );
    }

    public function testNeverPersistFieldsAreNotAudited(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->save();
        self::assertSame(
            1,
            (int)$entity->ref(Audit::class)->action('count')->getOne()
        );

        $entity->set('never_persist', 'Some');
        $entity->save();
        $entity->set('never_persist', 'Another');
        $entity->save();

        self::assertSame(
            1,
            (int)$entity->ref(Audit::class)->action('count')->getOne()
        );
    }

    public function testSkippedFieldsAreNotAudited(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('text', 'bla');
        $entity->save();
        self::assertSame(
            2,
            (int)$entity->ref(Audit::class)->action('count')->getOne()
        );

        //now disable audit for that field
        $entity->setSkipFields(['text']);
        $entity->set('text', 'du');
        $entity->save();
        self::assertSame(
            2,
            (int)$entity->ref(Audit::class)->action('count')->getOne()
        );
        //re-enable, audit should be created
        $entity->setSkipFields(['string']);
        $entity->set('text', 'kra');
        $entity->save();
        self::assertSame(
            3,
            (int)$entity->ref(Audit::class)->action('count')->getOne()
        );
    }
}
