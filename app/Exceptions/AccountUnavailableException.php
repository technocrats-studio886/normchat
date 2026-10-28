<?php

namespace App\Exceptions;

use RuntimeException;

/** Akun yang dihapus atau diblokir superadmin tidak bisa masuk; pesannya aman ditampilkan. */
class AccountUnavailableException extends RuntimeException
{
}
