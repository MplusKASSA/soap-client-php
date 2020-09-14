<?php

namespace Mpluskassa\ApiClient;

use GuzzleHttp\Client;
use SimpleXMLElement;
use DOMDocument;
use DOMAttr;

class ApiClient {

    private const VERSION = "1.0.0";
    private const SOAP_HEADER = '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Body>';
    private const SOAP_FOOTER = '</SOAP-ENV:Body></SOAP-ENV:Envelope>';

    private Client $client;
    private string $apiUrl;
    private string $query;

    public function __construct(string $apiServer, int $apiPort, string $ident, string $secret) {
        $this->apiUrl = sprintf("%s:%u", $apiServer, $apiPort);
        $this->client = new Client([
            'base_uri' => $this->apiUrl,
//            'auth' => [
//                $ident,
//                $secret
//            ],
            'headers' => [
                'User-Agent' => sprintf("%s %s", __CLASS__, self::VERSION),
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => ''
            ],
//            'query' => [
//                'ident' => $ident,
//                'secret' => $secret
//            ]
        ]);
    }

    public function execute(string $method, $data = null) {

        //$cleanXml = $dom->ownerDocument->saveXML($dom->ownerDocument->documentElement);
//        $xml = new SimpleXMLElement(sprintf('<ns1:%s xmlns:ns1="urn:mplusqapi"></ns1:%s>', $method, $method), 0, false, "ns1='urn:mplusqapi'");
//        $customXML = new SimpleXMLElement($xml->asXML());
//        $dom = dom_import_simplexml($customXML);
//        $cleanXml = $dom->ownerDocument->saveXML($dom->ownerDocument->documentElement);
//        $xmlRequest = sprintf("%s%s%s", self::SOAP_HEADER, $cleanXml, self::SOAP_FOOTER);

        $xml = $this->createXML($method, $data);
        die(print_r($xml, true));
        
        
        try {
            $response = $this->client->post(
                    "/",
                    [
                        'body' => $xml,
                    ]
            );

//            var_dump($response);
            if ($response->getStatusCode() === 200) {
                // Success!
                $xmlResponse = simplexml_load_string($response->getBody()); // Convert response into object for easier parsing
                echo "Response : " . var_export($xmlResponse, true);
            } else {
                echo 'Response Failure !!!';
            }
        } catch (\Exception $e) {
            echo 'Exception:' . $e->getMessage();
        }
    }

    private function createXML($method, $data) {
        $dom = new DOMDocument();
        $dom->encoding = 'utf-8';
        $dom->xmlVersion = '1.0';
        $dom->formatOutput = true;
        $soapEnv = $dom->createElement("SOAP-ENV:Envelope");
        $soapEnv->setAttribute('xmlns:SOAP-ENV', 'http://schemas.xmlsoap.org/soap/envelope/');
        $soapEnv->setAttribute('xmlns:SOAP-ENC', 'http://schemas.xmlsoap.org/soap/encoding/');
        $soapEnv->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $soapEnv->setAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $soapEnv->setAttribute('xmlns:ns', 'urn:mplusqapi');
        $soapBody = $dom->createElement("SOAP-ENV:BODY");
        $methodElement = $dom->createElement(sprintf("ns1:%s", $method));
        $soapBody->appendChild($methodElement);
        $soapEnv->appendChild($soapBody);
        $dom->appendChild($soapEnv);
        return $dom->saveXML();
    }

}
