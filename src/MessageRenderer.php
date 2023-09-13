<?php declare(strict_types=1);

namespace PhilippR\Atk4\Audit;

use Atk4\Data\Model;

class MessageRenderer {


    public function renderCreatedMessage(Audit $audit, Model $entity): string {
        return 'created ' . $entity->getModelCaption();
    }

    public function renderDeletedMessage(Audit $audit, Model $entity): string {
        return 'deleted ' . $entity->getModelCaption();
    }

    public function renderFieldAudit(Audit $audit): string {
        return '';
    }

    public function renderHasOneAudit(Audit $audit, Model $entity, Model $newRefModel, ?Model $oldRefModel): string {
        if($oldRefModel) {
            return 'changed "fieldName" from "' . $newRefModel->getTitle() . '" to "' . $oldRefModel->getTitle() . '",';
        }
        else {
            return 'set "fieldName" to "' . $oldRefModel->getTitle() .'".';
        }
    }
}
