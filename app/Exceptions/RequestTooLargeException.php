<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pesan yang men-tag Normi (beserta dokumen/gambarnya) sudah melewati batas token input per permintaan
 * walau seluruh konteks riwayat dibuang. Ditolak sebelum NC maupun saldo OpenAI ditahan.
 */
class RequestTooLargeException extends RuntimeException
{
    public const USER_MESSAGE = 'Pesan atau lampirannya terlalu panjang untuk dijawab Normi. Persingkat pesannya atau kirim lampiran yang lebih kecil.';

    public function __construct()
    {
        parent::__construct(self::USER_MESSAGE);
    }
}
