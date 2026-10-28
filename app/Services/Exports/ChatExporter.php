<?php

namespace App\Services\Exports;

interface ChatExporter
{
    public function extension(): string;

    /** @return string isi berkas */
    public function render(ExportDocument $document): string;
}
