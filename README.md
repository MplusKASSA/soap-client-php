# MplusKASSA SOAP API Client for PHP

This SOAP API Client is intended to be lightweight and fast, so no wrappers, WSDL-file or SoapClient is used.

Use your personal MplusKASSA API documentation for reference: https://api.mpluskassa.nl:YOUR_API_PORT/?docs

Please note that all "List" elements will be filtered and standardized by the client. 
E.g. GetProductsResponse has $response->productList which contains an array with all products and loses the $response->productList->product property.

---

Usage:

```php
<?php

use MplusKASSA\SOAP\Client;
use MplusKASSA\Support\ApiException;

$apiServer = "https://api.mpluskassa.nl";
$apiPort = YOUR_API_PORT;
$apiIdent = YOUR_API_IDENT;
$apiSecret = YOUR_API_SECRET;

$client = new Client($apiServer, $apiPort, $apiIdent, $apiSecret);

$apiMethod = "getArticleGroupChanges"; // <-- This is the API method, get this from documentation after "Input:" for the desired call

// Build the request object as documented in your personal documentation for the specific API method
$requestArray = [
    'request' => [
        [
            'syncMarker' => 0,
            'syncMarkerLimit' => 10,
            'groupNumbers' => [
                1, 2, 3, 9,
            ]
        ]
    ]
];

try {
    $response = $client->execute($apiMethod, $requestArray); // <-- execute the request
    print_r($response);
    foreach($response->changedArticleGroupList->changedArticleGroups as $articleGroup) { // <-- access the response as an object
        print_r($articleGroup->name);
    }
    echo sprintf("Duration : %.1f seconds\n", $client->getLastCallDurationInSeconds()); // <-- retrieve last call duration
} catch (ApiException $e) {
    echo "Exception : " . $e->getMessage() . "\n"; // <-- get exception message
    echo "Code : ".$e->getCode()."\n"; // <-- get last exception code
    echo "Trace : ".print_r($e->getTrace(), true)."\n"; //<-- get backtrace
    echo "RequestId : ".$e->getRequestId()."\n"; //<-- get request id
    echo "Request :\n" . $client->getLastRequestXML() . "\n"; // <-- get last request XML
    echo "Response :\n" . $client->getLastResponseXML() . "\n"; // <-- get last response XML
}
```