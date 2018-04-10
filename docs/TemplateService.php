<?php
/**
 * restServer, a PSR HTTP Message rest server implementation
 *
 * This file is a part of restServer.
 *
 * Copyright 2018 Kjell-Inge Gustafsson, kigkonsult, All rights reserved
 * Link      http://kigkonsult.se/restServer/index.php
 * Version   0.9.23
 * License   Subject matter of licence is the software restServer.
 *           The above copyright, link, package and version notices and
 *           this licence notice shall be included in all copies or
 *           substantial portions of the restServer.
 *           restServer can be used either under the terms of
 *           a proprietary license, available at <https://kigkonsult.se/>
 *           or the GNU Affero General Public License, version 3:
 *           restServer is free software: you can redistribute it and/or
 *           modify it under the terms of the GNU Affero General Public License
 *           as published by the Free Software Foundation, either version 3 of
 *           the License, or (at your option) any later version.
 *           restServer is distributed in the hope that it will be useful,
 *           but WITHOUT ANY WARRANTY; without even the implied warranty of
 *           MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *           GNU Affero General Public License for more details.
 *           You should have received a copy of the GNU Affero General Public
 *           License along with this program.
 *           If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * Template class for service definition
 *
 * Service definition has three parts :
 *   $method,            GET, POST etc string|string[]
 *   $uri,               '/'
 *   $callback           callable, below
 *
 * For uri and derived arguments,
 * please visit https://github.com/nikic/FastRoute.
 * Derived (uri) arguments, if any, are accessible(/included) using
 * request::getParsedBody()
 *
 * A callable can be
 *   simple function
 *   anonymous function
 *   instantiated object+method, passed as an array: object, method name
 *   class name and static method, passed as an array: class, method name (factory method?)
 *   instantiated object, class has an (magic) __call method
 *   class name, class has an (magic) __callStatic method
 *   instantiated object, class has an (magic) __invoke method
 *
 * Service definition callbacks MUST have arguments
 *   ServerRequestInterface and ResponseInterface.
 * Callback MUST return ResponseInterface.
 *
 * For service definition callbacks arguments, you have access to all
 * methods as described in
 *   Psr\Http\Message\ServerRequestInterface;
 *   Psr\Http\Message\ResponseInterface;
 *
 * The default response status code is 200, any other codes must be set in
 * your service definition callback.
 *
 * Aside from serializing and encoding headers, you may set additional
 * headers using response::withHeader().
 *
 * A strong recommendation is to place service definition callbacks
 * (as well as the applikation and config) outside webserver document root.
 *
 * You define your services as
 * (using class TemplateServiceClass below)
 *
 * $callback   = [
 *     'yourNamespace\\TemplateServiceClass',
 *     'templateServiceMethod'
 * ];
 * $serviceDef = [
 *     RestServer::METHOD   => yourNamespace\TemplateServiceClass::METHOD,
 *     RestServer::URI      => yourNamespace\TemplateServiceClass::URI,
 *     RestServer::CALLBACK => $callback
 * ];
 *
 * You can attach services in four ways
 *
 * $config = [];
 * $config[RestServer::SERVICES]   = [];
 * $config[RestServer::SERVICES][] = $serviceDef;
 * (new RestServer( $config ))->run();
 *
 * OR
 *
 * (new RestServer( $config ))->attachRestService( $serviceDef )->run();
 *
 * OR
 *
 * $RestServer = new RestServer( $config );
 * $RestServer->attachRestService( $serviceDef );
 * $RestServer->run();
 *
 * OR
 *
 * $RestServer = new RestServer( $config );
 * $attachRestServiceCallback = $RestServer->getAttachRestServiceCallback();
 *    <invoke attachRestServiceCallback as in 3:d example below>
 * $RestServer->run();
 *
 * The first two and last ends up with
 *   the RestServer::attachRestService method.
 * The method (in ex. three and four)
 *   may throw InvalidArgumentException on unvalid arguments,
 *   in ex. one and two an 5xx status code is emitted as response.
 */

namespace yourNamespace;

use kigkonsult\RestServer\RestServer;
use Kigkonsult\RestServer\Handlers\RequestMethodHandler;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class and callback for method : 'GET'
 *                        uri    : '/'
 */
class TemplateServiceClass
{
/**
 * Service definition as constants
 */
    const METHOD = 'GET';

    const URI = '/';

    const ENDPOINT = 'templateServiceMethod';

/**
 * Class constants, headers, cfg keys etc
 */
    const TEMPLATE = 'template';

/**
 * Template Route callback method
 *
 * (opt. derived param(s) from route uri)
 * @param ServerRequestInterface $request      // always last
 * @param ResponseInterface      $response     // always last
 * @return ResponseInterface
 */
    public function templateServiceMethod(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {

        // your code here....

        // opt. access to the config
        $config = $request->getAttribute( RestServer::CONFIG, [] );

        // opt. fetch a unique session correlation-id, later use in logging etc
        $correlationId = $config[RestServer::CORRELATIONID];

        // opt. if logger is set, ref. RestServer.php
        $logger = RestServer::getLogger();
        if ( ! empty( $logger ) && \method_exists( $logger, RestServer::ERROR )) {
            $Logger->{RestServer::ERROR}( $correlationId . ' error message' ); // log an error
        }

        // Fetch the (decoded/unSerialized) request message body
        $requestBody = $request->getParsedBody();

        // Set response message body, here from an attribute..
        $responseBody = $request->getAttribute(
            self::TEMPLATE,
           'Hello word !!'
        );

/**
 * Set response message by using a non-standard
 *   (Psr\Http\Message\ResponseInterface) method
 *   Serializing/encoding will be taken care of later
 *   (as well as headers), in builtin handlers
 *   (directives are previously captured from request)
 *
 * response::withRawBody( data )
 *
 * param string|array : data
 * return : ResponseInterface
 *
 * may throw RuntimeException on error
 */
        $response = $response->withRawBody( $responseBody );
        return ( isset( $error ))
               ? $response->withStatus( 500 ) // ex. return status for internal server error
                                              // 4xx for invalid input message
                                              // explore src/Response.php for all codes
               : $response;                   // success return
    }
}

$TemplateServiceClass = new TemplateServiceClass();
$RestServer           = new RestServer();

$RestServer->attachRestService( [
    RestServer::METHOD   => TemplateServiceClass::METHOD,
    RestServer::URI      => TemplateServiceClass::URI,
    RestServer::CALLBACK => [
        $TemplateServiceClass,
        TemplateServiceClass::ENDPOINT,
    ],
]);

$RestServer->run();

/** ***********************************************************************
 * Another class and (factory) callback
 */
class ApplicationClass
{
/**
 * Message
 *
 * @var string
 */
    private $message = null;

/**
 * Class constructor
 *
 */
    public function __construct()
    {
        $this->message = 'Hello ';
    }

/**
 * Do something method
 *
 * $param string $name
 * @return string
 */
    public function doSomething(
        $name
    ) {
        return $this->message . $name;
    }

/**
 * Factory method
 *
 * $param ServerRequestInterface $request,
 * $param ResponseInterface      $response
 * @return ResponseInterface
 * @access static
 */
    public static function factory(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        $ApplicationClass = new self();

        $requestMessage = $request->getParsedBody();

        // some logic...
        if ( isset( $requestMessage['name'] )) {
            $responseMessage = $ApplicationClass->doSomething( $requestMessage['name'] );
            $response        = $response->withRawBody( $responseMessage );
        }
        return $response;
    }
}

$RestServer = new RestServer();

$RestServer->attachRestService( [
    RestServer::METHOD   => RequestMethodHandler::METHOD_GET,
    RestServer::URI      => '/',
    RestServer::CALLBACK => [
        'ApplicationClass', // incl. namespace !
        'factory',
    ],
]);

$RestServer->run();

/** ***********************************************************************
 * A third example
 */
$RestServer = new RestServer();

$attachRestServiceCallback = $RestServer->getAttachRestServiceCallback();

class applicationClass3
{
    public function registerAsRestService(
        $attachRestServiceCallback
    ) {
        $attachRestServiceCallback(
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
$applicationClass3 = new applicationClass3();
$applicationClass3->registerAsRestService( $attachRestServiceCallback );

$RestServer->run();
