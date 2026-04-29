<?php

namespace Magic\Service;

final class ClaudeApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        int $httpStatus,
        public readonly string $detail,
        public readonly ?int $upstreamStatus = null,
    ) {
        parent::__construct($message, $httpStatus);
    }
}
