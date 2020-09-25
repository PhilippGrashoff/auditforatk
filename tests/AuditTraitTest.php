<?php declare(strict_types=1);

namespace auditforatk\tests;

use atk4\data\Persistence;
use atk4\ui\UserAction\ModalExecutor;
use auditforatk\Audit;
use auditforatk\tests\testclasses\AppWithAuditSetting;
use auditforatk\tests\testclasses\Email;
use auditforatk\tests\testclasses\ModelWithAudit;
use auditforatk\tests\testclasses\PersistenceWithApp;
use auditforatk\tests\testclasses\User;


class AuditTraitTest extends TestCase
{
    protected $sqlitePersistenceModels = [
        Audit::class,
        ModelWithAudit::class,
        User::class
    ];

    public function testSettingInAppDisablesAudit() {
        $persistence = $this->getSqliteTestPersistence([Email::class]);
        $persistence->app = new AppWithAuditSetting();
        $persistence->app->createAudit = false;
        $model = new ModelWithAudit($persistence);
        $model->set('name', 'Lala');
        $model->save();

        $user = new User($persistence);
        $user->save();
        $model->addMToMAudit('ADD', $user);

        $email = new Email($persistence);
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

    public function testGetAuditViewModel() {
        $model = new ModelWithAudit($this->getSqliteTestPersistence());
        self::assertInstanceOf(
            Audit::class,
            $model->getAuditViewModel()
        );
    }

    public function testNoAuditCreatedOnNoChange() {
        $persistence = $this->getSqliteTestPersistence();
        $model = new ModelWithAudit($persistence);

        //should create
        $model->set('name', 'Lala');
        $model->save();
        self::assertEquals(
            1,
            $model->ref(Audit::class)->action('count')->getOne()
        );
        //change Id field. that should trigger save, but no audit created as this field is ignored
        $model->set('id', '333');
        $model->save();
        self::assertEquals(
            0, //0 here as Id changed so initial audit has no parent
            $model->ref(Audit::class)->action('count')->getOne()
        );
    }

    public function testIdFieldIsIgnored() {
        $model = new ModelWithAudit($this->getSqliteTestPersistence());
        $data = [];
        $this->callProtected($model, '_addFieldToAudit', $data, 'id', '235');
        self::assertSame(
            [],
            $data
        );
    }

    public function testFieldValueIdenticalNoAudit() {
        $model = new ModelWithAudit($this->getSqliteTestPersistence());
        $model->set('name', 'Dede');
        $data = [];
        $this->callProtected($model, '_addFieldToAudit', $data, 'name', 'Dede');
        self::assertSame(
            [],
            $data
        );
    }

    public function testStringsLooselyComparedNoAudit() {
        $model = new ModelWithAudit($this->getSqliteTestPersistence());
        $model->set('name', null);
        $data = [];
        //as strings are compared using ==, null should equal ''
        $this->callProtected($model, '_addFieldToAudit', $data, 'name', '');
        self::assertSame(
            [],
            $data
        );
    }

    public function testSeveralFieldChangesCreateOneAuditEntry() {
        $model = new ModelWithAudit($this->getSqliteTestPersistence());
        $model->set('name', 'Lala');
        $model->set('other_field', 'Gaga');
        $model->save();

        self::assertEquals(
            1,
            $model->ref(Audit::class)->action('count')->getOne()
        );

        self::assertCount(
            2,
            $model->ref(Audit::class)->loadAny()->get('data')
        );
    }

    public function testAuditRemainsAfterDeletingModel() {
        $persistence = $this->getSqliteTestPersistence();
        $model = new ModelWithAudit($persistence);
        $model->set('name', 'Lala');
        $model->set('other_field', 'Gaga');
        $model->save();

        self::assertEquals(
            1,
            $model->ref(Audit::class)->action('count')->getOne()
        );

        $model->delete();

        $auditForModel = new Audit($persistence);
        $auditForModel->set('model_id', $model->get('id'));
        $auditForModel->set('model_class', ModelWithAudit::class);

        self::assertEquals(
            2,
            $auditForModel->action('count')->getOne()
        );
    }

    public function testCreateChangeAndDeleteAuditIsAdded() {
        $persistence = $this->getSqliteTestPersistence();
        $model = new ModelWithAudit($persistence);
        $model->set('name', 'Lala');
        $model->set('other_field', 'Gaga');
        $model->save();
        $model->set('name', 'Baba');
        $model->save();
        $model->delete();

        $auditForModel = new Audit($persistence);
        $auditForModel->set('model_id', $model->get('id'));
        $auditForModel->set('model_class', ModelWithAudit::class);

        self::assertEquals(
            3,
            $auditForModel->action('count')->getOne()
        );

        $check = ['CREATE', 'CHANGE', 'DELETE'];

        foreach ($check as $valueName) {
            $checkAudit = clone $auditForModel;
            $checkAudit->addCondition('value', $valueName);
            $checkAudit->loadAny();

            self::assertEquals(
                1,
                $checkAudit->action('count')->getOne()
            );
        }
    }

    public function testMToMAudit()
    {
        $persistence = $this->getSqliteTestPersistence();
        $model = new ModelWithAudit($persistence);
        $model->save();
        $user = new User($persistence);
        $model->addMToMAudit('ADD', $user);

        self::assertEquals(
            2,
            $model->ref(Audit::class)->action('count')->getOne()
        );
    }

    public function testAddSecondaryAudit() {
        $persistence = $this->getSqliteTestPersistence([Email::class]);
        $model = new ModelWithAudit($persistence);
        $model->save();
        $email = new Email($persistence);
        $email->set('value', 'someEmail');
        $model->addSecondaryAudit('ADD', $email);

        self::assertEquals(
            2,
            $model->ref(Audit::class)->action('count')->getOne()
        );
    }

    public function testAddSecondaryAuditWithDifferentModelClassAndId() {
        $persistence = $this->getSqliteTestPersistence([Email::class]);
        $model = new ModelWithAudit($persistence);
        $model->save();

        $email = new Email($persistence);
        $email->set('value', 'someEmail');
        $model->addSecondaryAudit('ADD', $email, 'value', 'SomeOtherClass', '444');

        $audit = new Audit($persistence);
        $audit->addCondition('model_class', 'SomeOtherClass');
        $audit->addCondition('model_id', '444');
        $audit->loadAny();
        self::assertEquals(
            'SomeOtherClass',
            $audit->get('model_class')
        );

        self::assertEquals(
            '444',
            $audit->get('model_id')
        );
    }

    public function testAddAdditionalAudit() {
        $persistence = $this->getSqliteTestPersistence();
        $model = new ModelWithAudit($persistence);
        $model->save();
        $model->addAdditionalAudit('SOME_TYPE', ['bagga' => 'wagga']);

        self::assertEquals(
            2,
            $model->ref(Audit::class)->action('count')->getOne()
        );
    }

    public function testTimeFieldAudit() {
        $persistence = $this->getSqliteTestPersistence();
        $model = new ModelWithAudit($persistence);
        $model->set('time', '11:11');
        $model->save();

        $audit = $model->ref(Audit::class)->loadAny();
        self::assertSame(
            '11:11',
            $audit->get('data')['time']['new_value']
        );
    }

    public function testDateTimeFieldAudit() {
        $persistence = $this->getSqliteTestPersistence();
        $model = new ModelWithAudit($persistence);
        $model->set('datetime', '2020-01-01T11:11:00+00:00');
        $model->save();

        $audit = $model->ref(Audit::class)->loadAny();
        self::assertSame(
            '01.01.2020 11:11',
            $audit->get('data')['datetime']['new_value']
        );
    }

    public function testDateFieldAudit() {
        $persistence = $this->getSqliteTestPersistence();
        $model = new ModelWithAudit($persistence);
        $model->set('date', '2020-01-01T11:11:00+00:00');
        $model->save();

        $audit = $model->ref(Audit::class)->loadAny();
        self::assertSame(
            '01.01.2020',
            $audit->get('data')['date']['new_value']
        );
    }

    public function testHasOneAudit() {
        $persistence = $this->getSqliteTestPersistence();
        $model = new ModelWithAudit($persistence);

        $user1 = new User($persistence);
        $user1->set('name', 'Hans');
        $user1->save();

        $user2 = new User($persistence);
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


    public function testValuesFieldAudit() {
        $persistence = $this->getSqliteTestPersistence();
        $model = new ModelWithAudit($persistence);

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



    public function testDropDownFieldAudit() {
        $persistence = $this->getSqliteTestPersistence();
        $model = new ModelWithAudit($persistence);

        $model->set('dropdown', 0);
        $model->save();
        $audit = $model->ref(Audit::class);
        $audit->loadAny();
        self::assertEquals(
            [
                'field_name' => 'DropDownTest',
                'old_value' => '',
                'new_value' => 'SomeValue'
            ],
            $audit->get('data')['dropdown']
        );

        $model->set('dropdown', 1);
        $model->save();
        $audit = $model->ref(Audit::class);
        $audit->loadAny();
        self::assertEquals(
            [
                'field_name' => 'DropDownTest',
                'old_value' => 'SomeValue',
                'new_value' => 'SomeOtherValue'
            ],
            $audit->get('data')['dropdown']
        );

        $model->set('dropdown', null);
        $model->save();
        $audit = $model->ref(Audit::class);
        $audit->loadAny();
        self::assertEquals(
            [
                'field_name' => 'DropDownTest',
                'old_value' => 'SomeOtherValue',
                'new_value' => ''
            ],
            $audit->get('data')['dropdown']
        );

        $model->set('dropdown', 'someNonExistantValue');
        $model->save();
        $audit = $model->ref(Audit::class);
        $audit->loadAny();
        self::assertEquals(
            [
                'field_name' => 'DropDownTest',
                'old_value' => '',
                'new_value' => ''
            ],
            $audit->get('data')['dropdown']
        );

        $model->set('dropdown', 'someOtherNonExistantValue');
        $model->save();
        $audit = $model->ref(Audit::class);
        $audit->loadAny();
        self::assertEquals(
            [
                'field_name' => 'DropDownTest',
                'old_value' => '',
                'new_value' => ''
            ],
            $audit->get('data')['dropdown']
        );
    }


}
