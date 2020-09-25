<?php declare(strict_types=1);

namespace auditforatk\tests;

use atk4\data\Persistence;
use atk4\schema\Migration;
use auditforatk\Audit;
use atk4\core\AtkPhpunit\TestCase;
use auditforatk\tests\testclasses\AppWithAuditSetting;
use auditforatk\tests\testclasses\AuditRendererDemo;
use auditforatk\tests\testclasses\PersistenceWithApp;
use auditforatk\tests\testclasses\User;


class AuditTest extends TestCase {

    public function testUserInfoOnSave() {
        $audit = new Audit($this->getPersistence());
        $audit->save();
        self::assertEquals(
            $audit->get('created_by_name'),
            'SOME NAME'
        );
    }

    public function testAuditMessageRendererIsUsed() {
        $audit = new Audit($this->getPersistence());
        $audit->auditRenderer = new AuditRendererDemo();
        $audit->save();
        self::assertEquals(
            $audit->get('rendered_output'),
            'Demo'
        );
    }

    protected function getPersistence(): Persistence {
        $persistence = PersistenceWithApp::connect('sqlite::memory:');
        $model1 = new Audit($persistence);
        Migration::of($model1)->drop()->create();
        $user = new User($persistence);
        Migration::of($user)->drop()->create();

        $persistence->app = new AppWithAuditSetting();
        $persistence->app->auth = new \stdClass();
        $persistence->app->auth->user = new User($persistence);
        $persistence->app->auth->user->set('name', 'SOME NAME');
        $persistence->app->auth->user->save();

        return $persistence;
    }
}
