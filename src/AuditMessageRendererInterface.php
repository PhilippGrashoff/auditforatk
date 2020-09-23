<?php

declare(strict_types=1);

namespace auditforatk;


interface AuditMessageRendererInterface {

    public function renderMessage(Audit $audit): string;
}