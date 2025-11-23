<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests;

use Atk4\Data\Persistence\Sql;
use Atk4\Data\Schema\TestCase;
use PhilippR\Atk4\Audit\Audit;
use PhilippR\Atk4\Audit\MessageRenderer;
use PhilippR\Atk4\Audit\Tests\Testclasses\ModelWithAudit;
use PhilippR\Atk4\Audit\Tests\Testclasses\User;

class MessageRendererTest extends TestCase
{
    protected MessageRenderer $messageRenderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new Sql('sqlite::memory:');
        $this->createMigrator(new Audit($this->db))->create();
        $this->createMigrator(new ModelWithAudit($this->db))->create();
        $this->createMigrator(new User($this->db))->create();

        $this->messageRenderer = new MessageRenderer();
    }

    public function testRenderCreatedMessage(): void
    {
        $modelWithAudit = (new ModelWithAudit($this->db))
            ->createEntity()
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($modelWithAudit);

        $result = $this->messageRenderer->renderCreatedMessage($audit, $modelWithAudit);

        $this->assertSame('created Model with audit', $result);
    }

    public function testRenderDeletedMessage(): void
    {
        $modelWithAudit = (new ModelWithAudit($this->db))
            ->createEntity()
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($modelWithAudit);

        $result = $this->messageRenderer->renderDeletedMessage($audit, $modelWithAudit);

        $this->assertSame('deleted Model with audit', $result);
    }

    public function testRenderFieldAuditWithUser(): void
    {
        $user = (new User($this->db))
            ->createEntity();
        $user->set('name', 'John Doe');
        $user->save();

        $modelWithAudit = (new ModelWithAudit($this->db))
            ->createEntity()
            ->set('user_id', $user->getId())
            ->save();

        $audit = $modelWithAudit->ref(Audit::class)->loadAny();

        $result = $this->messageRenderer->renderFieldAudit($audit, $modelWithAudit);

        $this->assertStringContainsString('Benutzer', $result);
        $this->assertStringContainsString('John Doe', $result);
    }

    public function testRenderScalarFieldAudit(): void
    {
        $modelWithAudit = (new ModelWithAudit($this->db))
            ->createEntity();
        $modelWithAudit->set('string', 'test value');
        $modelWithAudit->save();

        $audit = $modelWithAudit->ref(Audit::class)->loadAny();

        $result = $this->messageRenderer->renderFieldAudit($audit, $modelWithAudit);

        $this->assertStringContainsString('SomeCaption', $result);
        $this->assertStringContainsString('test value', $result);
    }
}