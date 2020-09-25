<?php

declare(strict_types=1);

namespace auditforatk\tests\testclasses;

use auditforatk\Audit;
use auditforatk\AuditRendererInterface;

class AuditRendererDemo implements AuditRendererInterface {

    public function renderMessage(Audit $audit): string
    {
        return "Demo";
    }
}