<?php

namespace App\Services\Ai\Files;

use RuntimeException;

final class DocumentPageLimit extends RuntimeException
{
    public function __construct(public readonly int $pages)
    {
        parent::__construct("PDF ini berisi {$pages} halaman. Pilih maksimal 35 halaman untuk dibaca.");
    }
}
