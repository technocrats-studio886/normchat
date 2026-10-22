<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Request AI ditolak sebelum memanggil provider karena perkiraan biayanya
 * melewati batas per request atau saldo grup. Pesannya ditampilkan ke grup.
 */
class InsufficientBudgetException extends RuntimeException
{
}
