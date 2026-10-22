<?php

namespace App\Exceptions;

use RuntimeException;

/** Checkout Midtrans tidak bisa dimulai. Pesannya aman ditampilkan ke user. */
class CheckoutFailedException extends RuntimeException
{
}
