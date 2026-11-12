<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Erreur métier de l'intégration des rapports DGI/OSAT.
 *
 * Le code est stable et destiné au frontend ; le message destiné à
 * l'utilisateur ne doit jamais contenir le corps brut du provider.
 */
class DgiReportException extends RuntimeException
{
    private string $errorCode;
    private int $httpStatus;

    public function __construct(
        string $errorCode,
        string $message,
        int $httpStatus = 502,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
