<?php
/**
 * restServer, a PSR HTTP Message rest server implementation
 *
 * This file is a part of restServer.
 *
 * Copyright 2018 Kjell-Inge Gustafsson, kigkonsult, All rights reserved
 * Link      http://kigkonsult.se/restServer/index.php
 * Version   0.8.0
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
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Server;
use Zend\Diactoros\Stream;
use Zend\Diactoros\Response\SapiStreamEmitter;
use RuntimeException;
use InvalidArgumentException;
use Exception;
use FastRoute\RouteCollector;
use FastRoute\Dispatcher;

class RestServer
{
    /**
     * Class constants, headers, cfg keys etc
     */
    const URI              = 'uri';

    const METHOD           = 'method';

    const CALLBACK         = 'callback';

    const HANDLERS         = 'handlers';

    const SERVICES         = 'services';

    const CONFIG           = 'config';

    const ALLOW            = 'allow';

    const DISALLOW         = 'disallow';

    const INIT             = 'init';

    const BASEURI          = 'baseUri';

    const REQUESTTARGET    = 'requestTarget';

    const REQUESTMETHODURI = 'requestMethodUri';

    /**
     * @var string
     */
    private static $version = '0.8.2';

    /**
     * Handler callbacks, called before routing
     *
     * @var callable[]
     */
    private static $builtinPreHandlers = [
        [ __NAMESPACE__ . '\\Handlers\\RequestMethodHandler', 'validateRequestMethod' ],
        [ __NAMESPACE__ . '\\Handlers\\CorsHandler',          'validateCors' ],
        [ __NAMESPACE__ . '\\Handlers\\EncodingHandler',      'validateRequestHeader' ],
        [ __NAMESPACE__ . '\\Handlers\\ContentTypeHandler',   'validateRequestHeader' ],
        [ __NAMESPACE__ . '\\Handlers\\ContentTypeHandler',   'validateResponseHeader' ],
        [ __NAMESPACE__ . '\\Handlers\\EncodingHandler',      'validateResponseHeader' ],
        [ __NAMESPACE__ . '\\Handlers\\EncodingHandler',      'deCode' ],
        [ __NAMESPACE__ . '\\Handlers\\ContentTypeHandler',   'unSerialize' ],
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
     * An Server instance
     *
     * @var Server (Zend\Diactoros\Server)
     */
    private $server = null;

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
        if ( null === $config ) {
            $config = [];
        }
        $this->config        = $config + [ self::INIT => \microtime( true ) ];
        $request             = ServerRequestFactory::fromGlobals( $server, $query, $body, $files );
        $response            = new Response( self::getNewStream(), 200 );
        $this->preHandlers   = self::$builtinPreHandlers;
        $this->noPreHandlers = \count( $this->preHandlers );
        $this->postHandlers  = self::$builtinPostHandlers;
        if ( isset( $this->config[self::HANDLERS] ) ) {
            list( $request, $response ) = $this->addHandlersFromConfig( $request, $response );
        }
        if ( ( false === $request->getAttribute( self::ERROR, false ) ) &&
            isset( $this->config[self::SERVICES] ) ) {
            list( $request, $response ) = $this->attachRestServicesFromConfig( $request, $response );
        }
        $this->server = new Server(
            function (
                ServerRequestInterface $request,
                ResponseInterface $response,
                $done
            ) {
                return $this->serverCallback( $request, $response, $done );
            },
            $request,
            $response
        );
    }

    /**
     * class destructor
     */
    public function __destruct()
    {
        unset(
            $this->startTime,
            $this->config,
            $this->server,
            $this->preHandlers,
            $this->noPreHandlers,
            $this->postHandlers,
            $this->services,
            $this->noServices
        );
    }

    /**
     * Fire of the server and emits the response
     */
    public function run()
    {
        static $FMT2 = 'time :%01.6f';
        $this->server->setEmitter( new SapiStreamEmitter() );
        $this->server->listen();
        self::log( \sprintf( $FMT2, ( \microtime( true ) - $this->config[self::INIT] ) ), self::INFO );
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
        $config[self::INIT] = $this->config[self::INIT];
        $this->config       = $config;
        if ( isset( $config[self::HANDLERS] ) ) {
            $this->addHandler( $this->config[self::HANDLERS] );
            unset( $this->config[self::HANDLERS] );
        }
        if ( isset( $config[self::SERVICES] ) ) {
            foreach ( $this->config[self::SERVICES] as $x => $service ) {
                $this->attachRestService( $service );
            }
            unset( $this->config[self::SERVICES] );
        } // end if
        return $this;
    }

    /**
     * Allow access to private properties
     *
     * @param string $name
     * @return mixed
     */
    public function __get(
        $name
    ) {
        if ( \property_exists( $this, $name ) ) {
            return $this->{$name};
        }
        return null;
    }

    /**
     * Server callback, exec pre/POST-handlers, perform routing and exec found method/service callback
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param null|callable          $done
     * @return ResponseInterface
     */
    public function serverCallback(
        ServerRequestInterface $request,
        ResponseInterface $response,
        $done = null
    ) {
        // initialize server
        list( $request, $response ) = $this->setUp( $request, $response );
        // exec preHandlers
        list( $request, $response ) = $this->execPreHandlers( $request, $response );
        // debug ??
        if ( isset( $this->config[self::DEBUG] ) ) {
            list( $request, $response ) = self::$debugHandlers[0]( $request, $response );
        }
        // find and exec service callback
        if (( false === $request->getAttribute( self::ERROR, false )) &&
            ( RequestMethodHandler::METHOD_OPTIONS != $request->getMethod())) {
            $orgMethod = $request->getMethod();
            if ( RequestMethodHandler::METHOD_HEAD == $orgMethod ) {
                $request = $request->withMethod( RequestMethodHandler::METHOD_GET );
            }
            try {
                $serviceInfo = $this->getServiceInfo( $request );
            } catch ( Exception $e ) { // & RuntimeException
                self::log( $e, self::ERROR );
                self::log( $request, self::ERROR );

                return $response->withStatus( $e->GetCode() );
            }
            $response = $this->execService( $request, $response, $serviceInfo );

            if ( RequestMethodHandler::METHOD_HEAD == $orgMethod ) {
                $response = $response->withRawBody( null );
            }
        } // end if
        // exec postHandlers
        list( $request, $response ) = $this->execPostHandlers( $request, $response );
        if ( isset( $this->config[self::DEBUG] ) ) {
            list( $request, $response ) = self::$debugHandlers[1]( $request, $response );
        }
        return $response;
    }

    /**
     * Set up server
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @access private
     */
    private function setUp(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        // catch init request error
        $error = $request->getAttribute( self::ERROR, false );
        if ( $error instanceof Exception ) {
            self::log( $error->getMessage(), self::ERROR );
            self::log( $error, self::ERROR );
            self::log( $request, self::ERROR );
            $this->preHandlers  = [];
            $this->services     = [];
            $this->postHandlers = [];

            return [
                $request,
                $response->withStatus( 500 ),   // Internal server error...
            ];
        } // end if
        // set (array) attribute from each service method & uri
        $methods = [];
        foreach ( $this->services as $method => $uriServices ) {
            foreach ( $uriServices as $uri => $callback ) {
                if ( empty( $callback ) ) {
                    continue;
                }
                if ( ! isset( $methods[$method] )) {
                    $methods[$method] = [];
                }
                if ( ! \in_array( $callback[self::URI], $methods[$method] )) {
                    $methods[$method][] = $uri;
                }
            }
        } // end foreach
        $request = $request->withAttribute( self::REQUESTMETHODURI, $methods );
        // add config to attributes
        $request = $request->withAttribute( self::CONFIG, $this->config );
        // add (real) request target to attributes
        $request = $request->withAttribute( self::REQUESTTARGET, $this->getUriFromRequestTarget( $request ) );
        return [
            $request,
            $response,
        ];
    }

    /**
     * Add handlers from config
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @access private
     */
    private function addHandlersFromConfig(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        try {
            $this->addHandler( $this->config[self::HANDLERS] );
        } catch ( Exception $e ) { // & InvalidArgumentException
            return [
                $request->withAttribute( self::ERROR, $e ),
                $response->withStatus( 500 ),
            ];
        }
        unset( $this->config[self::HANDLERS] );

        return [
            $request,
            $response,
        ];
    }

    /**
     * Add (callable) pre service handler(s)
     *
     * Each handler with arguments (ResponseInterface $request, ServerRequestInterface $response)
     * and MUST return [ ResponseInterface, ServerRequestInterface ]
     * @param callable|callable[]| $handler one or more callable
     * @return static
     * @throws InvalidArgumentException on not callable handler
     */
    public function addHandler(
        $handler
    ) {
        static $FMT = '%s : Handler #%d is not callable';
        if ( ! \is_array( $handler ) ||
            ( 2 == \count( $handler ) ) &&
            \is_callable( $handler, true ) ) {
            $handler = [ $handler ];
        } // end if
        $cnt = $this->noPreHandlers - \count( self::$builtinPreHandlers );
        foreach ( $handler as $h ) {
            $cnt += 1;
            if ( ! \is_callable( $h, true ) ) {
                throw new InvalidArgumentException( \sprintf( $FMT, __METHOD__, $cnt ) );
            }
            $this->noPreHandlers += 1;
            $this->preHandlers[] = $h;
        } // end foreach
        return $this;
    }

    /**
     * Call all added pre service callback handlers
     *
     * Break if a handler request has return attribute error=true
     * will also skip service mgnt
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @access private
     */
    private function execPreHandlers(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        foreach ( $this->preHandlers as $x => $handler ) {
            list( $request, $response ) = $handler( $request, $response );
            if ( false !== $request->getAttribute( self::ERROR, false ) ) {
                $this->preHandlers = [];
                $this->services    = [];

                return [
                    $request,
                    $response,
                ];
            } //end if
            if ( ( 1 == $x ) &&
                ( RequestMethodHandler::METHOD_OPTIONS == $request->getMethod() ) ) {
                try {
                    list( $request, $response ) = RequestMethodHandler::setOptionsResponsePayload( $request, $response );
                } catch ( Exception $e ) {
                    self::log( $e, self::ERROR );
                    self::log( $request, self::ERROR );
                    $request  = $request->withAttribute( self::ERROR, true );
                    $response = $response->withStatus( $e->getCode() );
                }
                $this->postHandlers = [];
                $this->services = [];    // skip services
                return [
                    $request,
                    $response,
                ];
            } //end if
        } // end foreach
        return [
            $request,
            $response,
        ];
    }

    /**
     * Call post service callback handlers
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @access private
     */
    private function execPostHandlers(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        foreach ( $this->postHandlers as $x => $handler ) {
            list( $request, $response ) = $handler( $request, $response );
            if ( false !== $request->getAttribute( self::ERROR, false ) ) {
                break;
            }
        } // end foreach
        return [
            $request,
            $response,
        ];
    }

    /**
     * Attach rest method/uri services from config
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @access private
     */
    private function attachRestServicesFromConfig(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        foreach ( $this->config[self::SERVICES] as $x => $service ) {
            try {
                $this->attachRestService( $service );
            } catch ( Exception $e ) { // & InvalidArgumentException
                return [
                    $request->withAttribute( self::ERROR, $e ),
                    $response->withStatus( 500 ),
                ];
            }
        } // end foreach
        unset( $this->config[self::SERVICES] );

        return [
            $request,
            $response,
        ];
    }

    /**
     * Validates service
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
            ! isset( $service[self::CALLBACK] ) ) {
            throw new InvalidArgumentException( \sprintf( $FMT1, $cnt ) );
        }
        if ( ! RequestMethodHandler::isValidRequestMethod( $service[self::METHOD] ) ) {
            throw new InvalidArgumentException( \sprintf( $FMT2, $cnt, $service[self::METHOD] ) );
        }
        if ( ! \is_callable( $service[self::CALLBACK], true ) ) {
            throw new InvalidArgumentException( \sprintf( $FMT3, $cnt ) );
        }

        return true;
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
        $uri = null,
        $callback = null
    ) {
        if ( ( null !== $uri ) &&
            ( null !== $callback ) ) {
            $method = $serviceDef;
            $serviceDef = [];
            $serviceDef[self::METHOD] = $method;
            $serviceDef[self::URI] = $uri;
            $serviceDef[self::CALLBACK] = $callback;
        }
        self::assertValidService( $serviceDef, ( 1 + $this->noOfServices ) );
        foreach ( (array)$serviceDef[self::METHOD] as $x => $serviceMethod ) {
            $this->noOfServices += 1;
            $serviceDef[self::METHOD] = $serviceMethod;
            $this->services[$serviceMethod][$serviceDef[self::URI]] = $serviceDef;
        }

        return $this;
    }

    /**
     * Return attachRestService callback
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
     * Detach rest service ([method, uri, callback])
     * Can only detach attached services, no services origin from config
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
        foreach ( (array)$method as $serviceMethod ) {
            if ( isset( $this->services[$serviceMethod][$uri] ) ) {
                unset( $this->services[$serviceMethod][$uri] );
                $this->noOfServices -= 1;
                $success             = true;
                break;
            }
        } // end foreach
        return $success;
    }

    /**
     * Return array of rest services ([method, uri, callback])
     *
     * @return array
     * @access private
     */
    private function getServices()
    {
        $return = [];
        foreach ( $this->services as $method => $uriServices ) {
            foreach ( $uriServices as $uri => $callback ) {
                if ( ! empty( $callback ) ) {
                    $return[] = $callback;
                }
            }
        }

        return $return;
    }

    /**
     * Return matched rest services ([method, uri, callback])
     *
     * @param ServerRequestInterface $request
     * @return array
     * @access private
     * @throws RuntimeException on matching service error
     * @link https://github.com/nikic/FastRoute
     */
    private function getServiceInfo(
        ServerRequestInterface $request
    ) {
        static $FMT1 = 'NO services attached for #%d for %s and uri \'%s\', return status 500';
        static $FMT2 = 'FastRoute addRoute error for %s and uri \'%s\', return status 500';
        static $FMT3 = 'Dispatcher FastRoute error for %s and uri \'%s\', return status 500';
        $requestUri  = $request->getAttribute( self::REQUESTTARGET, '/' );
        $httpMethod  = $request->getMethod();
        $services    = $this->getServices();
        if ( empty( $services ) ) {
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
     * Exec callback for matched rest service (method, uri)
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param array                  $serviceInfo
     * @return ResponseInterface
     * @access private
     * @link https://github.com/nikic/FastRoute
     */
    private function execService(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $serviceInfo
    ) {
        static $FMT3 = '%s and uri \'%s\' NOT found, return status 404';
        static $FMT4 = '%s with \'%s\' NOT allowed, return status 405';
        $httpMethod = $request->getMethod();
        switch ( $serviceInfo[0] ) {
            case Dispatcher::NOT_FOUND :
                $requestUri = $request->getAttribute( self::REQUESTTARGET, '/' );
                self::log( \sprintf( $FMT3, $httpMethod, $requestUri ), self::WARNING );
                self::log( $request, self::WARNING );
                return $response->withStatus( 404 );
                break;
            case Dispatcher::METHOD_NOT_ALLOWED :
                $requestUri = $request->getAttribute( self::REQUESTTARGET, '/' );
                self::log( \sprintf( $FMT4, $httpMethod, $requestUri ), self::WARNING );
                self::log( $request, self::WARNING );
                $allowedMethods = RequestMethodHandler::extendAllowedMethods(
                    $request,
                    $serviceInfo[1]
                );
                return RequestMethodHandler::setStatusMethodNotAllowed(
                    $response,
                    $allowedMethods
                );
                break;
            case Dispatcher::FOUND :
                if ( ! empty( $serviceInfo[2] )) {
                    $request = self::updateRequest(
                        $request,
                        $serviceInfo[2],
                        $httpMethod
                    );
                }
                $handler = $serviceInfo[1];
                return $handler( $request, $response );
                break;
        } // end switch
        return $response;
    }

    /**
     * Return uri from requestTarget (with baseUri eliminated)
     *
     * @param ServerRequestInterface $request
     * @return string
     * @access private
     */
    private function getUriFromRequestTarget(
        ServerRequestInterface $request
    ) {
        static $FILE = 'file';
        static $Q    = '?';
        static $S    = '/';
        $uri = $request->getRequestTarget();
        if ( false !== ( $pos = \strpos( $uri, $Q ) ) ) {
            $uri = \substr( $uri, 0, $pos );
        }
        $baseUri = ( isset( $this->config[self::BASEURI] ) )
            ? $this->config[self::BASEURI]
            : \basename( ( \array_slice( \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), -1 ) )[0][$FILE] );
        if ( ! empty( $baseUri ) &&
            ( false !== \strpos( $uri, $baseUri ) ) ) {
            $uriParts = \explode( $S, $uri );
            $found = false;
            foreach ( $uriParts as $x => $uriPart ) {
                if ( $baseUri == $uriPart ) {
                    $found = true;
                    break;
                }
            } // end foreach
            if ( $found ) {
                $uriParts = \array_slice( $uriParts, ( $x + 1 ) );
            }
            $uri = $S . \implode( $S, $uriParts );
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
     * @todo mgnt of parsedBody array error
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
        $isQueryMethod = ( \in_array( $httpMethod, $queryMethods ) );
        if ( null === $parsedBody ) {
            $parsedBody = [];
        }
        foreach ( $arguments as $k => $v ) {
            $parsedBody[$k] = $v;
            if ( $isQueryMethod ) {
                $queryParams[$k] = $v;
            }
        } // end foreach
        return $request->withQueryParams( $queryParams )->withParsedBody( $parsedBody );
    }

    /**
     * Return new stream, opt with data
     *
     * @param null|string $data
     * @return Stream
     * @throws RuntimeException on stream write error
     * @static
     */
    public static function getNewStream(
        $data = null
    ) {
        static $arg1 = 'php://memory';
        static $arg2 = 'wb+';
        $stream = new Stream( $arg1, $arg2 );
        if ( null !== $data ) {
            $stream->write( $data );
        }
        return $stream;
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
                'service' => [
                    'server'    => get_class() . ' ' . self::$version,
                    'copyright' => '2018 Kjell-Inge Gustafsson, kigkonsult, All rights reserved',
                    'time'      => date('Y-m-d H:i:s' ),
                ],
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
        return [
            self::METHOD   => [
                RequestMethodHandler::METHOD_GET,
            ],
            self::URI      => '/ping',
            self::CALLBACK => [
                'self', 'pingService',
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
