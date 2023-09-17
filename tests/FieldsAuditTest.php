<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests;

use Atk4\Data\Persistence;
use Atk4\Data\Persistence\Sql;
use Atk4\Data\Schema\TestCase;
use PhilippR\Atk4\Audit\Audit;
use PhilippR\Atk4\Audit\Tests\Testclasses\AuditRendererDemo;
use PhilippR\Atk4\Audit\Tests\Testclasses\ModelWithAudit;
use PhilippR\Atk4\Audit\Tests\Testclasses\User;


class FieldsAuditTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new Sql('sqlite::memory:');
        $this->createMigrator(new Audit($this->db))->create();
        $this->createMigrator(new User($this->db))->create();
    }

    public function testSettingInAppDisablesAudit()
    {
        $this->db = $this->getSqliteTestPersistence([Email::class]);
        $this->db->app = new AppWithAuditSetting();
        $this->db->app->createAudit = false;
        $model = new ModelWithAudit($this->db);
        $model->set('name', 'Lala');
        $model->save();

        $user = new User($this->db);
        $user->save();
        $model->addMToMAudit('ADD', $user);

        $email = new Email($this->db);
        $email->save();
        $model->addSecondaryAudit('ADD', $email);

        $model->addAdditionalAudit('SOMETYPE', []);

        $audit = $model->ref(Audit::class);

        self::assertEquals(
            0,
            $audit->action('count')->getOne()
        );

        $model->delete();

        self::assertEquals(
            0,
            $audit->action('count')->getOne()
        );
    }

    public function testNoAuditCreatedOnNoChange()
    {
        $model = new ModelWithAudit($this->db);

        //should create
        $model->set('name', 'Lala');
        $model->save();
        self::assertEquals(
            2,
            $model->ref(Audit::class)->action('count')->getOne()
        );
        $model->set('name', 'Lala');
        $model->save();
        self::assertEquals(
            2,
            $model->ref(Audit::class)->action('count')->getOne()
        );
    }


    public function testFieldValueIdenticalNoAudit()
    {
        $model = new ModelWithAudit($this->db);
        $model->set('name', 'Dede');
        $data = [];
        $this->callProtected($model, '_addFieldToAudit', $data, 'name', 'Dede');
        self::assertSame(
            [],
            $data
        );
    }

    public function testStringsLooselyComparedNoAudit()
    {
        $model = new ModelWithAudit($this->db);
        $model->set('name', null);
        $data = [];
        //as strings are compared using ==, null should equal ''
        $this->callProtected($model, '_addFieldToAudit', $data, 'name', '');
        self::assertSame(
            [],
            $data
        );
    }

    public function testSeveralFieldChangesCreateOneAuditEntry()
    {
        $model = new ModelWithAudit($this->db);
        $model->set('name', 'Lala');
        $model->set('other_field', 'Gaga');
        $model->save();

        self::assertEquals(
            2,
            $model->ref(Audit::class)->action('count')->getOne()
        );

        self::assertCount(
            2,
            $model->ref(Audit::class)->loadAny()->get('data')
        );
    }

    public function testAuditRemainsAfterDeletingModel()
    {
        $model = new ModelWithAudit($this->db);
        $model->set('name', 'Lala');
        $model->set('other_field', 'Gaga');
        $model->save();

        self::assertEquals(
            2,
            $model->ref(Audit::class)->action('count')->getOne()
        );

        $model->delete();

        $auditForModel = new Audit($this->db);
        $auditForModel->set('model_id', $model->get('id'));
        $auditForModel->set('model_class', ModelWithAudit::class);

        self::assertEquals(
            3,
            $auditForModel->action('count')->getOne()
        );
    }

    public function testCreateChangeAndDeleteAuditIsAdded()
    {
        $model = new ModelWithAudit($this->db);
        $model->set('name', 'Lala');
        $model->set('other_field', 'Gaga');
        $model->save();
        $model->set('name', 'Baba');
        $model->save();
        $model->delete();

        $auditForModel = new Audit($this->db);
        $auditForModel->set('model_id', $model->get('id'));
        $auditForModel->set('model_class', ModelWithAudit::class);

        self::assertEquals(
            4,
            $auditForModel->action('count')->getOne()
        );

        $check = ['CREATE', 'CHANGE', 'DELETE'];

        foreach ($check as $valueName) {
            $checkAudit = clone $auditForModel;
            $checkAudit->addCondition('value', $valueName);
            $checkAudit->tryLoadAny();

            self::assertTrue($checkAudit->loaded());
        }
    }

    public function testTimeFieldAudit()
    {
        $model = new ModelWithAudit($this->db);
        $model->set('time', '11:11');
        $model->save();

        $audit = $model->ref(Audit::class)->loadAny();
        self::assertSame(
            '11:11',
            $audit->get('data')['time']['new_value']
        );
    }

    public function testDateTimeFieldAudit()
    {
        $model = new ModelWithAudit($this->db);
        $model->set('datetime', '2020-01-01T11:11:00+00:00');
        $model->save();

        $audit = $model->ref(Audit::class)->loadAny();
        self::assertSame(
            '01.01.2020 11:11',
            $audit->get('data')['datetime']['new_value']
        );
    }

    public function testDateFieldAudit()
    {
        $model = new ModelWithAudit($this->db);
        $model->set('date', '2020-01-01T11:11:00+00:00');
        $model->save();

        $audit = $model->ref(Audit::class)->loadAny();
        self::assertSame(
            '01.01.2020',
            $audit->get('data')['date']['new_value']
        );
    }

    public function testHasOneAudit()
    {
        $model = new ModelWithAudit($this->db);

        $user1 = new User($this->db);
        $user1->set('name', 'Hans');
        $user1->save();

        $user2 = new User($this->db);
        $user2->set('name', 'Peter');
        $user2->save();

        $model->set('user_id', $user1->get('id'));
        $model->save();
        $audit = $model->ref(Audit::class);
        $audit->loadAny();
        self::assertEquals(
            [
                'field_name' => 'Benutzer',
                'old_value' => null,
                'new_value' => 'Hans'
            ],
            $audit->get('data')['user_id']
        );

        $model->set('user_id', $user2->get('id'));
        $model->save();
        $audit = $model->ref(Audit::class);
        $audit->loadAny();
        self::assertEquals(
            [
                'field_name' => 'Benutzer',
                'old_value' => 'Hans',
                'new_value' => 'Peter'
            ],
            $audit->get('data')['user_id']
        );

        $model->set('user_id', null);
        $model->save();
        $audit = $model->ref(Audit::class);
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


    public function testValuesFieldAudit()
    {
        $model = new ModelWithAudit($this->db);

        $model->set('values', 0);
        $model->save();
        $audit = $model->ref(Audit::class);
        $audit->loadAny();
        self::assertEquals(
            [
                'field_name' => 'ValuesTest',
                'old_value' => '',
                'new_value' => 'SomeValue'
            ],
            $audit->get('data')['values']
        );

        $model->set('values', 1);
        $model->save();
        $audit = $model->ref(Audit::class);
        $audit->loadAny();
        self::assertEquals(
            [
                'field_name' => 'ValuesTest',
                'old_value' => 'SomeValue',
                'new_value' => 'SomeOtherValue'
            ],
            $audit->get('data')['values']
        );

        $model->set('values', null);
        $model->save();
        $audit = $model->ref(Audit::class);
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


    public function testSkipFieldsIfSet()
    {
        $model = new ModelWithAudit($this->db);
        $model->set('other_field', 'bla');
        $model->save();
        self::assertSame(
            2,
            (int)$model->ref(Audit::class)->action('count')->getOne()
        );

        //now disable audit for that field
        $model->setSkipFields(['other_field']);
        $model->set('other_field', 'du');
        $model->save();
        self::assertSame(
            2,
            (int)$model->ref(Audit::class)->action('count')->getOne()
        );
        //reenable, audit should be created
        $model->setSkipFields(['name']);
        $model->set('other_field', 'kra');
        $model->save();
        self::assertSame(
            3,
            (int)$model->ref(Audit::class)->action('count')->getOne()
        );
    }
}
