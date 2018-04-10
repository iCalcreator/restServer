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

namespace Kigkonsult\RestServer;

use Kigkonsult\RestServer\Handlers\RequestMethodHandler;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\SapiStreamEmitter;
use RuntimeException;
use InvalidArgumentException;
use Exception;
use FastRoute\RouteCollector;
use FastRoute\Dispatcher;
use Kigkonsult\RestServer\Handlers\LogUtilHandler;

class RestServer
{
    /**
     * Class constants, config keys
     */
    const ALLOW            = 'allow';
    const BASEURI          = 'baseUri';
    const CONFIG           = 'config';
    const CORRELATIONID    = 'correlationId';
    const DISALLOW         = 'disallow';
    const FINALHANDLER     = 'finalHandler';
    const HANDLERS         = 'handlers';
    const IGNORE           = 'ignore';
    const INIT             = 'init';
    const SERVICES         = 'services';

    /**
     * Class constants, request attribute keys
     */
    const REQUESTTARGET    = 'requestTarget';
    const REQUESTMETHODURI = 'requestMethodUri';

    /**
     * Class constants, service definition keys
     */
    const URI              = 'uri';
    const METHOD           = 'method';
    const CALLBACK         = 'callback';

    /**
     * @var string version
     */
    private static $version = '0.9.23';

    /**
     * @var string misc
     */
    private static $space   = ' ';
    private static $slash   = '/';

    /**
     * Handler callbacks, called before routing
     *
     * @var callable[]
     */
    private static $builtinPreHandlers = [
        [ __NAMESPACE__ . '\\Handlers\\RequestMethodHandler',  'validateRequestMethod' ],
        [ __NAMESPACE__ . '\\Handlers\\CorsHandler',           'validateCors' ],
//      [ __NAMESPACE__ . '\\Handlers\\AuthenticationHandler', 'validateAuthentication' ],
        [ __NAMESPACE__ . '\\Handlers\\EncodingHandler',       'validateRequestHeader' ],
        [ __NAMESPACE__ . '\\Handlers\\ContentTypeHandler',    'validateRequestHeader' ],
        [ __NAMESPACE__ . '\\Handlers\\ContentTypeHandler',    'validateResponseHeader' ],
        [ __NAMESPACE__ . '\\Handlers\\EncodingHandler',       'validateResponseHeader' ],
        [ __NAMESPACE__ . '\\Handlers\\EncodingHandler',       'deCode' ],
        [ __NAMESPACE__ . '\\Handlers\\ContentTypeHandler',    'unSerialize' ],
    ];

    /**
     * Handler callbacks, called after routing
     *
     * @var callable[]
     */
    private static $builtinPostHandlers = [
        [ __NAMESPACE__ . '\\Handlers\\ContentTypeHandler', 'serialize' ],
        [ __NAMESPACE__ . '\\Handlers\\EncodingHandler',    'enCode' ],
        [ __NAMESPACE__ . '\\Handlers\\ContentTypeHandler', 'setContentLength' ],
    ];

    /**
     * Handler debug callbacks
     *
     * @var callable[]
     */
    private static $debugHandlers = [
        [
            __NAMESPACE__ . '\\Handlers\\LogUtilHandler',
            'logRequest',
        ],
        [
            __NAMESPACE__ . '\\Handlers\\LogUtilHandler',
            'logResponse',
        ],
    ];

    /**
     * Configuration
     *
     * @var mixed[]
     */
    private $config = [];

    /**
     * @var ServerRequestInterface
     */
    private $request;

    /**
     * @var ResponseInterface
     */
    private $response;

    /**
     * Callable pre service handlers, each with arguments ( ServerRequestInterface, ResponseInterface )
     *
     * @var callable[]
     */
    private $preHandlers = [];

    /**
     * Number of service preHandlers
     *
     * @var int
     */
    private $noPreHandlers = 0;

    /**
     * Callable post service handlers, each with arguments ( ServerRequestInterface, ResponseInterface )
     *
     * @var callable[]
     */
    private $postHandlers = [];

    /**
     * Service definitions
     *
     * @var array
     */
    private $services = [];

    /**
     * Number of rest services
     *
     * @var int
     */
    private $noOfServices = 0;

    /**
     * Callable final handler, with arguments ( ServerRequestInterface, ResponseInterface )
     *
     * @var callable[]
     */
    private $finalHandler = null;

    /**
     * Class constructor
     *
     * @param array $config
     * @param array $server  $_SERVER superglobal
     * @param array $query   $_GET    superglobal
     * @param array $body    $_POST   superglobal
     * @param array $cookies $_COOKIE superglobal
     * @param array $files   $_FILES  superglobal
     */
    public function __construct(
        array $config  = null,
        array $server  = null,
        array $query   = null,
        array $body    = null,
        array $cookies = null,
        array $files   = null
    ) {
        $this->initConfig( $config );
        $this->request       = ServerRequestFactory::fromGlobals( $server, $query, $body, $cookies, $files );
        $this->response      = new Response( StreamFactory::createStream(), 200 );
        $this->preHandlers   = self::$builtinPreHandlers;
        $this->noPreHandlers = \count( $this->preHandlers );
        $this->postHandlers  = self::$builtinPostHandlers;
        $error = null;
        try {
            $this->addHandlersFromConfig( $this->config );
            $this->attachRestServicesFromConfig( $this->config );
            $this->addFinalHandlerFromConfig( $this->config );
        }
        catch ( Exception $e ) {
            $this->request = $this->request->setAttribute( self::ERROR, $e );
        }
    }

    /**
     * class destructor
     */
    public function __destruct()
    {
        unset(
            $this->startTime,
            $this->config,
            $this->request,
            $this->response,
            $this->preHandlers,
            $this->noPreHandlers,
            $this->postHandlers,
            $this->services,
            $this->noServices,
            $this->finalHandler
        );
    }

    /**
     * Fire of the server and emits the response
     */
    public function run()
    {
        static $FMT2 = '%s time :%01.6f';
        $response = $this->processRequest();
        $emitter  = new SapiStreamEmitter();
        $emitter->emit( $response );
        $this->execFinalHandler( $response );
        $msg      = \sprintf(
            $FMT2,
            $this->config[self::CORRELATIONID],
            \microtime( true ) - $this->config[self::INIT]
        );
        self::log( $msg,self::INFO );
    }

    /**
     * Set config
     *
     * @param array $config
     * @return self
     * @throws InvalidArgumentException
     */
    public function setConfig(
        array $config
    ) {
        foreach( [ self::INIT, self::CORRELATIONID ] as $key )
            $config[$key] = $this->config[$key];
        $this->config       = $config;
        $this->addHandlersFromConfig( $config );
        $this->attachRestServicesFromConfig( $config );
        $this->addFinalHandlerFromConfig( $config );
        return $this;
    }

    /**
     * Init config
     *
     * @param null|array $config
     * @access private
     */
    private function initConfig(
        $config = null
    ) {
        if( empty( $config ))
            $config = [];
        if( ! isset( $config[self::INIT] ))
            $config[self::INIT] = \microtime( true );
        if( ! isset( $config[self::CORRELATIONID] ))
            $config[self::CORRELATIONID] = strtoupper( bin2hex( openssl_random_pseudo_bytes( 16) ));
        $this->config           = $config;
    }

    /**
     * Exec pre/post-handlers, init and exec found method/service callback
     *
     * @return ResponseInterface
     */
    public function processRequest()
    {
        if ( $this->hasInitRequestError()) {
            $this->response = $this->response->withStatus( 500 );
        }
        $this->request = $this->addRequestAttributes();
        $this->execPreHandlers();
        if ( isset( $this->config[self::DEBUG] )) {
            self::$debugHandlers[0]( $this->request );
        }
        if ( false === $this->request->getAttribute( self::ERROR, false )) {
            $this->response = $this->processRestServicesDefinitions();
        }
        $this->execPostHandlers();
        if ( isset( $this->config[self::DEBUG] )) {
            self::$debugHandlers[1](
                $this->request,
                $this->response
            );
        }
        return $this->response;
    }

    /**
     * Catch init request error
     *
     * @return bool
     * @access private
     */
    private function hasInitRequestError()
    {
        $error = $this->request->getAttribute( self::ERROR, false );
        if ( $error instanceof Exception ) {
            $corrId = $this->config[self::CORRELATIONID] . self::$space;
            self::log( $corrId . LogUtilHandler::jTraceEx( $error ), self::ERROR );
            self::log( $corrId . LogUtilHandler::getRequestToString( $this->request ), self::ERROR );
            $this->response     = $this->response->withStatus( 500 );   // Internal server error...
            $this->preHandlers  = [];
            $this->services     = [];
            $this->postHandlers = [];
            return true;
        } // end if
        return false;
    }

    /**
     * Set request attributes
     *
     * add services method & uri array
     * add config to attributes
     * add (real) request target to attributes
     * $return ServerRequestInterface
     * @access private
     */
    private function addRequestAttributes()
    {
        return $this->request->withAttribute( self::REQUESTMETHODURI,
                                              self::extractMethodAndUriFromServices( $this->services ))
                             ->withAttribute( self::CONFIG,
                                              $this->config )
                             ->withAttribute( self::REQUESTTARGET,
                                              $this->getUriFromRequestTarget());
    }

    /**
     * Perform closing actions, call finalHandler
     *
     * @param ResponseInterface
     */
    public function execFinalHandler(
        ResponseInterface $response
    ) {
        if( ! empty( $this->finalHandler )) {
            $handler = $this->finalHandler;
            $handler( $this->request, $response );
        }
    }

    /**
     * Return services method & uri in array
     *
     * @param array $services
     * @return array
     * @access private
     */
    private function extractMethodAndUriFromServices(
        array $services
    ) {
        $methodUriArr = [];
        foreach ( $services as $method => $uriServices ) {
            foreach ( $uriServices as $uri => $callback ) {
                if ( empty( $callback )) {
                    continue;
                }
                if ( ! isset( $methodUriArr[$method] )) {
                    $methodUriArr[$method] = [];
                }
                if ( ! \in_array( $callback[self::URI], $methodUriArr[$method] )) {
                    $methodUriArr[$method][] = $uri;
                }
            }
        } // end foreach
        return $methodUriArr;
    }

    /**
     * Add handlers from config
     *
     * @param array $config
     * @access private
     * @throws InvalidArgumentException
     */
    private function addHandlersFromConfig(
        array $config
    ) {
        if ( isset( $config[self::HANDLERS] )) {
            $this->addHandler( $config[self::HANDLERS] );
        }
        unset( $this->config[self::HANDLERS] );
    }

    /**
     * Add (callable) pre service handler(s)
     *
     * Each handler MUST have arguments (ServerRequestInterface $request, ResponseInterface $response)
     * and MUST return [ ServerRequestInterface, ResponseInterface ]
     * @param callable|callable[] $handler one or more callable
     * @return static
     * @throws InvalidArgumentException on not callable handler
     */
    public function addHandler(
        $handler
    ) {
        static $FMT = '%s : Handler #%d is not callable';
        if ( ! \is_array( $handler ) ||
            ( 2 == \count( $handler )) &&
            \is_callable( $handler, true )) {
            $handler = [ $handler ];
        } // end if
        $cnt = $this->noPreHandlers - \count( self::$builtinPreHandlers );
        foreach ( $handler as $h ) {
            $cnt += 1;
            if ( ! \is_callable( $h, true )) {
                throw new InvalidArgumentException( \sprintf( $FMT, __METHOD__, $cnt ));
            }
            $this->noPreHandlers += 1;
            $this->preHandlers[] = $h;
        } // end foreach
        return $this;
    }

    /**
     * Exec all added pre service handlers
     *
     * Break if a handler request has return attribute error=true
     * will also skip service management
     * @access private
     */
    private function execPreHandlers(
    ) {
        foreach ( $this->preHandlers as $x => $handler ) {
            list( $this->request, $this->response ) = $handler( $this->request, $this->response );
            if ( false !== $this->request->getAttribute( self::ERROR, false )) {
                $this->preHandlers = [];
                $this->services    = []; // skip services
                return;
                break;
            } // end if
            if (( 1 == $x ) && // i.e. CorsHandler
                ( RequestMethodHandler::METHOD_OPTIONS == $this->request->getMethod()) ) {
                try {
                    list(
                        $this->request,
                        $this->response
                    ) = RequestMethodHandler::setOptionsResponsePayload(
                            $this->request,
                            $this->response
                    );
                } catch ( Exception $e ) {
                    $corrId = $this->config[self::CORRELATIONID] . self::$space;
                    self::log( $corrId . LogUtilHandler::jTraceEx( $e ), self::ERROR );
                    self::log( $corrId . LogUtilHandler::getRequestToString( $this->request ), self::ERROR );
                    $this->request  = $this->request->withAttribute( self::ERROR, true );
                    $this->response = $this->response->withStatus( $e->getCode());
                }
                $this->postHandlers = [];
                $this->services     = []; // skip services
                return;
                break;
            } // end if
        } // end foreach
    }

    /**
     * Exec post service handlers
     *
     * @access private
     */
    private function execPostHandlers()
    {
        foreach ( $this->postHandlers as $x => $handler ) {
            list( $this->request, $this->response ) = $handler( $this->request, $this->response );
            if ( false !== $this->request->getAttribute( self::ERROR, false )) {
                return;
                break;
            }
        } // end foreach
    }

    /**
     * Add finalHandler from config
     *
     * @param array $config
     * @access private
     * @throws InvalidArgumentException
     */
    private function addFinalHandlerFromConfig(
        array $config
    ) {
        if ( isset( $config[self::FINALHANDLER] )) {
            $this->addFinalHandler( $config[self::FINALHANDLER] );
        }
        unset( $this->config[self::FINALHANDLER] );
    }

    /**
     * Add (callable) final handler
     *
     * The handler MUST have arguments ( ServerRequestInterface $request, ResponseInterface $response)
     * and No return
     * @param callable $handler
     * @return static
     * @throws InvalidArgumentException on not callable handler
     */
    public function addFinalHandler(
        $handler
    ) {
        static $FMT = 'finalHandler is not callable';
        if ( ! \is_callable( $handler, true  )) {
            throw new InvalidArgumentException( $FMT );
        } // end if
        $this->finalHandler = $handler;
        return $this;
    }

    /**
     * Attach rest service definitions from config
     *
     * @param array $config
     * @access private
     * @thows InvalidArgumentException
     */
    private function attachRestServicesFromConfig(
        array $config
    ) {
        if( isset( $config[self::SERVICES] )) {
            foreach ( $config[self::SERVICES] as $x => $service ) {
                $this->attachRestService( $service );
            } // end foreach
        }
        unset( $this->config[self::SERVICES] );
    }

    /**
     * Attach rest service definition ([method, uri, callback])
     *
     * @param array|string $serviceDef
     * @param string       $uri
     * @param string|array $callback
     * @return static
     * @throws InvalidArgumentException on service definition error
     */
    public function attachRestService(
        $serviceDef,
        $uri      = null,
        $callback = null
    ) {
        if (( null !== $uri ) &&
            ( null !== $callback )) {
            $method     = $serviceDef;
            $serviceDef = [];
            $serviceDef[self::METHOD]   = $method;
            $serviceDef[self::URI]      = $uri;
            $serviceDef[self::CALLBACK] = $callback;
        }
        self::assertValidService( $serviceDef, ( 1 + $this->noOfServices ));
        foreach ((array) $serviceDef[self::METHOD] as $x => $serviceMethod ) {
            $this->noOfServices      += 1;
            $serviceDef[self::METHOD] = $serviceMethod;
            $this->services[$serviceMethod][$serviceDef[self::URI]] = $serviceDef;
        }
        return $this;
    }

    /**
     * Validate service definition
     *
     * @param array $service
     * @param int   $cnt
     * @return bool true
     * @throws InvalidArgumentException
     * access private
     * @static
     */
    private static function assertValidService(
        array $service,
        $cnt
    ) {
        static $FMT1 = 'ServiceDef #%d is not valid';
        static $FMT2 = 'ServiceDef #%d method %s is not valid';
        static $FMT3 = 'ServiceDef #%d callback is not callable';
        if ( ! \is_array( $service ) ||
            ! isset( $service[self::METHOD] ) ||
            ! isset( $service[self::URI] ) ||
            ! isset( $service[self::CALLBACK] )) {
            throw new InvalidArgumentException( \sprintf( $FMT1, $cnt ));
        }
        if ( ! RequestMethodHandler::isValidRequestMethod( $service[self::METHOD] )) {
            throw new InvalidArgumentException( \sprintf( $FMT2, $cnt, $service[self::METHOD] ));
        }
        if ( ! \is_callable( $service[self::CALLBACK], true )) {
            throw new InvalidArgumentException( \sprintf( $FMT3, $cnt ));
        }
        return true;
    }

    /**
     * Return (this) attachRestService as callback
     *
     * @return array  callback
     */
    public function getAttachRestServiceCallback()
    {
        return [
            $this,
            'attachRestService',
        ];
    }

    /**
     * Detach rest service definition ([method, uri, callback])
     *
     * @param string|string[] $method
     * @param string          $uri
     * @return bool, false on not found otherwise true
     */
    public function detachRestService(
        $method,
        $uri
    ) {
        $success = false;
        foreach ((array) $method as $serviceMethod ) {
            if ( isset( $this->services[$serviceMethod][$uri] )) {
                unset( $this->services[$serviceMethod][$uri] );
                $this->noOfServices -= 1;
                $success             = true;
                break;
            }
        } // end foreach
        return $success;
    }

    /**
     * Process rest service definitions
     *
     * @return ResponseInterface
     * @access private
     */
    private function processRestServicesDefinitions()
    {
        if ( RequestMethodHandler::METHOD_OPTIONS != $this->request->getMethod()) {
            $orgMethod = $this->request->getMethod();
            if ( RequestMethodHandler::METHOD_HEAD == $orgMethod ) {
                $this->request = $this->request->withMethod( RequestMethodHandler::METHOD_GET );
            }
            // find service definition callback
            try {
                $serviceInfo = $this->getServiceInfo();
            } catch ( Exception $e ) { // & RuntimeException
                $corrId = $this->config[self::CORRELATIONID] . self::$space;
                self::log( $corrId . LogUtilHandler::jTraceEx( $e ), self::ERROR );
                self::log( $corrId . LogUtilHandler::getRequestToString( $this->request ), self::ERROR );
                return $this->response->withStatus( $e->GetCode());
            }
            // exec service definition callback
            $this->response = $this->execServiceDefinitionCallback( $serviceInfo );
            if ( RequestMethodHandler::METHOD_HEAD == $orgMethod ) {
                $this->response = $this->response->withRawBody( null );
            }
        } // end if
        return $this->response;
    }

    /**
     * Return serviceInfo for matched rest services ([method, uri, callback])
     *
     * @return array
     * @access private
     * @throws RuntimeException on matching service error
     * @link https://github.com/nikic/FastRoute
     */
    private function getServiceInfo()
    {
        static $FMT1 = 'NO services attached!! (%s and uri \'%s\'), return status 500';
        static $FMT2 = 'FastRoute addRoute error for %s and uri \'%s\', return status 500';
        static $FMT3 = 'Dispatcher FastRoute error for %s and uri \'%s\', return status 500';
        $requestUri  = $this->request->getAttribute( self::REQUESTTARGET, '/' );
        $httpMethod  = $this->request->getMethod();
        $services    = $this->getArrayOfServiceDefinitions();
        if ( empty( $services )) {
            throw new RuntimeException( \sprintf( $FMT1, $httpMethod, $requestUri ), 500 );
        }
        try {
            $dispatcher = \FastRoute\simpleDispatcher(
                function ( RouteCollector $r ) use ( $services ) {
                    foreach ( $services as $x => $service ) {
                        $r->addRoute(
                            $service[self::METHOD],
                            $service[self::URI],
                            $service[self::CALLBACK]
                        );
                    } // end foreach
                } // end function
            );
        } catch ( Exception $e ) { // & FastRoute\BadRouteException etc
            throw new RuntimeException( \sprintf( $FMT2, $httpMethod, $requestUri ), 500, $e );
        }
        try {
            $serviceInfo = $dispatcher->dispatch( $httpMethod, $requestUri );
        } catch ( Exception $e ) { // & FastRoute\BadRouteException etc
            throw new RuntimeException( \sprintf( $FMT3, $httpMethod, $requestUri ), 500, $e );
        }
        return $serviceInfo;
    }

    /**
     * Return array of rest services [*[method, uri, callback]]
     *
     * @return array
     * @access private
     */
    private function getArrayOfServiceDefinitions()
    {
        $return = [];
        foreach ( $this->services as $method => $uriServices ) {
            foreach ( $uriServices as $uri => $definition ) {
                if ( ! empty( $definition )) {
                    $return[] = $definition;
                }
            }
        }
        return $return;
    }

    /**
     * Exec callback for matched rest service method and uri
     *
     * @param array $serviceInfo
     * @return ResponseInterface
     * @access private
     * @link https://github.com/nikic/FastRoute
     */
    private function execServiceDefinitionCallback(
        array $serviceInfo
    ) {
        static $FMT4 = '%s %s with \'%s\' NOT allowed, return status 405';
        static $FMT3 = '%s %s and uri \'%s\' NOT found, return status 404';
        $httpMethod = $this->request->getMethod();
        switch ( $serviceInfo[0] ) {
            case Dispatcher::FOUND :
                if ( ! empty( $serviceInfo[2] )) {
                    $this->request = self::updateRequest(
                        $this->request,
                        $serviceInfo[2],
                        $httpMethod
                    );
                }
                $handler = $serviceInfo[1];
                return $handler( $this->request, $this->response );
                break;
            case Dispatcher::METHOD_NOT_ALLOWED :
                $corrId = $this->config[self::CORRELATIONID] . self::$space;
                $requestUri = $this->request->getAttribute( self::REQUESTTARGET, self::$slash );
                self::log( \sprintf( $FMT4, $corrId, $httpMethod, $requestUri ), self::WARNING );
                self::log( $corrId . LogUtilHandler::getRequestToString( $this->request ), self::WARNING );
                $allowedMethods = RequestMethodHandler::extendAllowedMethods(
                    $this->request,
                    $serviceInfo[1]
                );
                return RequestMethodHandler::setStatusMethodNotAllowed(
                    $this->response,
                    $allowedMethods
                );
                break;
            case Dispatcher::NOT_FOUND :
                // no break
            default :
                $corrId = $this->config[self::CORRELATIONID] . self::$space;
                $requestUri = $this->request->getAttribute( self::REQUESTTARGET, self::$slash );
                self::log( \sprintf( $FMT3, $corrId, $httpMethod, $requestUri ), self::WARNING );
                self::log( $corrId . LogUtilHandler::getRequestToString( $this->request ), self::WARNING );
                return $this->response->withStatus( 404 );
                break;
        } // end switch
    }

    /**
     * Return uri from requestTarget (with baseUri eliminated)
     *
     * @return string
     * @access private
     */
    private function getUriFromRequestTarget()
    {
        static $FILE = 'file';
        static $Q    = '?';
        $uri = $this->request->getRequestTarget();
        if ( false !== ( $pos = \strpos( $uri, $Q )) ) {
            $uri = \substr( $uri, 0, $pos );
        }
        $baseUri = ( isset( $this->config[self::BASEURI] ))
            ? $this->config[self::BASEURI]
            : \basename(( \array_slice( \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), -1 ))[0][$FILE] );
        if ( ! empty( $baseUri ) &&
            ( false !== \strpos( $uri, $baseUri )) ) {
            $uriParts = \explode( self::$slash, $uri );
            $found    = false;
            $x        = 0;
            foreach ( $uriParts as $x => $uriPart ) {
                if ( $baseUri == $uriPart ) {
                    $found = true;
                    break;
                }
            } // end foreach
            if ( $found ) {
                $uriParts = \array_slice( $uriParts, ( $x + 1 ));
            }
            $uri = self::$slash . \implode( self::$slash, $uriParts );
        }
        return $uri;
    }

    /**
     * Return updated request from service uri derived arguments
     *
     * @param ServerRequestInterface $request
     * @param array                  $arguments
     * @param string                 $httpMethod
     * @return ServerRequestInterface
     * @access private
     * @static
     * @todo management of parsedBody array error
     */
    private static function updateRequest(
        ServerRequestInterface $request,
        array $arguments,
        $httpMethod
    ) {
        static $queryMethods = [
            RequestMethodHandler::METHOD_DELETE,
            RequestMethodHandler::METHOD_GET,
            RequestMethodHandler::METHOD_HEAD,
        ];
        $queryParams = $request->getQueryParams();
        $parsedBody  = $request->getParsedBody();
        $isQueryMethod = ( \in_array( $httpMethod, $queryMethods ));
        if ( null === $parsedBody ) {
            $parsedBody = [];
        }
        foreach ( $arguments as $k => $v ) {
            $parsedBody[$k] = $v;
            if ( $isQueryMethod ) {
                $queryParams[$k] = $v;
            }
        } // end foreach
        return $request->withQueryParams( $queryParams )
                       ->withParsedBody( $parsedBody );
    }

    /** ***********************************************************************
     * Simple 'ping' service checking server is alive
     */

    /**
     * Service returning server info
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return ResponseInterface
     * @access private
     * @static
     */
    private static function pingService(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        return $response->withRawBody(
            [
                'service' => array(
                    'server'    => get_class() . ' ' . self::$version,
                    'copyright' => '2018 Kjell-Inge Gustafsson, kigkonsult, All rights reserved',
                    'time'      => date('Y-m-d H:i:s' ),
                ),
            ]
        );
    }

    /**
     * Return service definition of ping service
     *
     * @return array
     * @static
     */
    public static function getPingServiceDef()
    {
        $class = get_class();
        return [
            self::METHOD   => [
                RequestMethodHandler::METHOD_GET,
            ],
            self::URI      => '/ping',
            self::CALLBACK => [
                $class,
                'pingService',
            ],
        ];
    }

    /** ***********************************************************************
     * Logger constants and methods
     */

    /**
     * Logger constants
     */
    const ERROR   = 'error';
    const WARNING = 'warning';
    const INFO    = 'info';
    const DEBUG   = 'debug';

    /**
     * @var object
     * @static
     */
    private static $logger = null;

    /**
     * Get logger
     *
     * Return logger to RestServer handlers
     *
     * @return object
     * @static
     */
    public static function getLogger()
    {
        return self::$logger;
    }

    /**
     * Set logger
     *
     * @param object $logger
     * @static
     */
    public static function setLogger(
        $logger
    ) {
        self::$logger = $logger;
    }

    /**
     * Log into logger
     *
     * Captures logging from RestServer
     *
     * @param string|object $msg
     * @param string        $prio
     * @static
     */
    public static function log(
        $msg,
        $prio = RestServer::ERROR
    ) {
        if ( \is_object( self::$logger ) &&
            \method_exists( self::$logger, $prio )) {
            self::$logger->{$prio}( LogUtilHandler::getStringifiedObject( $msg ));
        }
    }
}
