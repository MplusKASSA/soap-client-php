<?php

namespace MplusKASSA\SOAP;

use MplusKASSA\Support\ApiException;
use MplusKASSA\SOAP\ClientBase;
use GuzzleHttp\Exception\ServerException;

/**
 * MplusKASSA SOAP API client PHP
 *
 */
class Client extends ClientBase {

    /**
     * construct
     * 
     * @param string $apiServer         The URL of your api server, e.g. : https://api.mpluskassa.nl
     * @param int $apiPort              Your api port number
     * @param string $ident             Your api ident / username
     * @param string $secret            Your api secret / password 
     */
    public function __construct(string $apiServer, int $apiPort, string $ident, string $secret) {
        parent::__construct($apiServer, $apiPort, $ident, $secret);
    }

    /**
     * execute
     * Execute an API method
     * 
     * @param string $method            The api method you wish to execute, e.g. getProducts
     * @param array $requestArray       Optional : The request array with parameters if applicable for the method
     * @param string $requestId         Optional : The requestId, here you can add an reference for easy debugging
     * @return object                   Response object
     */
    public function execute(string $method, ?array $requestArray = null, ?string $requestId = null): object {
        $this->lastRequestXML = "";
        $this->lastResponseXML = "";
        $startTime = microtime(true);
        $requestXML = $this->createXML($method, $requestArray);
        $this->lastRequestXML = $requestXML;
        try {
            $response = $this->client->post("/", [
                'body' => $requestXML,
                'headers' => $this->buildRequestHeaders($method, $requestId),
                'connect_timeout' => $this->connectTimeout,
                'timeout' => $this->timeout,
            ]);
            if (($responseCode = $response->getStatusCode()) === 200) {
                $responseXML = $response->getBody()->getContents();
                if (empty($responseObjectName = $this->getResponseObjectName($responseXML))) {
                    throw new \Exception("Could not find response object", 1000);
                }
                $this->lastResponseXML = $responseXML;
                $this->filterNamespace($responseXML);
                if (($parsedXML = simplexml_load_string($responseXML)) === false) {
                    throw new \Exception("Could not parse XML", 2000);
                }
                if (is_array($returnValue = $parsedXML->xpath(sprintf('//%s', $responseObjectName))) && count($returnValue) && is_object(reset($returnValue))) {
                    $this->duration = microtime(true) - $startTime;
                    $returnValue = json_decode(json_encode(reset($returnValue)));
                    $this->standardizeResult($returnValue);
                    return $returnValue;
                }
                throw new \Exception("No valid response", 3000);
            } else {
                throw new \Exception("Received a HTTP Code : " . $responseCode, 4000);
            }
        } catch (ServerException $e) {
            $this->setSoapFault($e);
            if (!is_null($soapFault = $this->getSoapFault())) {
                $errorMessage = sprintf("SoapFault : %s", $soapFault);
            } else {
                $errorMessage = sprintf("ServerException : %s", $e->getMessage());
            }
            throw new ApiException($errorMessage, $e->getCode(), $e, $this->getLastRequestId());
        }
    }

    /**
     * getLastRequestXML
     * Get last XML of the request
     * @return string Last request XML
     */
    public function getLastRequestXML(): string {
        return $this->lastRequestXML;
    }

    /**
     * getLastResponseXML
     * Get last XML of the response
     * @return string Last response XML
     */
    public function getLastResponseXML(): string {
        return $this->lastResponseXML;
    }

    /**
     * getLastCallDurationInSeconds
     * Retrieve the duration in seconds of the last call
     * @return float Last call duration
     */
    public function getLastCallDurationInSeconds(): float {
        return round($this->duration, 1);
    }

    /**
     * getLastRequestId
     * Get last request Id of the request
     * @return string Last request Id
     */
    public function getLastRequestId(): string {
        return $this->requestId;
    }

    /**
     * setConnectTimeout
     * Set connection timeout
     * @return void
     */
    public function setConnectTimeout(float $connectTimoutInSeconds): void {
        $this->connectTimeout = $connectTimoutInSeconds;
    }

    /**
     * setTimeout
     * Set the timeout
     * @return void
     */
    public function setTimeout(float $timeoutInSeconds): void {
        $this->timeout = $timeoutInSeconds;
    }

    /**
     * getSoapFault
     * Get soap fault if any
     * @return ?string Soap fault
     */
    public function getSoapFault(): ?string {
        return $this->soapFault;
    }

    /**
     * prepareRequest
     * Prepare request to use for the execute. If the request contains objects,
     * these will be converted to arrays. Also list elements can be added here.
     * If renameKeys are present, the keys are first renamed and after that list elements are added on the new keys.
     * 
     * @param mixed $request            The request array/object. Passed by reference, so will be modified 
     * @param array $addListElements    Key => value array where the key is the name of the 
     * list : e.g. 'lineList' and the value is the element to add : e.g. 'line'.
     * This will result in all array items being wrapped in a line element.
     * @param array $renameKeys         Key => value array where the key is the sourceName and the value is the targetName
     * @return void
     */
    public function prepareRequest(&$request, array $addListElements = [], array $renameKeys = []): void {
        $request = (array) json_decode(json_encode($request), true); // Convert objects to array
        if (count($renameKeys)) {
            $this->renameKeys($request, $renameKeys);
        }
        if (count($addListElements)) {
            $this->addListElementToRequest($request, $addListElements);
        }  
    }

}
