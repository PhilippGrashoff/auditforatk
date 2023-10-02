<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests;

use Atk4\Data\Persistence\Sql;
use Atk4\Data\Schema\TestCase;
use DateTime;
use PhilippR\Atk4\Audit\Audit;
use PhilippR\Atk4\Audit\Tests\Testclasses\ModelWithAudit;
use PhilippR\Atk4\Audit\Tests\Testclasses\User;


class FieldsAuditTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new Sql('sqlite::memory:');
        $this->createMigrator(new Audit($this->db))->create();
        $this->createMigrator(new ModelWithAudit($this->db))->create();
        $this->createMigrator(new User($this->db))->create();
    }

    public function testCreatedAndDeletedAuditIsAdded(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->save();
        self::assertEquals(
            1,
            (new Audit($this->db))->action('count')->getOne()
        );
        self::assertSame(
            'CREATED',
            $entity->ref(Audit::class)->loadAny()->get('type'),
        );

        $clonedEntity = clone $entity;
        $entity->delete();
        self::assertEquals(
            2,
            (new Audit($this->db))->action('count')->getOne()
        );
        self::assertSame(
            'DELETED',
            $clonedEntity->ref(Audit::class)->loadAny()->get('type'),
        );
    }

    public function testNoAuditCreatedOnNoChange(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $now = new DateTime();
        //should create audits
        $entity->set('string', 'Lala');
        $entity->set('text', 'Lala');
        $entity->set('integer', 11);
        $entity->set('float', 11.111);
        $entity->set('json', ["la" => "la", "da" => "da"]);
        $entity->set('time', $now);
        $entity->set('date', $now);
        $entity->set('datetime', $now);
        $entity->set('values_integer_key', 1);
        $entity->save();

        self::assertEquals(
            10,
            $entity->ref(Audit::class)->action('count')->getOne()
        );

        //Same Values everywhere, should not create any additional audit
        $entity->set('string', 'Lala');
        $entity->set('text', 'Lala');
        $entity->set('integer', 11);
        $entity->set('float', 11.111);
        $entity->set('json', ["la" => "la", "da" => "da"]);
        $entity->set('time', $now);
        $entity->set('date', $now);
        $entity->set('datetime', $now);
        $entity->set('values_integer_key', 1);
        $entity->save();

        self::assertEquals(
            10,
            $entity->ref(Audit::class)->action('count')->getOne()
        );
    }

    /*
    public function testEmptyStringsVsNullNoAudit(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('string', null);
        $entity->save();
        self::assertEquals(
            1,
            (new Audit($this->db))->action('count')->getOne()
        );

        $entity->set('string', '');
        $entity->save();
        self::assertEquals(
            1,
            (new Audit($this->db))->action('count')->getOne()
        );

        $entity->set('string', null);
        $entity->save();
        self::assertEquals(
            1,
            (new Audit($this->db))->action('count')->getOne()
        );
    }
    */

    public function testAuditRemainsAfterDeletingModel(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('string', 'Lala');
        $entity->set('text', 'Gaga');
        $entity->save();

        self::assertEquals(
            3,
            $entity->ref(Audit::class)->action('count')->getOne()
        );

        $clonedEntity = clone $entity;
        $entity->delete();

        self::assertEquals(
            4,
            $clonedEntity->ref(Audit::class)->action('count')->getOne()
        );
    }

    public function testTimeFieldAudit(): void
    {
        $now = new DateTime();
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('time', $now);
        $entity->save();

        $audit = $entity->ref(Audit::class)->loadAny();
        self::assertSame(
            $now->format('Hisv'),
            $audit->get('data')->newValue->format('Hisv')
        );

        sleep(1);
        $newNow = new DateTime();
        $entity->set('time', $newNow);
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        self::assertSame(
            $now->format('Hisv'),
            $audit->get('data')->oldValue->format('Hisv')
        );
        self::assertSame(
            $newNow->format('Hisv'),
            $audit->get('data')->newValue->format('Hisv')
        );
    }

    public function testDateTimeFieldAudit(): void
    {
        $now = new DateTime();
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('datetime', $now);
        $entity->save();

        $audit = $entity->ref(Audit::class)->loadAny();
        self::assertSame(
            $now->format(DATE_ATOM),
            $audit->get('data')->newValue->format(DATE_ATOM)
        );

        sleep(1);
        $newNow = new DateTime();
        $entity->set('datetime', $newNow);
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        self::assertSame(
            $now->format(DATE_ATOM),
            $audit->get('data')->oldValue->format(DATE_ATOM)
        );
        self::assertSame(
            $newNow->format(DATE_ATOM),
            $audit->get('data')->newValue->format(DATE_ATOM)
        );
    }

    public function testDateFieldAudit(): void
    {
        $now = new DateTime();
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('date', $now);
        $entity->save();

        $audit = $entity->ref(Audit::class)->loadAny();
        self::assertSame(
            $now->format('Ymd'),
            $audit->get('data')->newValue->format('Ymd')
        );

        sleep(1);
        $yesterday = (new DateTime())->modify('-1 Day');
        $entity->set('date', $yesterday);
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        self::assertSame(
            $now->format('Ymd'),
            $audit->get('data')->oldValue->format('Ymd')
        );
        self::assertSame(
            $yesterday->format('Ymd'),
            $audit->get('data')->newValue->format('Ymd')
        );
    }

    public function testHasOneAudit(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();

        $user1 = (new User($this->db))->createEntity();
        $user1->set('name', 'Hans');
        $user1->save();

        $user2 = (new User($this->db))->createEntity();
        $user2->set('name', 'Peter');
        $user2->save();

        $entity->set('user_id', $user1->getId());
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        $expected = new \stdClass();
        $expected->fieldType = 'integer';
        $expected->oldValue = null;
        $expected->newValue = $user1->getId();
        self::assertSame(
            json_encode($expected),
            json_encode($audit->get('data'))
        );


        $entity->set('user_id', $user2->getId());
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        $expected->oldValue = $user1->getId();
        $expected->newValue = $user2->getId();
        self::assertSame(
            json_encode($expected),
            json_encode($audit->get('data'))
        );

        $entity->set('user_id', null);
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        $expected->oldValue = $user2->getId();
        $expected->newValue = null;
        self::assertEquals(
            json_encode($expected),
            json_encode($audit->get('data'))
        );
    }

    public function testValuesFieldWithIntegerKeyAudit(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('values_integer_key', 0);
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        $expected = new \stdClass();
        $expected->fieldType = 'integer';
        $expected->oldValue = null;
        $expected->newValue = 0;
        self::assertSame(
            json_encode($expected),
            json_encode($audit->get('data'))
        );

        $entity->set('values_integer_key', 1);
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        $expected->oldValue = 0;
        $expected->newValue = 1;
        self::assertSame(
            json_encode($expected),
            json_encode($audit->get('data'))
        );

        $entity->set('values_integer_key', null);
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        $expected->oldValue = 1;
        $expected->newValue = null;
        self::assertSame(
            json_encode($expected),
            json_encode($audit->get('data'))
        );
    }

    public function testValuesFieldWithStringKeyAudit(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('values_string_key', 'first');
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        $expected = new \stdClass();
        $expected->fieldType = 'string';
        $expected->oldValue = null;
        $expected->newValue = 'first';
        self::assertSame(
            json_encode($expected),
            json_encode($audit->get('data'))
        );

        $entity->set('values_string_key', 'second');
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        $expected->oldValue = 'first';
        $expected->newValue = 'second';
        self::assertSame(
            json_encode($expected),
            json_encode($audit->get('data'))
        );

        $entity->set('values_string_key', null);
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        $expected->oldValue = 'second';
        $expected->newValue = null;
        self::assertSame(
            json_encode($expected),
            json_encode($audit->get('data'))
        );
    }
}
