# Mplus api client PHP

---

This api client is intended to be lightweight and fast so no wrappers, wsdl or soapclient is used.
Use your api documentation for reference : https://api.mpluskassa.nl:YOUR_API_PORT/?docs

---

Usage :
```
<?php
$apiServer = "https://ap.mpluskassa.nl";
$apiPort = YOUR_API_PORT;
$apiIdent = YOUR_API_IDENT;
$apiSecret = YOUR_API_SECRET;

$client = new ApiClient($apiServer, $apiPort, $apiIdent, $apiSecret);

$requestObj = (object) [                 // Build the request object as documented in the documentation for the api method
            'request' => [
                (object) [
                    'syncMarker' => 0,
                    'syncMarkerLimit' => 10,
                    'groupNumbers' => [
                        1,
                        2,
                        3,
                        9,
                    ]
                ]
            ]
];
$method = "getArticleGroupChanges";                                     // <-- This is the api method, get from documentation after "Input:" for the api method
$responseObjectName = "GetArticleGroupChangesResponse"; // <-- This is the response name, get from the documentation after "Output:" for the api method

try {
    $response = $client->execute($method, $responseObjectName, $requestObj);    // <-- execute the request
    print_r($response);
    foreach($response->changedArticleGroupList->changedArticleGroups as $articleGroup) {    // <-- access the response as an object
        print_r($articleGroup->name);
    }
    echo sprintf("Duration : %.1f seconds\n", $client->getLastCallDurationInSeconds());             // <-- retrieve last call duration
} catch (\Exception $e) {
    echo "Exception : " . $e->getMessage() . "\n";                              // <-- get exception message
    echo "Request :\n" . $client->getLastRequestXML() . "\n";           // <-- get last request XML
    echo "Response :\n" . $client->getLastResponseXML() . "\n";     // <-- get last response XML
}
```