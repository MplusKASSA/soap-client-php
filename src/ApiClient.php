<?php

namespace Mpluskassa\ApiClient;

use GuzzleHttp\Client;
use SimpleXMLElement;
use DOMDocument;
use DOMElement;
use DOMAttr;

class ApiClient {

    private const VERSION = "1.0.0";

    private Client $client;
    private float $duration = 0;

    public function __construct(string $apiServer, int $apiPort, string $ident, string $secret) {
        $this->client = new Client([
            'base_uri' => sprintf("%s:%u", $apiServer, $apiPort),
            'headers' => [
                'User-Agent' => sprintf("%s %s", __CLASS__, self::VERSION),
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => ''
            ],
            'query' => [
                'ident' => $ident,
                'secret' => $secret
            ]
        ]);
    }

    public function execute(string $method, ?object $requestObject = null): SimpleXMLElement {
        $startTime = microtime(true);
        $requestXML = $this->createXML($method, $requestObject);
        die(var_export($requestXML, true));
        try {
            $response = $this->client->post("/", ['body' => $requestXML]);
            if (($responseCode = $response->getStatusCode()) === 200) {
                $responseXML = $response->getBody()->getContents();
                echo "Response xml : " . $responseXML;
                if (($parsedXML = simplexml_load_string($responseXML)) === false) {
                    throw new \Exception("Could not parse XML");
                }
                if (is_array($returnValue = $parsedXML->xpath(sprintf('//ns:%sResponse', ucfirst($method)))) && count($returnValue) && is_object(reset($returnValue))) {
                    $this->duration = microtime(true) - $startTime;
                    return reset($returnValue);
                }
                throw new \Exception("No valid response");
            } else {
                throw new \Exception("Received a HTTP Code : " . $responseCode);
            }
        } catch (\Exception $e) {
            throw new Exception('Exception:' . $e->getMessage());
        }
    }

    public function getLastCallDurationInSeconds(): float {
        return round($this->duration, 1);
    }

    private function createXML(string $method, ?object $requestObject): string {
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
        $this->addRequestObject($dom, $methodElement, $requestObject);
        $soapBody->appendChild($methodElement);
        $soapEnv->appendChild($soapBody);
        $dom->appendChild($soapEnv);
        return $dom->saveXML();
    }

    private function addRequestObject(DOMDocument &$dom, DOMElement &$methodElement, $requestObject): void {
        if ($requestObject !== null) {
//            die(print_r($requestObject, true));
            if(is_array($requestObject)) {
                foreach($requestObject as $key => $requestObjectItem) {
                    $this->addRequestObject($dom, $methodElement, $requestObject[$key]);
                }
            } elseif(is_object($requestObject)) {
                foreach($requestObject as $key => $value) {
                    $requestElement = $dom->createElement($key);
                    $methodElement->appendChild($requestElement);
                    if(is_array($value) || is_object($value)) {
                        $this->addRequestObject($dom, $requestElement, $value);
                    } else {
                        
                    }
                }
            }
        }
    }

}
