<?php

namespace FediversePlugin\API;

use Exception;

/**
 * APIException is a custom exception class for handling
 * errors related to API requests and responses.
 */
class APIException extends Exception
{
    protected array $responseData;

    /**
     * Create a new APIException.
     *
     * @param string $message        Human-readable error message.
     * @param int $code              Optional HTTP or application-level error code.
     * @param array $responseData    Optional full response or payload from the API.
     */
    public function __construct(string $message, int $code = 0, array $responseData = [])
    {
        parent::__construct($message, $code);
        $this->responseData = $responseData;
    }

    /**
     * Get the full response data associated with the error.
     *
     * @return array
     */
    public function getResponseData(): array
    {
        return $this->responseData;
    }
}
