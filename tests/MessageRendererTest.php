<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests;

use Atk4\Data\Persistence\Array_;
use Atk4\Data\Schema\TestCase;
use DateTime;
use PhilippR\Atk4\Audit\Audit;
use PhilippR\Atk4\Audit\MessageRenderer;
use PhilippR\Atk4\Audit\Tests\Testclasses\Invoice;
use PhilippR\Atk4\Audit\Tests\Testclasses\User;
use stdClass;

class MessageRendererTest extends TestCase
{
    protected MessageRenderer $messageRenderer;

    protected function setUp(): void
    {
        $this->db = new Array_([]);
        $this->messageRenderer = new MessageRenderer();
    }

    public function testRenderCreatedMessage(): void
    {
        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice);

        $result = $this->messageRenderer->renderCreatedMessage($audit, $invoice);

        $this->assertSame('created Invoice', $result);
    }

    public function testRenderDeletedMessage(): void
    {
        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice);

        $result = $this->messageRenderer->renderDeletedMessage($audit, $invoice);

        $this->assertSame('deleted Invoice', $result);
    }

    public function testRenderScalarFieldAuditWithOldValue(): void
    {
        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice)
            ->set('ident', 'total');

        $auditData = new stdClass();
        $auditData->fieldType = 'money';
        $auditData->oldValue = 100;
        $auditData->newValue = 200;
        $audit->setData($auditData);

        $result = $this->messageRenderer->renderScalarFieldAudit($audit, $invoice);

        $this->assertSame('changed "Total" from "100" to "200"', $result);
    }

    public function testRenderScalarFieldAuditWithoutOldValue(): void
    {
        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice)
            ->set('ident', 'total');

        $auditData = new stdClass();
        $auditData->fieldType = 'money';
        $auditData->oldValue = null;
        $auditData->newValue = 200;
        $audit->setData($auditData);

        $result = $this->messageRenderer->renderScalarFieldAudit($audit, $invoice);

        $this->assertSame('set "Total" to "200"', $result);
    }

    public function testRenderDateTimeFieldAuditWithOldValue(): void
    {
        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice)
            ->set('ident', 'due_date');

        $oldDate = new DateTime('2023-01-01 10:30:00');
        $newDate = new DateTime('2023-02-01 15:45:00');

        $auditData = new stdClass();
        $auditData->fieldType = 'datetime';
        $auditData->oldValue = $oldDate->format(DATE_ATOM);
        $auditData->newValue = $newDate->format(DATE_ATOM);
        $audit->setData($auditData);

        $result = $this->messageRenderer->renderDateTimeFieldAudit($audit, $invoice, $this->messageRenderer->dateTimeFormat);

        $this->assertSame('changed "Due Date" from "2023-01-01 10:30" to "2023-02-01 15:45"', $result);
    }

    public function testRenderDateTimeFieldAuditWithoutOldValue(): void
    {
        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice)
            ->set('ident', 'due_date');

        $newDate = new DateTime('2023-02-01 15:45:00');

        $auditData = new stdClass();
        $auditData->fieldType = 'datetime';
        $auditData->oldValue = null;
        $auditData->newValue = $newDate->format(DATE_ATOM);
        $audit->setData($auditData);

        $result = $this->messageRenderer->renderDateTimeFieldAudit($audit, $invoice, $this->messageRenderer->dateTimeFormat);

        $this->assertSame('set "Due Date" to "2023-02-01 15:45"', $result);
    }

    public function testRenderDateFieldAudit(): void
    {
        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice)
            ->set('ident', 'due_date');

        $oldDate = new DateTime('2023-01-01');
        $newDate = new DateTime('2023-02-01');

        $auditData = new stdClass();
        $auditData->fieldType = 'date';
        $auditData->oldValue = $oldDate->format(DATE_ATOM);
        $auditData->newValue = $newDate->format(DATE_ATOM);
        $audit->setData($auditData);

        $result = $this->messageRenderer->renderDateTimeFieldAudit($audit, $invoice, $this->messageRenderer->dateFormat);

        $this->assertSame('changed "Due Date" from "2023-01-01" to "2023-02-01"', $result);
    }

    public function testRenderTimeFieldAudit(): void
    {
        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice)
            ->set('ident', 'due_date');

        $oldTime = new DateTime('2023-01-01 10:30:00');
        $newTime = new DateTime('2023-01-01 15:45:00');

        $auditData = new stdClass();
        $auditData->fieldType = 'time';
        $auditData->oldValue = $oldTime->format(DATE_ATOM);
        $auditData->newValue = $newTime->format(DATE_ATOM);
        $audit->setData($auditData);

        $result = $this->messageRenderer->renderDateTimeFieldAudit($audit, $invoice, $this->messageRenderer->timeFormat);

        $this->assertSame('changed "Due Date" from "10:30" to "15:45"', $result);
    }

    public function testRenderJsonFieldAuditWithOldValue(): void
    {
        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice)
            ->set('ident', 'total');

        $oldData = ['amount' => 100, 'currency' => 'USD'];
        $newData = ['amount' => 200, 'currency' => 'EUR'];

        $auditData = new stdClass();
        $auditData->fieldType = 'json';
        $auditData->oldValue = $oldData;
        $auditData->newValue = $newData;
        $audit->setData($auditData);

        $result = $this->messageRenderer->renderJsonFieldAudit($audit, $invoice);

        $expectedOld = json_encode($oldData);
        $expectedNew = json_encode($newData);
        $this->assertSame('changed "Total" from "' . $expectedOld . '" to "' . $expectedNew . '"', $result);
    }

    public function testRenderJsonFieldAuditWithoutOldValue(): void
    {
        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice)
            ->set('ident', 'total');

        $newData = ['amount' => 200, 'currency' => 'EUR'];

        $auditData = new stdClass();
        $auditData->fieldType = 'json';
        $auditData->oldValue = null;
        $auditData->newValue = $newData;
        $audit->setData($auditData);

        $result = $this->messageRenderer->renderJsonFieldAudit($audit, $invoice);

        $expectedNew = json_encode($newData);
        $this->assertSame('set "Total" to "' . $expectedNew . '"', $result);
    }

    public function testRenderJsonFieldAuditWithEmptyNewValue(): void
    {
        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice)
            ->set('ident', 'total');

        $auditData = new stdClass();
        $auditData->fieldType = 'json';
        $auditData->oldValue = null;
        $auditData->newValue = null;
        $audit->setData($auditData);

        $result = $this->messageRenderer->renderJsonFieldAudit($audit, $invoice);

        $this->assertSame('set "Total" to ""', $result);
    }

    public function testRenderHasOneAuditWithOldValue(): void
    {
        $user1 = (new User($this->db))
            ->createEntity()
            ->set('name', 'User One')
            ->save();

        $user2 = (new User($this->db))
            ->createEntity()
            ->set('name', 'User Two')
            ->save();

        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->set('user_id', $user1->getId())
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice)
            ->set('ident', 'user_id');

        $auditData = new stdClass();
        $auditData->fieldType = 'integer';
        $auditData->oldValue = $user1->getId();
        $auditData->newValue = $user2->getId();
        $audit->setData($auditData);

        $result = $this->messageRenderer->renderHasOneAudit($audit, $invoice);

        $this->assertSame('changed "User" from "User One" to "User Two"', $result);
    }

    public function testRenderHasOneAuditWithoutOldValue(): void
    {
        $user = (new User($this->db))
            ->createEntity()
            ->set('name', 'New User')
            ->save();

        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice)
            ->set('ident', 'user_id');

        $auditData = new stdClass();
        $auditData->fieldType = 'integer';
        $auditData->oldValue = null;
        $auditData->newValue = $user->getId();
        $audit->setData($auditData);

        $result = $this->messageRenderer->renderHasOneAudit($audit, $invoice);

        $this->assertSame('set "User" to "New User"', $result);
    }

    public function testRenderKeyValueAuditWithOldValue(): void
    {
        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->save();

        // Add values to the status field for testing
        $invoice->getModel()->getField('status')->values = [
            'draft' => 'Draft',
            'sent' => 'Sent',
            'paid' => 'Paid'
        ];

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice)
            ->set('ident', 'status');

        $auditData = new stdClass();
        $auditData->fieldType = 'string';
        $auditData->oldValue = 'draft';
        $auditData->newValue = 'sent';
        $audit->setData($auditData);

        $result = $this->messageRenderer->renderKeyValueAudit($audit, $invoice);

        $this->assertSame('changed "Status" from "Draft" to "Sent"', $result);
    }

    public function testRenderKeyValueAuditWithoutOldValue(): void
    {
        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->save();

        // Add values to the status field for testing
        $invoice->getModel()->getField('status')->values = [
            'draft' => 'Draft',
            'sent' => 'Sent',
            'paid' => 'Paid'
        ];

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice)
            ->set('ident', 'status');

        $auditData = new stdClass();
        $auditData->fieldType = 'string';
        $auditData->oldValue = null;
        $auditData->newValue = 'draft';
        $audit->setData($auditData);

        $result = $this->messageRenderer->renderKeyValueAudit($audit, $invoice);

        $this->assertSame('set "Status" to "Draft"', $result);
    }

    public function testRenderFieldAuditWithScalarField(): void
    {
        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice)
            ->set('ident', 'total');

        $auditData = new stdClass();
        $auditData->fieldType = 'money';
        $auditData->oldValue = 100;
        $auditData->newValue = 200;
        $audit->setData($auditData);

        $result = $this->messageRenderer->renderFieldAudit($audit, $invoice);

        $this->assertSame('changed "Total" from "100" to "200"', $result);
    }

    public function testRenderFieldAuditWithDateTimeField(): void
    {
        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice)
            ->set('ident', 'due_date');

        $oldDate = new DateTime('2023-01-01 10:30:00');
        $newDate = new DateTime('2023-02-01 15:45:00');

        $auditData = new stdClass();
        $auditData->fieldType = 'datetime';
        $auditData->oldValue = $oldDate->format(DATE_ATOM);
        $auditData->newValue = $newDate->format(DATE_ATOM);
        $audit->setData($auditData);

        $result = $this->messageRenderer->renderFieldAudit($audit, $invoice);

        $this->assertSame('changed "Due Date" from "2023-01-01 10:30" to "2023-02-01 15:45"', $result);
    }

    public function testRenderFieldAuditWithJsonField(): void
    {
        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice)
            ->set('ident', 'total');

        $oldData = ['amount' => 100];
        $newData = ['amount' => 200];

        $auditData = new stdClass();
        $auditData->fieldType = 'json';
        $auditData->oldValue = $oldData;
        $auditData->newValue = $newData;
        $audit->setData($auditData);

        $result = $this->messageRenderer->renderFieldAudit($audit, $invoice);

        $expectedOld = json_encode($oldData);
        $expectedNew = json_encode($newData);
        $this->assertSame('changed "Total" from "' . $expectedOld . '" to "' . $expectedNew . '"', $result);
    }

    public function testRenderFieldAuditWithHasOneReference(): void
    {
        $user1 = (new User($this->db))
            ->createEntity()
            ->set('name', 'User One')
            ->save();

        $user2 = (new User($this->db))
            ->createEntity()
            ->set('name', 'User Two')
            ->save();

        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->set('user_id', $user1->getId())
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice)
            ->set('ident', 'user_id');

        $auditData = new stdClass();
        $auditData->fieldType = 'integer';
        $auditData->oldValue = $user1->getId();
        $auditData->newValue = $user2->getId();
        $audit->setData($auditData);

        $result = $this->messageRenderer->renderFieldAudit($audit, $invoice);

        $this->assertSame('changed "User" from "User One" to "User Two"', $result);
    }

    public function testCustomTemplates(): void
    {
        $renderer = new MessageRenderer();
        $renderer->changedTemplate = 'modified {fieldName}: {oldValue} -> {newValue}';
        $renderer->setTemplate = 'initialized {fieldName} = {newValue}';
        $renderer->createdTemplate = 'new {modelCaption} created';
        $renderer->deletedTemplate = 'removed {modelCaption}';

        $invoice = (new Invoice($this->db))
            ->createEntity()
            ->save();

        $audit = (new Audit($this->db))
            ->createEntity()
            ->setParentEntity($invoice);

        // Test custom created template
        $result = $renderer->renderCreatedMessage($audit, $invoice);
        $this->assertSame('new Invoice created', $result);

        // Test custom deleted template
        $result = $renderer->renderDeletedMessage($audit, $invoice);
        $this->assertSame('removed Invoice', $result);

        // Test custom changed template
        $audit->set('ident', 'total');
        $auditData = new stdClass();
        $auditData->fieldType = 'money';
        $auditData->oldValue = 100;
        $auditData->newValue = 200;
        $audit->setData($auditData);

        $result = $renderer->renderScalarFieldAudit($audit, $invoice);
        $this->assertSame('modified Total: 100 -> 200', $result);

        // Test custom set template
        $auditData->oldValue = null;
        $audit->setData($auditData);

        $result = $renderer->renderScalarFieldAudit($audit, $invoice);
        $this->assertSame('initialized Total = 200', $result);
    }
}