<?php

namespace Mpluskassa\ApiClient;

class ApiException extends \Exception {
    private string $requestId;
    
    public function __construct(string $message = "", int $code = 0, \Throwable $previous = NULL, string $requestId = "") {
        parent::__construct($message, $code, $previous);
        $this->requestId = $requestId;
    }
    
    public function getRequestId() {
        return $this->requestId;
    }
}
