<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Audit\Tests\Testclasses;

use PhilippR\Atk4\Audit\Audit;
use PhilippR\Atk4\Audit\AuditRendererInterface;

class AuditRendererDemo implements AuditRendererInterface
{

    public function renderMessage(Audit $audit): string
    {
        return "Demo";
    }
}