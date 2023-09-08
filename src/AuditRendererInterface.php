<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Audit;


interface AuditRendererInterface {

    public function renderMessage(Audit $audit): string;
}