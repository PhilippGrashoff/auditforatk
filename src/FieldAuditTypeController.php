<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit;

use Atk4\Data\Exception;
use Atk4\Data\Field\PasswordField;
use Atk4\Data\Model;

/**
 * A basic implementation for a controller which decides which fields
 * - to audit normally
 * - to audit without values (Password fields)
 * - to not audit at all. Extend to your own needs.
 */
class FieldAuditTypeController
{

    public const string TYPE_NORMAL = 'NORMAL';
    public const string TYPE_SKIP = 'SKIP';
    public const string TYPE_NO_VALUE = 'NO_VALUE';

    /**
     * @param Model $entity
     * @param string $fieldName
     * @return string
     * @throws Exception
     * @throws \Atk4\Core\Exception
     */
    public function getFieldAuditType(Model $entity, string $fieldName): string
    {
        if (
            !$entity->hasField($fieldName)
            || $fieldName === $entity->idField
            || $entity->getField($fieldName)->neverPersist === true
        ) {
            return self::TYPE_SKIP;
        }

        if (
            method_exists($entity, 'skipFieldFromAudit')
            && $entity->skipFieldFromAudit($fieldName)
        ) {
            return self::TYPE_SKIP;
        }

        if ($entity->getField($fieldName) instanceof PasswordField) {
            return self::TYPE_NO_VALUE;
        }

        return self::TYPE_NORMAL;
    }
}
