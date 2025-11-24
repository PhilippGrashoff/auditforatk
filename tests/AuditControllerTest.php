<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests;

use Atk4\Data\Persistence\Sql;
use Atk4\Data\Schema\TestCase;
use DateTime;
use PhilippR\Atk4\Audit\Audit;
use PhilippR\Atk4\Audit\Tests\Testclasses\ModelWithAudit;
use PhilippR\Atk4\Audit\Tests\Testclasses\User;


class AuditControllerTest extends TestCase
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
            Audit::TYPE_CREATED,
            $entity->ref(Audit::class)->loadAny()->get('type'),
        );

        $clonedEntity = clone $entity;
        $entity->delete();
        self::assertEquals(
            2,
            (new Audit($this->db))->action('count')->getOne()
        );
        self::assertSame(
            Audit::TYPE_DELETED,
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
        $data = $audit->getData();
        self::assertSame(
            $now->format('His'),
            (new DateTime($audit->getData()->newValue))->format('His')
        );

        sleep(1);
        $newNow = new DateTime();
        $entity->set('time', $newNow);
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        self::assertSame(
            $now->format('His'),
            (new DateTime($audit->getData()->oldValue))->format('His')
        );
        self::assertSame(
            $newNow->format('His'),
            (new DateTime($audit->getData()->newValue))->format('His')
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
            (new DateTime($audit->getData()->newValue))->format(DATE_ATOM)
        );

        sleep(1);
        $newNow = new DateTime();
        $entity->set('datetime', $newNow);
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        self::assertSame(
            $now->format(DATE_ATOM),
            (new DateTime($audit->getData()->oldValue))->format(DATE_ATOM)
        );
        self::assertSame(
            $newNow->format(DATE_ATOM),
            (new DateTime($audit->getData()->newValue))->format(DATE_ATOM)
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
            (new DateTime($audit->getData()->newValue))->format('Ymd')
        );

        sleep(1);
        $yesterday = (new DateTime())->modify('-1 Day');
        $entity->set('date', $yesterday);
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        self::assertSame(
            $now->format('Ymd'),
            (new DateTime($audit->getData()->oldValue))->format('Ymd')
        );
        self::assertSame(
            $yesterday->format('Ymd'),
            (new DateTime($audit->getData()->newValue))->format('Ymd')
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
        $expected->fieldType = 'bigint';
        $expected->oldValue = null;
        $expected->newValue = $user1->getId();
        self::assertSame(
            json_encode($expected),
            json_encode($audit->getData())
        );


        $entity->set('user_id', $user2->getId());
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        $expected->oldValue = $user1->getId();
        $expected->newValue = $user2->getId();
        self::assertSame(
            json_encode($expected),
            json_encode($audit->getData())
        );

        $entity->set('user_id', null);
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        $expected->oldValue = $user2->getId();
        $expected->newValue = null;
        self::assertEquals(
            json_encode($expected),
            json_encode($audit->getData())
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
            json_encode($audit->getData())
        );

        $entity->set('values_integer_key', 1);
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        $expected->oldValue = 0;
        $expected->newValue = 1;
        self::assertSame(
            json_encode($expected),
            json_encode($audit->getData())
        );

        $entity->set('values_integer_key', null);
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        $expected->oldValue = 1;
        $expected->newValue = null;
        self::assertSame(
            json_encode($expected),
            json_encode($audit->getData())
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
            json_encode($audit->getData())
        );

        $entity->set('values_string_key', 'second');
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        $expected->oldValue = 'first';
        $expected->newValue = 'second';
        self::assertSame(
            json_encode($expected),
            json_encode($audit->getData())
        );

        $entity->set('values_string_key', null);
        $entity->save();
        $audit = $entity->ref(Audit::class)->loadAny();
        $expected->oldValue = 'second';
        $expected->newValue = null;
        self::assertSame(
            json_encode($expected),
            json_encode($audit->getData())
        );
    }

    public function testEmptyStringsVsNullNoAudit(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('string', null);
        $entity->save();
        self::assertEquals(
            1, // Only created audit
            (new Audit($this->db))->action('count')->getOne()
        );

        // Setting empty string should not create additional audit due to loose comparison
        $entity->set('string', '');
        $entity->save();
        self::assertEquals(
            1, // Still only created audit
            (new Audit($this->db))->action('count')->getOne()
        );

        // Setting back to null should not create additional audit due to loose comparison
        $entity->set('string', null);
        $entity->save();
        self::assertEquals(
            1, // Still only created audit
            (new Audit($this->db))->action('count')->getOne()
        );
    }

    public function testTextFieldEmptyVsNullNoAudit(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('text', null);
        $entity->save();
        self::assertEquals(
            1, // Only created audit
            (new Audit($this->db))->action('count')->getOne()
        );

        // Setting empty string should not create additional audit due to loose comparison
        $entity->set('text', '');
        $entity->save();
        self::assertEquals(
            1, // Still only created audit
            (new Audit($this->db))->action('count')->getOne()
        );

        // Setting back to null should not create additional audit due to loose comparison
        $entity->set('text', null);
        $entity->save();
        self::assertEquals(
            1, // Still only created audit
            (new Audit($this->db))->action('count')->getOne()
        );
    }

    public function testStringFieldActualChangeCreatesAudit(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->save();
        $entity->set('string', 'initial value');
        $entity->save();
        self::assertEquals(
            2, // Created audit + field change audit
            (new Audit($this->db))->action('count')->getOne()
        );

        // Actual change should create audit even for string fields
        $entity->set('string', 'changed value');
        $entity->save();
        self::assertEquals(
            3, // Created audit + 2 field change audits
            (new Audit($this->db))->action('count')->getOne()
        );
    }

    public function testTextFieldActualChangeCreatesAudit(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->save();
        $entity->set('text', 'initial text');
        $entity->save();
        self::assertEquals(
            2, // Created audit + field change audit
            (new Audit($this->db))->action('count')->getOne()
        );

        // Actual change should create audit even for text fields
        $entity->set('text', 'changed text');
        $entity->save();
        self::assertEquals(
            3, // Created audit + 2 field change audits
            (new Audit($this->db))->action('count')->getOne()
        );
    }

    public function testNonStringFieldStrictComparison(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('integer', 0);
        $entity->save();
        self::assertEquals(
            2, // Created audit + field change audit
            (new Audit($this->db))->action('count')->getOne()
        );

        // For non-string fields, strict comparison should apply
        // This tests that the string/text special case doesn't affect other field types
        $entity->set('integer', 0); // Same value
        $entity->save();
        self::assertEquals(
            2, // No additional audit for same value
            (new Audit($this->db))->action('count')->getOne()
        );
    }
}
