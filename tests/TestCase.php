<?php

declare(strict_types=1);

namespace auditforatk\tests;

use atk4\data\Persistence;
use atk4\schema\Migration;

class TestCase extends \atk4\core\AtkPhpunit\TestCase {

    protected $sqlitePersistenceModels = [];


    protected function getSqliteTestPersistence(array $additionalClasses = []): Persistence {
        $allClasses = array_merge($this->sqlitePersistenceModels, $additionalClasses);
        $persistence = Persistence::connect('sqlite::memory:');
        foreach($allClasses as $className) {
            $model = new $className($persistence);
            Migration::of($model)->drop()->create();
        }

        return $persistence;
    }
}