<?php declare(strict_types=1);

namespace auditforatk\tests;

use PMRAtk\Data\Audit;
use PMRAtk\tests\phpunit\TestCase;

class AuditTest extends TestCase {

    /**
     * see if created_by and created_by_name are set on save
     */
    public function testUserInfoOnSave() {
        $audit = new Audit(self::$app->db);
        $audit->save();
        self::assertEquals($audit->get('created_by_name'), self::$app->auth->user->get('name'));
    }
}
