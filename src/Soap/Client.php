<?php

namespace Mpluskassa\Soap;

use GuzzleHttp\Client as HttpClient;
use DOMDocument;
use DOMElement;
use Mpluskassa\Support\ApiException;

class Client {

    private const VERSION = "1.0.0";
    private const DEFAULT_CONNECT_TIMEOUT_SECS = 30;
    private const DEFAULT_TIMEOUT_SECS = 600;

    private HttpClient $client;
    private float $duration = 0;
    private string $lastRequestXML;
    private string $lastResponseXML;
    private string $requestId;
    private float $connectTimeout;
    private float $timeout;

    public function __construct(string $apiServer, int $apiPort, string $ident, string $secret) {
        $this->client = new HttpClient([
            'base_uri' => sprintf("%s:%u", $apiServer, $apiPort),
            'query' => [
                'ident' => $ident,
                'secret' => $secret
            ],
        ]);
        $this->connectTimeout = self::DEFAULT_CONNECT_TIMEOUT_SECS;
        $this->timeout = self::DEFAULT_TIMEOUT_SECS;
    }

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
                    throw new Exception("Could not find response object", 1000);
                }
                $this->lastResponseXML = $responseXML;
                if (($parsedXML = simplexml_load_string($responseXML)) === false) {
                    throw new Exception("Could not parse XML", 2000);
                }
                if (is_array($returnValue = $parsedXML->xpath(sprintf('//%s', $responseObjectName))) && count($returnValue) && is_object(reset($returnValue))) {
                    $this->duration = microtime(true) - $startTime;
                    return json_decode(json_encode(reset($returnValue)));
                }
                throw new Exception("No valid response", 3000);
            } else {
                throw new Exception("Received a HTTP Code : " . $responseCode, 4000);
            }
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage(), $e->getCode(), $e, $this->getLastRequestId());
        }
    }

    public function getLastRequestXML() {
        return $this->lastRequestXML;
    }

    public function getLastResponseXML() {
        return $this->lastResponseXML;
    }

    public function getLastCallDurationInSeconds(): float {
        return round($this->duration, 1);
    }

    public function getLastRequestId(): string {
        return $this->requestId;
    }

    public function setConnectTimeout(float $connectTimoutInSeconds) {
        $this->connectTimeout = $connectTimoutInSeconds;
    }

    public function setTimeout(float $timeoutInSeconds) {
        $this->timeout = $timeoutInSeconds;
    }

    private function buildRequestHeaders(string $method, ?string $requestId = null): array {
        $this->requestId = $requestId ?? uniqid("mpac_");
        return [
            'User-Agent' => sprintf("%s %s", __CLASS__, self::VERSION),
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => $method,
            'X-Request-Id' => $this->requestId,
        ];
    }

    private function createXML(string $method, ?array $requestArray): string {
        $dom = new DOMDocument();
        $dom->encoding = 'utf-8';
        $dom->xmlVersion = '1.0';
        $dom->formatOutput = true;
        $soapEnv = $dom->createElement("SOAP-ENV:Envelope");
        $soapEnv->setAttribute('xmlns:SOAP-ENV', 'http://schemas.xmlsoap.org/soap/envelope/');
        $soapEnv->setAttribute('xmlns:SOAP-ENC', 'http://schemas.xmlsoap.org/soap/encoding/');
        $soapEnv->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $soapEnv->setAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $soapEnv->setAttribute('xmlns:ns1', 'urn:mplusqapi');
        $soapBody = $dom->createElement("SOAP-ENV:Body");
        $methodElement = $dom->createElement(sprintf("ns1:%s", $method));
        $this->addRequestArray($dom, $methodElement, $requestArray);
        $soapBody->appendChild($methodElement);
        $soapEnv->appendChild($soapBody);
        $dom->appendChild($soapEnv);
        return $dom->saveXML();
    }

    private function addRequestArray(DOMDocument &$dom, DOMElement &$methodElement, $requestArray): void {
        if ($requestArray !== null) {
            if (is_array($requestArray)) {
                if (!is_string(array_key_first($requestArray))) {
                    foreach ($requestArray as $key => $requestArrayItem) {
                        $this->addRequestArray($dom, $methodElement, $requestArray[$key]);
                    }
                } else {
                    foreach ($requestArray as $key => $value) {
                        if (is_array($value) && (is_array(reset($value)) && is_string(array_key_first(reset($value))) || is_string(array_key_first($value)))) {
                            $requestElement = $dom->createElement($key);
                            $methodElement->appendChild($requestElement);
                            $this->addRequestArray($dom, $requestElement, $value);
                        } else {
                            if (is_array($value)) {
                                foreach ($value as $itemValue) {
                                    $requestElement = $dom->createElement($key);
                                    $methodElement->appendChild($requestElement);
                                    $requestElement->nodeValue = $itemValue;
                                }
                            } else {
                                $requestElement = $dom->createElement($key);
                                $methodElement->appendChild($requestElement);
                                $requestElement->nodeValue = $value;
                            }
                        }
                    }
                }
            }
        }
    }

    private function getResponseObjectName(string $xml): string {
        if (($startPos = strpos($xml, "SOAP-ENV:Body")) === false) {
            return "";
        }
        if (($startPos = strpos($xml, "ns:", $startPos)) === false) {
            return "";
        }
        if (($endPos = strpos($xml, ">", $startPos)) === false) {
            return "";
        }
        return substr($xml, $startPos, $endPos - $startPos);
    }

}
