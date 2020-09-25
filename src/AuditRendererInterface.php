<?php

declare(strict_types=1);

namespace auditforatk;


interface AuditRendererInterface {

    public function renderMessage(Audit $audit): string;
}