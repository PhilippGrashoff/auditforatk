<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests;

use _PHPStan_95cdbe577\Nette\Utils\DateTime;
use Atk4\Data\Persistence\Sql;
use Atk4\Data\Schema\TestCase;
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

    public function testFieldCaptionIsTaken(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('string', 'SomeString');
        $entity->save();
        self::assertSame(
            'SomeCaption',
            $entity->ref(Audit::class)->loadAny()->get('ident'),
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
        $entity->set('values', 1);
        $entity->save();

        self::assertEquals(
            2,
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
        $entity->set('values', 1);
        $entity->save();

        self::assertEquals(
            2,
            $entity->ref(Audit::class)->action('count')->getOne()
        );
    }


    public function testFieldValueIdenticalNoAudit(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('string', 'Dede');
        $data = [];
        $this->callProtected($entity, '_addFieldToAudit', $data, 'string', 'Dede');
        self::assertSame(
            [],
            $data
        );
    }

    public function testStringsLooselyComparedNoAudit(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('string', null);
        $data = [];
        //as strings are compared using ==, null should equal ''
        $this->callProtected($entity, '_addFieldToAudit', $data, 'string', '');
        self::assertSame(
            [],
            $data
        );
    }

    public function testSeveralFieldChangesCreateOneAuditEntry(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('string', 'Lala');
        $entity->set('text', 'Gaga');
        $entity->save();

        self::assertEquals(
            2,
            $entity->ref(Audit::class)->action('count')->getOne()
        );

        self::assertCount(
            2,
            $entity->ref(Audit::class)->loadAny()->get('data')
        );
    }

    public function testAuditRemainsAfterDeletingModel(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('string', 'Lala');
        $entity->set('text', 'Gaga');
        $entity->save();

        self::assertEquals(
            2,
            $entity->ref(Audit::class)->action('count')->getOne()
        );

        $entity->delete();

        $auditForModel = new Audit($this->db);
        $auditForModel->set('model_id', $entity->get('id'));
        $auditForModel->set('model_class', ModelWithAudit::class);

        self::assertEquals(
            3,
            $auditForModel->action('count')->getOne()
        );
    }

    public function testCreateChangeAndDeleteAuditIsAdded(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('string', 'Lala');
        $entity->set('text', 'Gaga');
        $entity->save();
        $entity->set('string', 'Baba');
        $entity->save();
        $entity->delete();

        $auditForModel = (new Audit($this->db))->createEntity();
        $auditForModel->set('model_id', $entity->get('id'));
        $auditForModel->set('model_class', ModelWithAudit::class);

        self::assertEquals(
            4,
            $auditForModel->action('count')->getOne()
        );

        $check = ['CREATED', 'CHANGE', 'DELETE'];

        foreach ($check as $valueName) {
            $checkAudit = clone $auditForModel;
            $checkAudit->addCondition('value', $valueName);
            $checkAudit->tryLoadAny();

            self::assertTrue($checkAudit->loaded());
        }
    }

    public function testTimeFieldAudit(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('time', '11:11');
        $entity->save();

        $audit = $entity->ref(Audit::class)->loadAny();
        self::assertSame(
            '11:11',
            $audit->get('data')['time']['new_value']
        );
    }

    public function testDateTimeFieldAudit(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('datetime', '2020-01-01T11:11:00+00:00');
        $entity->save();

        $audit = $entity->ref(Audit::class)->loadAny();
        self::assertSame(
            '01.01.2020 11:11',
            $audit->get('data')['datetime']['new_value']
        );
    }

    public function testDateFieldAudit(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();
        $entity->set('date', '2020-01-01T11:11:00+00:00');
        $entity->save();

        $audit = $entity->ref(Audit::class)->loadAny();
        self::assertSame(
            '01.01.2020',
            $audit->get('data')['date']['new_value']
        );
    }

    public function testHasOneAudit(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();

        $user1 = new User($this->db);
        $user1->set('string', 'Hans');
        $user1->save();

        $user2 = new User($this->db);
        $user2->set('string', 'Peter');
        $user2->save();

        $entity->set('user_id', $user1->get('id'));
        $entity->save();
        $audit = $entity->ref(Audit::class);
        $audit->loadAny();
        self::assertEquals(
            [
                'field_name' => 'Benutzer',
                'old_value' => null,
                'new_value' => 'Hans'
            ],
            $audit->get('data')['user_id']
        );

        $entity->set('user_id', $user2->get('id'));
        $entity->save();
        $audit = $entity->ref(Audit::class);
        $audit->loadAny();
        self::assertEquals(
            [
                'field_name' => 'Benutzer',
                'old_value' => 'Hans',
                'new_value' => 'Peter'
            ],
            $audit->get('data')['user_id']
        );

        $entity->set('user_id', null);
        $entity->save();
        $audit = $entity->ref(Audit::class);
        $audit->loadAny();
        self::assertEquals(
            [
                'field_name' => 'Benutzer',
                'old_value' => 'Peter',
                'new_value' => null
            ],
            $audit->get('data')['user_id']
        );
    }


    public function testValuesFieldAudit(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();

        $entity->set('values', 0);
        $entity->save();
        $audit = $entity->ref(Audit::class);
        $audit->loadAny();
        self::assertEquals(
            [
                'field_name' => 'ValuesTest',
                'old_value' => '',
                'new_value' => 'SomeValue'
            ],
            $audit->get('data')['values']
        );

        $entity->set('values', 1);
        $entity->save();
        $audit = $entity->ref(Audit::class);
        $audit->loadAny();
        self::assertEquals(
            [
                'field_name' => 'ValuesTest',
                'old_value' => 'SomeValue',
                'new_value' => 'SomeOtherValue'
            ],
            $audit->get('data')['values']
        );

        $entity->set('values', null);
        $entity->save();
        $audit = $entity->ref(Audit::class);
        $audit->loadAny();
        self::assertEquals(
            [
                'field_name' => 'ValuesTest',
                'old_value' => 'SomeOtherValue',
                'new_value' => ''
            ],
            $audit->get('data')['values']
        );
    }
}
