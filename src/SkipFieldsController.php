<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit;

use Atk4\Data\Exception;
use Atk4\Data\Model;

/**
 * A basic implementation for a controller which decides which fields to audit and which not. Extend to your own needs.
 */
class SkipFieldsController
{

    /**
     * @param Model $entity
     * @param string $fieldName
     * @return bool
     * @throws \Atk4\Core\Exception
     * @throws Exception
     */
    public function skipFieldFromAudit(Model $entity, string $fieldName): bool
    {
        if (
            !$entity->hasField($fieldName)
            || $fieldName === $entity->idField
            || $entity->getField($fieldName)->neverPersist === true
        ) {
            return true;
        }

        if (
            method_exists($entity, 'skipFieldFromAudit')
            && $entity->skipFieldFromAudit($fieldName)
        ) {
            return true;
        }
        return false;
    }
}
