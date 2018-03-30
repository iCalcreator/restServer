restServer - rest services made easy
====================================

restServer provides

 * a rest server and API for your application

The library is based on

 * PSR HTTP message and util interfaces
 * zend-diactoros - server implementation
 * FastRoute - Fast request router for PHP

Install
-------

To install with composer:

```sh
composer require kigkonsult/restserver
```
Requires PHP 5.6, 7.*

Usage
-----

Example usage:

```php
<?php

require '/path/to/vendor/autoload.php';

// Implement an application rest service operation entry
// (or any callable with the same interface)
$callback = function(
    ServerRequestInterface $request,
    ResponseInterface      $response
) {
    return $response->withRawBody( [ 'Hello' => 'world' ] );
};

// Set up the rest service definition (method, uri and callback)
$restGetServiceDef = [
    RestServer::METHOD   => RequestMethodHandler::METHOD_GET,
    RestServer::URI      => '/',
    RestServer::CALLBACK => $callback
];

// Attach the rest service(s) and fire of the server
(new RestServer())->attachRestService( $restGetServiceDef )->run();
```
More example usage:

```php
<?php

require '/path/to/vendor/autoload.php';

$RestServer = new RestServer();

$attachRestServiceCallback = $RestServer->getAttachRestServiceCallback();

class applicationClass2
{
    public function registerAsRestService(
        $callback
    ) {
        $callback(
            RequestMethodHandler::METHOD_GET,
            '/',
            [$this, 'action']
        );
    }

    public function action(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        return $response->withRawBody( ['msg' => 'Hello world'] );
    }
}
$applicationClass2 = new applicationClass2();
$applicationClass2->registerAsRestService( $attachRestServiceCallback );

$RestServer->run();
```

Rest service definition
----------------------

You have to implement one or more rest service (callable) entries for your application logic.
Each entry with one or more http request methods and a (single) uri (ex '/') form a service definition. The service definitions, attached to restServer,  are interfaces to your application logic.

Handlers
--------

restServer have builtin handlers managing messages serializing, en-/decoding
and Cross-Origin Resource Sharing.
As well as rest service definitions, you can attach custom
request message handler(s), invoked before any operation callback.

Documentation
-------------
In the restServer package docs folder are found
 - summary and supplementary documentation
 - demo applications and service definitions
 - demo handlers
 - more examples


For restServer issues, use [github].
Due to the restServer development status (ver 0.8.0), review reports are appreciated!

Credits and base software information
-------------------------------------

 * [PSR HTTP message interfaces]
 * [PSR HTTP message Util interfaces]
 * [zend-diactoros] server implementation
 * [FastRoute] uri routing ('/' etc)

Built status
------------
Dev 0.8.0

[PSR HTTP message interfaces]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md
[PSR HTTP message Util interfaces]: https://github.com/php-fig/http-message-util
[zend-diactoros]: https://github.com/zendframework/zend-diactoros
[FastRoute]: https://github.com/nikic/FastRoute
[github]: https://github.com/iCalcreator/restServer/issues