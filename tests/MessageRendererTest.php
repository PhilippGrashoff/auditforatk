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

    public function testRenderJsonFieldAuditWithOldValue(): void
    {
        $modelWithAudit = (new ModelWithAudit($this->db))
            ->createEntity();

        // Set initial JSON value
        $initialValue = ['key' => 'initial', 'number' => 42];
        $modelWithAudit->set('json', $initialValue);
        $modelWithAudit->save();

        // Change JSON value to test the selected code path
        $newValue = ['key' => 'updated', 'number' => 99];
        $modelWithAudit->set('json', $newValue);
        $modelWithAudit->save();

        // Get the latest audit record (the change audit, not the initial set audit)
        $audit = $modelWithAudit->ref(Audit::class)->loadAny();

        $result = $this->messageRenderer->renderFieldAudit($audit, $modelWithAudit);

        // Verify the message contains expected elements
        $this->assertStringContainsString('Json', $result); // Field caption
        $this->assertStringContainsString('changed', $result); // Uses changedTemplate
        $this->assertStringContainsString('from', $result); // changedTemplate format
        $this->assertStringContainsString('to', $result); // changedTemplate format

        // Verify JSON values are properly encoded in the message
        $expectedOldValue = json_encode($initialValue);
        $expectedNewValue = json_encode($newValue);
        $this->assertStringContainsString($expectedOldValue, $result);
        $this->assertStringContainsString($expectedNewValue, $result);
    }

    public function testRenderJsonFieldAuditWithNullNewValue(): void
    {
        $modelWithAudit = (new ModelWithAudit($this->db))
            ->createEntity();

        // Set initial JSON value
        $initialValue = ['key' => 'initial', 'data' => ['nested' => 'value']];
        $modelWithAudit->set('json', $initialValue);
        $modelWithAudit->save();

        // Set to null to test the newValue ? json_encode($auditData->newValue) : '' part
        $modelWithAudit->set('json', null);
        $modelWithAudit->save();

        $audit = $modelWithAudit->ref(Audit::class)->loadAny();

        $result = $this->messageRenderer->renderFieldAudit($audit, $modelWithAudit);

        // Verify the message structure
        $this->assertStringContainsString('Json', $result);
        $this->assertStringContainsString('changed', $result);
        $this->assertStringContainsString('from', $result);
        $this->assertStringContainsString('to', $result);

        // Verify the old value is JSON encoded and new value is empty string
        $expectedOldValue = json_encode($initialValue);
        $this->assertStringContainsString($expectedOldValue, $result);
        $this->assertStringContainsString('" to ""', $result); // Empty new value
    }
}