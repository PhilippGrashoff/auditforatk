<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests;

use Atk4\Data\Persistence\Sql;
use Atk4\Data\Schema\TestCase;
use PhilippR\Atk4\Audit\FieldAuditTypeController;
use PhilippR\Atk4\Audit\Tests\Testclasses\ModelWithAudit;

class FieldAuditTypeControllerTest extends TestCase
{
    protected FieldAuditTypeController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new Sql('sqlite::memory:');
        $this->createMigrator(new ModelWithAudit($this->db))->create();

        $this->controller = new FieldAuditTypeController();
    }

    public function testSkipFieldFromAuditWhenFieldDoesNotExist(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();

        // Test !$entity->hasField($fieldName) condition
        $result = $this->controller->getFieldAuditType($entity, 'non_existent_field');

        self::assertSame(FieldAuditTypeController::TYPE_SKIP, $result);
    }

    public function testSkipFieldFromAuditWhenFieldIsIdField(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();

        // Test $fieldName === $entity->idField condition
        $result = $this->controller->getFieldAuditType($entity, $entity->idField);

        self::assertSame(FieldAuditTypeController::TYPE_SKIP, $result);
    }

    public function testSkipFieldFromAuditWhenFieldNeverPersist(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();

        // Test $entity->getField($fieldName)->neverPersist === true condition
        $result = $this->controller->getFieldAuditType($entity, 'never_persist');

        self::assertSame(FieldAuditTypeController::TYPE_SKIP, $result);
    }

    public function testSkipFieldFromAuditWhenEntityMethodReturnsTrue(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();

        // Set the entity to skip the 'string' field using its internal method
        $entity->setSkipFields(['string']);

        // Test method_exists($entity, 'skipFieldFromAudit') && $entity->skipFieldFromAudit($fieldName)
        $result = $this->controller->getFieldAuditType($entity, 'string');

        self::assertSame(FieldAuditTypeController::TYPE_SKIP, $result);
    }

    public function testDoNotSkipWhenEntityMethodReturnsFalse(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();

        // Don't set any skip fields, so entity's skipFieldFromAudit should return false

        // Test that the method exists but returns false, so field should not be skipped
        $result = $this->controller->getFieldAuditType($entity, 'string');

        self::assertSame(FieldAuditTypeController::TYPE_NORMAL, $result);
    }

    public function testDoNotSkipRegularField(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();

        // Test that a regular field is not skipped by the selected conditions
        $result = $this->controller->getFieldAuditType($entity, 'string');

        self::assertSame(FieldAuditTypeController::TYPE_NORMAL, $result);
    }

    public function testDoNotSkipRegularFieldWithValues(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();

        // Test that fields with values are not skipped by the selected conditions
        $result = $this->controller->getFieldAuditType($entity, 'values_integer_key');

        self::assertSame(FieldAuditTypeController::TYPE_NORMAL, $result);
    }

    public function testPasswordFieldReturnsNoValue(): void
    {
        $entity = (new ModelWithAudit($this->db))->createEntity();

        // Test that password field returns TYPE_NO_VALUE
        $result = $this->controller->getFieldAuditType($entity, 'password');

        self::assertSame(FieldAuditTypeController::TYPE_NO_VALUE, $result);
    }

}