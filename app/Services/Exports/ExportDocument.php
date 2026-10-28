<?php

namespace App\Services\Exports;

use Carbon\CarbonImmutable;

/** Isi satu file ekspor: kepala dokumen dan daftar pesannya. */
final class ExportDocument
{
    /** @param list<ExportEntry> $entries */
    public function __construct(
        public readonly string $groupName,
        public readonly string $exporterName,
        public readonly CarbonImmutable $exportedAt,
        public readonly string $rangeLabel,
        public readonly array $entries,
    ) {
    }

    public function count(): int
    {
        return count($this->entries);
    }
}
