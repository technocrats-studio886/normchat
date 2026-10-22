<?php

namespace App\Services\Ai\Responders;

use App\Exceptions\InsufficientBudgetException;
use App\Services\Ai\AiReply;
use App\Services\Ai\AiTask;

/** Satu jenis balasan AI (chat, dokumen, gambar). */
interface AiResponder
{
    /**
     * Tahan Normkredit lewat $task sebelum memanggil API, catat biaya nyata ke $task->meter.
     *
     * @throws InsufficientBudgetException
     */
    public function respond(AiTask $task): AiReply;
}
