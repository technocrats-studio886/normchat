<?php

namespace App\Services\Exports;

interface ChatExporter
{
    public function extension(): string;

    /** @param list<ExportEntry> $entries */
    public function render(string $groupName, array $entries): string;
}
