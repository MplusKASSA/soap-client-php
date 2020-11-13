<?php

namespace MplusKASSA\SOAP;

use DOMDocument;
use DOMElement;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ServerException;

/**
 * MplusKASSA SOAP API client PHP Client Base class
 *
 */
abstract class ClientBase {

    protected const VERSION = "1.0.0";
    protected const DEFAULT_CONNECT_TIMEOUT_SECS = 30;
    protected const DEFAULT_TIMEOUT_SECS = 600;

    protected HttpClient $client;
    protected float $duration = 0;
    protected string $lastRequestXML;
    protected string $lastResponseXML;
    protected string $requestId = "";
    protected float $connectTimeout;
    protected float $timeout;
    protected ?string $soapFault = null;

    //Key is (part of) the list name
    //If value is true : first element will be removed from list
    //If value is false: first element will not be removed from list
    private const LIST_IDENTIFIERS = [
        'List' => true,
        'Ids' => false,
        'branches' => true,
        'licensedBranches' => true,
        'workplaces' => true,
        'Numbers' => false,
        'subGroups' => false,
        'serviceIpAddresses' => false,
        'subTables' => true,
        'articleBarcodes' => true,
        'articleStocks' => false,
        'articleStockHistory' => false,
        'values' => false,
        'availableValues' => false,
        'entries' => false,
    ];

    /**
     * construct
     * 
     * @param string $apiServer         The URL of your api server, e.g. : https://api.mpluskassa.nl
     * @param int $apiPort              Your api port number
     * @param string $ident             Your api ident / username
     * @param string $secret            Your api secret / password 
     */
    public function __construct(string $apiServer, int $apiPort, string $ident, string $secret) {
        if (0 !== stripos($apiServer, 'http')) {
            $apiServer = 'https://' . $apiServer;
        }
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

    /**
     * addListElementsToRequest
     * Wraps list elements in an element. This is required for the SOAP API
     * 
     * @param array $request            The request array. Passed by reference, so will be modified 
     * @param array $addListElements    Key => value array where the key is the name of the 
     * list : e.g. 'lineList' and the value is the element to add : e.g. 'line'.
     * This will result in all array items being wrapped in a line element.
     * @return void
     */
    protected function addListElementToRequest(array &$requestArray, array $addListElements): void {
        foreach ($requestArray as $idx => $value) {
            if (is_array($value)) {
                $this->addListElementToRequest($requestArray[$idx], $addListElements);
            }
            foreach ($addListElements as $listName => $element) {
                if ($idx === $listName) {
                    if (is_array($requestArray[$idx])) {
                        $saveRequest = $requestArray[$idx];
                        $requestArray[$idx] = [];
                        foreach ($saveRequest as $elementKey => $elementValue) {
                            $requestArray[$idx][] = [
                                $element => $elementValue
                            ];
                        }
                    }
                    break;
                }
            }
        }
    }

    /**
     * renameKeys
     * Rename keys in the request array
     * 
     * @param array $request            The request array. Passed by reference, so will be modified 
     * @param array $renameKeys         Key => value array where the key is the sourceName and the value is the targetName
     * @return void
     */
    protected function renameKeys(array &$requestArray, array $renameKeys): void {
        foreach ($requestArray as $idx => $value) {
            if (is_array($value)) {
                $this->renameKeys($requestArray[$idx], $renameKeys);
            }
            foreach ($renameKeys as $sourceName => $targetName) {
                if ($idx === $sourceName) {
                    $requestArray[$targetName] = $requestArray[$sourceName];
                    unset($requestArray[$sourceName]);
                    break;
                }
            }
        }
    }

    /**
     * convertBoolValues
     * Convert bool values to int values. Otherwise false values are empty.
     * 
     * @param array $request            The request array. Passed by reference, so will be modified 
     * @return void
     */
    protected function convertBoolValues(array &$requestArray): void {
        foreach ($requestArray as $idx => $value) {
            if (is_array($value)) {
                $this->convertBoolValues($requestArray[$idx]);
            } else {
                if (is_bool($value)) {
                    $requestArray[$idx] = intval($value);
                }
            }
        }
    }

    /**
     * createXML
     * Create XML request for the method and requestArray
     * 
     * @param string $method            The request array/object. Passed by reference, so will be modified 
     * @param array $requestArray       Optional : The request array
     * @return string XML request
     */
    protected function createXML(string $method, ?array $requestArray): string {
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
        $methodElement = $dom->createElement('ns1:' . $method);
        $this->addRequestArray($dom, $methodElement, $requestArray);
        $soapBody->appendChild($methodElement);
        $soapEnv->appendChild($soapBody);
        $dom->appendChild($soapEnv);
        return $dom->saveXML();
    }

    /**
     * addRequestArray
     * Add request array to DOMDocument XML.
     * 
     * @param DOMDocument $dom          The DOMDocument, passed by reference, so will be modified
     * @param DOMElement $methodElement The DOMElement, passed by reference, so will be modified
     * @param mixed $requestArray       The request array
     * @return void
     */
    protected function addRequestArray(DOMDocument &$dom, DOMElement &$methodElement, $requestArray): void {
        if ($requestArray !== null) {
            if (is_array($requestArray)) {
                if (!is_string(array_key_first($requestArray))) {
                    foreach ($requestArray as $key => $requestArrayItem) {
                        $this->addRequestArray($dom, $methodElement, $requestArray[$key]);
                    }
                } else {
                    foreach ($requestArray as $key => $value) {
                        if (is_array($value) && (is_array(reset($value)) && is_string(array_key_first(reset($value))) || is_string(array_key_first($value)))) {
                            $requestElement = $dom->createElement('ns1:' . $key);
                            $methodElement->appendChild($requestElement);
                            $this->addRequestArray($dom, $requestElement, $value);
                        } else {
                            if (is_array($value)) {
                                foreach ($value as $itemValue) {
                                    $requestElement = $dom->createElement('ns1:' . $key);
                                    $methodElement->appendChild($requestElement);
                                    $requestElement->nodeValue = $itemValue;
                                }
                            } else {
                                $requestElement = $dom->createElement('ns1:' . $key);
                                $methodElement->appendChild($requestElement);
                                $requestElement->nodeValue = $value;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * getResponseObjectName
     * Retrieve the response object name which contains the actual response from XML.
     * 
     * @param string $xml       The response XML
     * @return string The response object name
     */
    protected function getResponseObjectName(string $xml): string {
        if (($startPos = strpos($xml, "SOAP-ENV:Body")) === false) {
            return "";
        }
        if (($startPos = strpos($xml, "ns:", $startPos)) === false) {
            return "";
        }
        if (($endPos = strpos($xml, ">", $startPos)) === false) {
            return "";
        }
        $startPos += strlen("ns:");
        return substr($xml, $startPos, $endPos - $startPos);
    }

    /**
     * standardizeResult
     * StandardizeResult will :
     * (*1). Remove list elements : e.g. productList->product[] -> productList[]
     * (*2). If properties are empty, they contain an empty object. This will be replaced by null
     * (*3). Make sure that lists are an array
     * (*4). Convert 'true'/'false' text values to int 1/0
     * 
     * @param mixed $soapResult     Soap result
     * @return void
     */
    protected function standardizeResult(&$soapResult): void {
        if (is_object($soapResult)) {
            foreach ($soapResult as $key => $value) {
                if (is_object($value)) {
                    $shouldRemoveListElement = $this->shouldRemoveListElement($key);

                    if (is_null($listElement = $this->getFirstProperty($soapResult->$key))) {
                        if (!is_null($shouldRemoveListElement)) {
                            $soapResult->$key = [];     // Create empty array for list (*3)
                        } else {
                            $soapResult->$key = null;   // Empty object does not make sense, replace by null (*2)
                        }
                    } else {
                        if ($shouldRemoveListElement) {
                            if (!is_null($listElement) && isset($soapResult->$key->$listElement)) {
                                $soapResult->$key = $soapResult->$key->$listElement;    // Remove list element (*1)
                            }
                        }
                        if (!is_null($shouldRemoveListElement)) {
                            if (!is_array($soapResult->$key)) {
                                if (!empty($soapResult->$key)) {
                                    $soapResult->$key = [$soapResult->$key];    // Create array for list (*3)
                                } else {
                                    $soapResult->$key = []; // Create empty array for list (*3)
                                }
                            }
                        }
                        $this->standardizeResult($soapResult->$key);
                    }
                } elseif (is_array($value)) {
                    $this->standardizeResult($soapResult->$key);
                } elseif (is_string($value) && in_array(strtolower($value), ['true', 'false'])) {   // Convert 'true'/'false' text values to int 1/0 (*4)
                    $soapResult->$key = strtolower($value) == 'true' ? 1 : 0;
                } elseif (!is_null($this->shouldRemoveListElement($key))) {
                    if (!empty($value)) {
                        $soapResult->$key = [$soapResult->$key];    // Create array for list (*3)
                    } else {
                        $soapResult->$key = []; // Create empty array for list (*3)
                    }
                }
            }
        } elseif (is_array($soapResult)) {
            foreach ($soapResult as $key => $value) {
                $this->standardizeResult($soapResult[$key]);
            }
        }
    }

    /**
     * getFirstProperty
     * Get the first(and only) property of an object
     * 
     * @param object $object       The object to find the property in
     * @return mixed The first property or null
     */
    protected function getFirstProperty(object $object): ?string {
        foreach ($object as $elementName => $elementValue) {
            return $elementName;
        }
        return null;
    }

    /**
     * buildRequestHeaders
     * Builds the request headers necessary for the SOAP call
     * 
     * @param string $method            The method
     * @param string $requestId         Optional : The requestId, here you can add an reference for easy debugging
     * @return array Request headers
     */
    protected function buildRequestHeaders(string $method, ?string $requestId = null): array {
        $this->requestId = $requestId ?? uniqid("mpac_");
        return [
            'User-Agent' => sprintf("%s %s", __CLASS__, self::VERSION),
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => $method,
            'X-Request-Id' => $this->requestId,
        ];
    }

    /**
     * shouldRemoveListElement
     * Get the list identifier 
     * 
     * @param mixed $identifier     Identifier property / key
     * @return optional bool True if list element should be removed, 
     * false if not, null if not a list element.
     */
    private function shouldRemoveListElement($identifier): ?bool {
        if (is_string($identifier)) {
            foreach (self::LIST_IDENTIFIERS as $listIdentifier => $removeElement) {
                if (strpos($identifier, $listIdentifier) !== false) {
                    return $removeElement;
                }
            }
        }
        return null;
    }

    /**
     * setSoapFault
     * Try to set soap fault from body if present
     * 
     * @param ServerException $e     ServerException
     */
    protected function setSoapFault(ServerException $e): void {
        $this->soapFault = null;
        $bodyXML = $e->getResponse()->getBody()->getContents();
        if (($parsedXML = simplexml_load_string($bodyXML)) !== false) {
            if (is_array($returnValue = $parsedXML->xpath('//faultstring')) && count($returnValue) && is_object(reset($returnValue))) {
                $returnValue = json_decode(json_encode(reset($returnValue)));
                $this->soapFault = reset($returnValue);
            }
        }
    }

    /**
     * filterNamepsace
     * Filter namespace elements before using xpath to recursively retrieve
     * elements.
     * @param string $xml   XML to filter, passed by reference
     */
    protected function filterNamespace(string &$xml): void {
        $xml = str_replace('<ns:', '<', $xml);
        $xml = str_replace('</ns:', '</', $xml);
    }

}
