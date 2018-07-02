<?php
/**
 * restServer, a PSR HTTP Message rest server implementation
 *
 * This file is a part of restServer.
 *
 * Copyright 2018 Kjell-Inge Gustafsson, kigkonsult, All rights reserved
 * Link      http://kigkonsult.se/restServer/index.php
 * Version   0.9.123
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

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Kigkonsult\RestServer\Handlers\ContentTypeHandler;
use Kigkonsult\RestServer\Handlers\EncodingHandler;
use Exception;

/**
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */

/**
 * key constants
 */
define( 'KEY',        'key' );

define( 'HANDLERCNT', 'handlerCnt' );

/**
 * class for handlers
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */
class HandlerClass
{
    /**
     * test value and key
     */
    public $value1 = null;

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     */
    public function templateHandler1(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $this->value1 = \serialize( $request->getParsedBody());

        $cnt     = $request->getAttribute( HANDLERCNT, 0 );
        $request = $request->withAttribute( HANDLERCNT, ( $cnt + 1 ));

        $request = $request->withAttribute( KEY, $this->value1 );

        return [
            $request,
            $response,
        ];
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     */
    public function templateHandler2(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $cnt     = $request->getAttribute( HANDLERCNT, 0 );
        $request = $request->withAttribute( HANDLERCNT, ($cnt + 1 ));

        return [
            $request,
            $response,
        ];
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     */
    public function templateHandler3(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $cnt     = $request->getAttribute( HANDLERCNT, 0 );
        $request = $request->withAttribute( HANDLERCNT, ($cnt + 1 ));

        return [
            $request,
            $response,
        ];
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @throws Exception
     */
    public function templateHandler4Exception(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        if( true )
            throw new Exception( 'testing');

        return [
            $request,
            $response,
        ];
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @throws Exception
     */
    public function templateHandler4Err(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $ErrorClass = new ErrorClass();
        $ErrorClass->getValues()['test'] = 'test';

        return [
            $request->withAttribute( 'test', $ErrorClass->getValues()['test'] ),
            $response,
        ];
    }
}
class ErrorClass
{
    private $values = [];

    public function getValues() {
        return $this->values;
    }
}

/**
 * 1 service as function
 */
function callback1(
    ServerRequestInterface $request,
    ResponseInterface      $response
) {
    $msg = $request->getAttribute( KEY, null );

    return $response->withRawBody( $msg );
};

/**
 * 2 service as anonymous function
 */
$callback2 = function(
    ServerRequestInterface $request,
    ResponseInterface      $response
) {
    $msg = $request->getAttribute( KEY, null );

    return $response->withRawBody( $msg );
};

/**
 * 3 service as instantiated object+method, passed as an array: object, method name
 */
class ServiceClass3
{
    const METHOD = 'POST';

    const ROUTE = '/';

    const CALLBACK = 'templateService3';

    /**
     * Register method as rest service callback
     *
     * @param callable $callback
     */
    public function registerAsRestService(
        $callback
    ) {
        $callback(
            self::METHOD,
            self::ROUTE,
            [
                $this,
                self::CALLBACK,
            ]
        );
    }

    /**
     * Rest service callback
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return ResponseInterface
     */
    public function templateService3(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        $msg = $request->getAttribute( KEY, null );

        return $response->withRawBody( $msg );
    }

    /**
     * Rest service callback throwing exception
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return ResponseInterface
     * @throws Exception
     */
    public function templateService3Exception(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        if( true )
            throw new Exception( 'testing');

        $msg = $request->getAttribute( KEY, null );

        return $response->withRawBody( $msg );
    }

    /**
     * Rest service callback with php error
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return ResponseInterface
     * @throws Exception
     */
    public function templateService3Err(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        $ErrorClass = new ErrorClass();
        $ErrorClass->getValues()['test'] = 'test';

        return $response->withRawBody( $ErrorClass->getValues()['test'] );
    }
}

/**
 * 4 service as class name and static method, passed as an array: class, method name (factory method?)
 */
class ServiceClass4
{
    /**
     * Rest service callback
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return ResponseInterface
     */
    public static function factoryService4(
    ServerRequestInterface $request,
    ResponseInterface      $response
    ) {
        $msg = $request->getAttribute( KEY, null );

        return $response->withRawBody( $msg );
    }
}
/**
 * 5 service as instantiated object, class has an (magic) __call method
 */
class ServiceClass5
{
    /**
     * Rest service callback
     *
     * @param string $name
     * @param array  $arguments
     * @return ResponseInterface
     */
    public function __call(
        $name,
        $arguments
    ) {
        list( $request, $response ) = $arguments;

        $msg = $request->getAttribute( KEY, null );

        return $response->withRawBody( $msg );
    }

}

/*
6 service as class name, class has an (magic) __callStatic method
*/
class ServiceClass6
{
    /**
     * Rest service callback
     *
     * @param string $name
     * @param array  $arguments
     * @return ResponseInterface
     * @access static
     */
    public static function __callStatic(
        $name,
        $arguments
    ) {
        list( $request, $response ) = $arguments;

        $msg = $request->getAttribute( KEY, null );

        return $response->withRawBody( $msg );
    }

}

/*
7 service as instantiated object, class has an (magic) __invoke method
*/
class ServiceClass7
{
    /**
     * Rest service callback
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return ResponseInterface
     * @access static
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        $msg = $request->getAttribute( KEY, null );

        return $response->withRawBody( $msg );
    }

}

/**
 * Final handler, may be like any callable above
 */
class FinalHandlerClass
{
    /**
     * Final handler method
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return bool
     */
    public function finalHandlerMethod(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        return true;
    }
}

/**
 * class RestServerTest
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */
class RestServerTest extends TestCase
{
    /**
     * testRestServer0 provider
     */
    public function RestServer0Provider()
    {
        $dataArr = [];
        $HandlerClass      = new HandlerClass();
        $FinalHandlerClass = new FinalHandlerClass();
        $dataTemplate = [
            [  // config
               RestServer::DEBUG    => true,
               RestServer::BASEURI  => 'index.php',
               RestServer::HANDLERS => [
                   [  // handler 1
                      $HandlerClass,
                      'templateHandler1',
                   ],
               ],
               RestServer::FINALHANDLER => [
                   $FinalHandlerClass,
                   'finalHandlerMethod'
               ]
            ],
            [  // server
               'REQUEST_URI'          => 'http://anyHost.com/index.php/user',
               'SERVER_NAME'          => 'anyHost.com',
               'REQUEST_METHOD'       => 'GET',
               'HTTP_ACCEPT'          => 'text/plain',
               'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            null,       // body - 3
            null,       // cookies
            null,       // files
            null,       // expected - 6
        ];

        $dataArr[0] = $dataTemplate; // --------------- test data set #1
        // function
        $dataArr[0][0][RestServer::SERVICES][] = [
            RestServer::METHOD   => 'GET',
            RestServer::URI      => '/user',
            RestServer::CALLBACK => function (
                ServerRequestInterface $request,
                ResponseInterface      $response
            ) {
                $msg = $request->getAttribute( KEY, null );

                return $response->withRawBody( $msg );
            },
        ];
        $data = [
            'param2' => 'test2'
        ];
        $dataArr[0][3] = $data;
        $dataArr[0][6] = \serialize( $data );

        $dataArr[1] = $dataTemplate; // --------------- test data set #2
        // anonymous function
        $dataArr[1][0][RestServer::SERVICES][] = [
            RestServer::METHOD   => 'GET',
            RestServer::URI      => '/user',
            RestServer::CALLBACK => $GLOBALS['callback2'],
        ];
        $data = [
            'param2' => 'test2'
        ];
        $dataArr[1][3] = $data;
        $dataArr[1][6] = \serialize( $data );

        $dataArr[2] = $dataTemplate; // --------------- test data set #3
        // instantiated object+method, passed as an array: object, method name
        $ServiceClass3 = new ServiceClass3();
        $dataArr[2][0][RestServer::SERVICES][] = [
            RestServer::METHOD   => 'GET',
            RestServer::URI      => '/user',
            RestServer::CALLBACK => [
                $ServiceClass3,
                'templateService3',
            ],
        ];
        $data = [
            'param3' => 'test3'
        ];
        $dataArr[2][3] = $data;
        $dataArr[2][6] = \serialize( $data );

        $dataArr[3] = $dataTemplate; // --------------- test data set #4
        // class name and static method, passed as an array: class, method name (factory method?)
        $dataArr[3][0][RestServer::SERVICES][] = [
            RestServer::METHOD   => 'GET',
            RestServer::URI      => '/user',
            RestServer::CALLBACK => [
                'Kigkonsult\\RestServer\\ServiceClass4',
                'factoryService4',
            ],
        ];
        $data = [
            'param4' => 'test4'
        ];
        $dataArr[3][3] = $data;
        $dataArr[3][6] = \serialize( $data );

        $dataArr[4] = $dataTemplate; // --------------- test data set #5
        // service as instantiated object, class has an (magic) __call method
        $ServiceClass5 = new ServiceClass5();
        $dataArr[4][0][RestServer::SERVICES][] = [
            RestServer::METHOD   => 'GET',
            RestServer::URI      => '/user',
            RestServer::CALLBACK => [
                $ServiceClass5,
                'restService5',
            ],
        ];
        $data = [
            'param5' => 'test5'
        ];
        $dataArr[4][3] = $data;
        $dataArr[4][6] = \serialize( $data );

        $dataArr[5] = $dataTemplate; // --------------- test data set #6
        // service as class name, class has an (magic) __callStatic method
        $dataArr[5][0][RestServer::SERVICES][] = [
            RestServer::METHOD   => 'GET',
            RestServer::URI      => '/user',
            RestServer::CALLBACK => [
                'Kigkonsult\\RestServer\\ServiceClass6',
                'restService6',
            ],
        ];
        $data = [
            'param6' => 'test6'
        ];
        $dataArr[5][3] = $data;
        $dataArr[5][6] = \serialize( $data );

        $dataArr[6] = $dataTemplate; // --------------- test data set #7
        $HandlerClass->value1 = null;
        // service as instantiated object, class has an (magic) __invoke method
        $ServiceClass7 = new ServiceClass7();
        $dataArr[6][0][RestServer::SERVICES][] = [
            RestServer::METHOD   => 'GET',
            RestServer::URI      => '/user',
            RestServer::CALLBACK => $ServiceClass7,
        ];
        $data = [
            'param7' => 'test7'
        ];
        $dataArr[6][3] = $data;
        $dataArr[6][6] = \serialize( $data );

        return $dataArr;
    }

    /**
     * test RestServer0 - method && route found, testing all possible callbacks from config + finalHandler
     *
     * @test
     * @dataProvider RestServer0Provider
     */
    public function testRestServer0(
        array $config,
        array $server   = null,
        array $query    = null,
        $body           = null,
        array $cookies  = null,
        array $files    = null,
        $expected       = null
    ) {
        $RestServer = new RestServer( $config, $server, $query, $body, $cookies, $files );
        if ( defined( 'LOG' ) && LOG ){
            RestServer::setLogger( new RestServerLogger());
        }
        if( isset( $config[RestServer::FINALHANDLER] ))
            $RestServer->addFinalHandler( $config[RestServer::FINALHANDLER] );

        $response = $RestServer->processRequest();

        $this->assertEquals( 200, $response->getStatusCode());
        $body2 = $response->getBody();
        $body2->rewind();
        $data2 = $body2->getContents();

        $this->assertEquals( $expected, $data2 );
        $RestServer->__destruct();
    }

    /**
     * testRestServer1a provider
     */
    public function RestServer1aProvider()
    {
        $dataArr       = [];

        $HandlerClass  = new HandlerClass(); // --------------- test data set #1
        $ServiceClass3 = new ServiceClass3();
        $data          = [
            'param1' => 'test1'
        ];
        $dataArr[]     = [
            $ServiceClass3,
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::HANDLERS => [
                    [  // handler 1
                        $HandlerClass,
                        'templateHandler1',
                    ],
                    [  // handler 2
                        $HandlerClass,
                        'templateHandler2',
                    ],
                ],
                RestServer::SERVICES => [
                    [  // service definition 1
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user',
                        RestServer::CALLBACK => [
                            $ServiceClass3,
                            'templateService3',
                        ],
                    ],
                    [  // service definition 2, detached, no test on this
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user/{id:\w+}/dummy',
                        RestServer::CALLBACK => [
                            $ServiceClass3,
                            'templateService3',
                        ],
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'GET',
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
            \serialize( $data ),
        ];

        $HandlerClass  = new HandlerClass(); // --------------- test data set #2
        $ServiceClass3 = new ServiceClass3();
        $data         = [
            'param2' => 'test2',
        ];
        $dataArr[]    = [
            $ServiceClass3,
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::HANDLERS => [
                    [  // handler 1
                        $HandlerClass,
                        'templateHandler1',
                    ],
                    [  // handler 2
                        $HandlerClass,
                        'templateHandler2',
                    ],
                ],
                RestServer::SERVICES => [
                    [  // service definition 1
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user[/{id:\w+}]',
                        RestServer::CALLBACK => [
                            $ServiceClass3,
                            'templateService3',
                        ],
                    ],
                    [  // service definition 2, detached, no test on this
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user/{id:\w+}/dummy',
                        RestServer::CALLBACK => [
                            $ServiceClass3,
                            'templateService3'
                        ],
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user/testUser',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'GET',
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
            \serialize( $data ),
        ];

        $HandlerClass  = new HandlerClass(); // --------------- test data set #3
        $ServiceClass3 = new ServiceClass3();
        $data         = [
            'param3' => 'test3',
        ];
        $dataArr[]    = [
            $ServiceClass3,
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::HANDLERS => [
                    [  // handler 1
                        $HandlerClass,
                        'templateHandler1',
                    ],
                    [  // handler 2
                        $HandlerClass,
                        'templateHandler2',
                    ],
                ],
                RestServer::SERVICES => [
                    [  // service definition 1
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user[/{id:\w+}]',
                        RestServer::CALLBACK => [
                            $ServiceClass3,
                            'templateService3',
                        ],
                    ],
                    [  // service definition 2, detached, no test on this
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user/{id:\w+}/dummy',
                        RestServer::CALLBACK => [
                            $ServiceClass3,
                            'templateService3',
                        ],
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user?id=testUser',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'GET',
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
            \serialize( $data ),
        ];

        return $dataArr;
    }

    /**
     * test RestServer1a - method && route found
     * same test as RestServer1b but attach services using config
     * NO detachRestService() test here,
     *
     * @test
     * @dataProvider RestServer1aProvider
     */
    public function testRestServer1a(
        ServiceClass3 $ServiceClass3,
                array $config,
                array $server   = null,
                array $query    = null,
                      $body     = null,
                array $cookies  = null,
                array $files    = null,
                      $expected = null
    ) {
        $RestServer = new RestServer( $config, $server, $query, $body, $cookies, $files );
        if ( defined( 'LOG' ) && LOG ) {
            RestServer::setLogger( new RestServerLogger());
        }

        list( $method, $uri, $callback ) = \array_values( $config[RestServer::SERVICES][1] );
        $this->assertTrue( $RestServer->detachRestService( $method, $uri ));

        $response = $RestServer->processRequest();

        $this->assertEquals( 200, $response->getStatusCode());
        $body2 = $response->getBody();
        $body2->rewind();
        $data2 = $body2->getContents();

        $this->assertEquals( $expected, $data2 );
        $RestServer->__destruct();
    }

    /**
     * testRestServer1ba provider
     */
    public function RestServer1bProvider()
    {
        $dataArr       = [];

        $HandlerClass  = new HandlerClass(); // --------------- test data set #1
        $ServiceClass3 = new ServiceClass3();
        $data          = ['param' => 'test'];
        $replyVal      = \serialize( $data );
        $dataArr[]     = [
            $ServiceClass3,
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::HANDLERS => [
                    [  // handler 1
                        $HandlerClass,
                        'templateHandler1',
                    ],
                    [  // handler 2
                        $HandlerClass,
                        'templateHandler2',
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'GET',
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
            $replyVal,
            [
                [  // service definition 1
                    RestServer::METHOD   => 'GET',
                    RestServer::URI      => '/user',
                    RestServer::CALLBACK => [
                        $ServiceClass3,
                        'templateService3',
                    ],
                ],
                [  // service definition 2, detached, no test on this
                    RestServer::METHOD   => 'GET',
                    RestServer::URI      => '/user/{id:\w+}/dummy',
                    RestServer::CALLBACK => [
                        $ServiceClass3,
                        'templateService3'
                    ],
                ],
            ],
        ];

        $HandlerClass  = new HandlerClass(); // --------------- test data set #2
        $ServiceClass3 = new ServiceClass3();
        $data         = [
            'param' => 'test',
        ];
        $replyVal     = \serialize(
            $data + [
            'id' => 'testUser',
            ]
        );
        $dataArr[]    = [
            $ServiceClass3,
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::HANDLERS => [
                    [  // handler 1
                        $HandlerClass,
                        'templateHandler1',
                    ],
                    [  // handler 2
                        $HandlerClass,
                        'templateHandler2',
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user/testUser',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'GET',
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
            \serialize( $data ),
            [
                [  // service definition 1
                    RestServer::METHOD   => 'GET',
                    RestServer::URI      => '/user[/{id:\w+}]',
                    RestServer::CALLBACK => [
                        $ServiceClass3,
                        'templateService3',
                    ],
                ],
                [  // service definition 2, detached, no test on this
                    RestServer::METHOD   => 'GET',
                    RestServer::URI      => '/user/{id:\w+}/dummy',
                    RestServer::CALLBACK => [
                        $ServiceClass3,
                        'templateService3',
                    ],
                ],
            ],
        ];

        $HandlerClass  = new HandlerClass(); // --------------- test data set #3
        $ServiceClass3 = new ServiceClass3();
        $data         = [
            'param' => 'test',
        ];
        $replyVal     = \serialize(
            $data + [
            'id' => 'testUser',
            ]
        );
        $dataArr[]    = [
            $ServiceClass3,
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::HANDLERS => [
                    [ // handler 1
                        $HandlerClass,
                        'templateHandler1',
                    ],
                    [  // handler 2
                         $HandlerClass,
                        'templateHandler2',
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user?id=testUser',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'GET',
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
            \serialize( $data ),
            [
                [  // service definition 1
                    RestServer::METHOD   => 'GET',
                    RestServer::URI      => '/user[/{id:\w+}]',
                    RestServer::CALLBACK => [
                        $ServiceClass3,
                        'templateService3',
                    ],
                ],
                [  // service definition 2, detached, no test on this
                    RestServer::METHOD   => 'GET',
                    RestServer::URI      => '/user/{id:\w+}/dummy',
                    RestServer::CALLBACK => [
                        $ServiceClass3,
                        'templateService3',
                    ],
                ],
            ],
        ];

        return $dataArr;
    }

    /**
     * test RestServer1b - method && route found
     * same test as RestServer1a but attach services using attachRestService
     * Includes detachRestService() test here
     *
     * @test
     * @dataProvider RestServer1bProvider
     */
    public function testRestServer1b(
        ServiceClass3 $ServiceClass3,
                array $config,
                array $server   = null,
                array $query    = null,
                      $body     = null,
                array $cookies  = null,
                array $files    = null,
                      $expected = null,
                array $services
    ) {
        $RestServer = new RestServer( null, $server, $query, $body, $cookies, $files );
        $RestServer->setConfig( $config );
        if ( defined( 'LOG' ) && LOG ) {
            RestServer::setLogger( new RestServerLogger());
        }

        foreach ( $services as $x => $service ) {
            if ( empty( $x )) {
                list( $method, $uri, $callback ) = \array_values( $service );
                $RestServer->attachRestService( $method, $uri, $callback );
            } else {
                $RestServer->attachRestService( $service );
            }
        }

        list( $method, $uri, $callback ) = \array_values( $services[1] );
        $RestServer->detachRestService( $method, $uri );

        $response = $RestServer->processRequest();

        $this->assertEquals( 200, $response->getStatusCode());
        $body2 = $response->getBody();
        $body2->rewind();
        $data2 = $body2->getContents();

        $this->assertEquals( $expected, $data2 );
        $RestServer->__destruct();
    }

    /**
     * testRestServer2 provider
     */
    public function RestServer2Provider()
    {
        $dataArr       = [];

        $HandlerClass  = new HandlerClass(); // --------------- test data set #1
        $ServiceClass3 = new ServiceClass3();
        $data          = ['test'];
        $dataArr[]     = [
            $ServiceClass3,
            [  // config
                RestServer::DEBUG    => true,
                RestServer::HANDLERS => [
                    [  // handler 1
                        $HandlerClass,
                        'templateHandler1',
                    ],
                    [  // handler 2
                        $HandlerClass,
                        'templateHandler2',
                    ],
                ],
                RestServer::SERVICES => [
                    [  // service definition 1
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user',
                        RestServer::CALLBACK => [
                            $ServiceClass3,
                            'templateService3',
                        ],
                    ],
                ],
            ],
            [  // server
               'REQUEST_URI'          => 'http://anyHost.com/address', // no callback here
               'SERVER_NAME'          => 'anyHost.com',
               'REQUEST_METHOD'       => 'GET',
               'HTTP_ACCEPT'          => 'text/plain',
               'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
        ];

        return $dataArr;
    }

    /**
     * test RestServer2 - method found && route NOT found (i.e. FastRoute\BadRouteException)
     *
     * @test
     * @dataProvider RestServer2Provider
     */
    public function testRestServer2(
        ServiceClass3 $ServiceClass3,
                array $config,
                array $server  = null,
                array $query   = null,
                      $body    = null,
                array $cookies = null,
                array $files   = null
    ) {
        $RestServer = new RestServer( null, $server, $query, $body, $cookies, $files );
        $RestServer->setConfig( $config );
        if ( defined( 'LOG' ) && LOG ) {
            RestServer::setLogger( new RestServerLogger());
        }

        $response = $RestServer->processRequest();

        $this->assertEquals( 404, $response->getStatusCode());
        $RestServer->__destruct();
    }

    /**
     * testRestServer3 provider
     */
    public function RestServer3Provider()
    {
        $dataArr       = [];

        $HandlerClass  = new HandlerClass(); // --------------- test data set #1
        $ServiceClass3 = new ServiceClass3();
        $data          = ['test'];
        $dataArr[]     = [
            $ServiceClass3,
            [  // handler 1
                $HandlerClass,
               'templateHandler1',
            ],
            [  // config
                RestServer::DEBUG    => true,
                RestServer::HANDLERS => [
                    [ // handler 2
                         $HandlerClass,
                        'templateHandler2',
                    ],
                    [  // handler 3
                         $HandlerClass,
                        'templateHandler3',
                    ],
                ],
                RestServer::SERVICES => [
                    [  // service definition 1
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user',
                        RestServer::CALLBACK => [
                            $ServiceClass3,
                            'templateService3',
                        ],
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/user',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'POST',    // no callback here
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
        ];

        return $dataArr;
    }

    /**
     * test RestServer3 - method NOT found && route NOT found (i.e. FastRouteException)
     *
     * @test
     * @dataProvider RestServer3Provider
     */
    public function testRestServer3(
        ServiceClass3 $ServiceClass3,
                array $handler,
                array $config,
                array $server  = null,
                array $query   = null,
                      $body    = null,
                array $cookies = null,
                array $files   = null
    ) {
        $RestServer = new RestServer( null, $server, $query, $body, $cookies, $files );
        $RestServer->setConfig( $config );
        if ( defined( 'LOG' ) && LOG ) {
            RestServer::setLogger( new RestServerLogger());
        }
        $RestServer->addHandler( $handler );

        $response = $RestServer->processRequest();

        $this->assertEquals( 405, $response->getStatusCode());
        $RestServer->__destruct();
    }

    /**
     * testRestServer4 provider
     */
    public function RestServer4Provider()
    {
        $dataArr       = [];

        $HandlerClass  = new HandlerClass(); // --------------- test data set #1
        $ServiceClass3 = new ServiceClass3();
        $data          = [
            'param' => 'test',
        ];
        $dataArr[]     = [
            $ServiceClass3,
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::HANDLERS => [
                    [ // handler 1
                         $HandlerClass,
                        'templateHandler1',
                    ],
                    [ // handler 2
                        $HandlerClass,
                        'templateHandler2',
                    ],
                ],
                RestServer::SERVICES => [
                    [  // service definition 1
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user',
                        RestServer::CALLBACK => [
                            $ServiceClass3,
                            'templateService3',
                        ],
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'OPTIONS',
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
            [           // expected
               'GET',
               'HEAD',
               'OPTIONS',
            ],
        ];

        return $dataArr;
    }

    /**
     * test RestServer4 - OPTIONS (no preflight), check response body
     * Could (or should) be placed in RequestMethodHandlerTest...
     * Preflight tests placed in CorsHandlerTest
     *
     * @test
     * @dataProvider RestServer4Provider
     */
    public function testRestServer4(
        ServiceClass3 $ServiceClass3,
                array $config,
                array $server = null,
                array $query = null,
                      $body = null,
                array $cookies = null,
                array $files = null,
                      $expected = null
    ) {
        $RestServer = new RestServer( null, $server, $query, $body, $cookies, $files );
        $RestServer->setConfig( $config );
        if ( defined( 'LOG' ) && LOG ) {
            RestServer::setLogger( new RestServerLogger());
        }

        $ServiceClass3->registerAsRestService( $RestServer->getAttachRestServiceCallback());
        $this->assertTrue( $RestServer->detachRestService( ServiceClass3::METHOD, ServiceClass3::ROUTE ));

        $response = $RestServer->processRequest();

        $this->assertEquals( 200, $response->getStatusCode());

        $headerMethods = \explode( ',', $response->getHeader( 'Allow' )[0] );
        foreach ( $headerMethods as &$method ) {
            $method  = \trim( $method );
        }
        foreach ( $headerMethods as $method ) {
            $this->assertTrue( \in_array( $method, $expected ));
        }

        $body1       = $response->getBody();
        $body1->rewind();
        $body2       = $body1->getContents();
        $body3       = (array) @\json_decode( $body2, true );
        foreach ( \array_keys( $body3 ) as $method ) {
            $this->assertTrue( \in_array( $method, $expected ));
        }
        $this->assertSame( $body3['GET'], $body3['HEAD'] );
        $RestServer->__destruct();
    }

    /**
     * testRestServer5 provider
     */
    public function RestServer5Provider()
    {
        $data          = [
            'param5' => 'test5',
        ];
        $dataArr       = [];

        $dataArr[]     = [ // test set #1
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::SERVICES =>[
                    [
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user',
                        RestServer::CALLBACK => function (
                            ServerRequestInterface $request,
                            ResponseInterface      $response
                        ) {
                            $msg = $request->getParsedBody();

                            return $response->withRawBody( $msg );
                        },
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'GET',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
            $data,      // expected
        ];
        $dataArr[]     = [ // test set #2
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                EncodingHandler::ACCEPTENCODING => [EncodingHandler::FALLBACK    => 'gzip'],
                ContentTypeHandler::ACCEPT      => [ContentTypeHandler::FALLBACK => 'application/json'],
                RestServer::SERVICES => [
                    [
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user',
                        RestServer::CALLBACK => function (
                            ServerRequestInterface $request,
                            ResponseInterface      $response
                        ) {
                            $msg = $request->getParsedBody();

                            return $response->withRawBody( $msg );
                        },
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'GET',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
            $data,      // expected
        ];

        return $dataArr;
    }
    /**
     * test RestServer5 - testing fallback (defaults) are set for response body (content-typ/gzip)
     *
     * @test
     * @dataProvider RestServer5Provider
     */
    public function testRestServer5(
        array $config,
        array $server  = null,
        array $query   = null,
        $body          = null,
        array $cookies = null,
        array $files   = null,
        $expected      = null
    ) {
        $contentType = 'application/json';
        $RestServer = new RestServer( $config, $server, $query, $body, $cookies, $files );
        if ( defined( 'LOG' ) && LOG ) {
            RestServer::setLogger( new RestServerLogger());
        }

        $response = $RestServer->processRequest();

        $this->assertEquals( 200, $response->getStatusCode());
        $this->assertEquals( $contentType, $response->getHeader( ContentTypeHandler::CONTENTTYPE )[0] );

        $body1       = $response->getBody();
        $body1->rewind();
        $body2       = $body1->getContents();
        $body3       = @\gzdecode( $body2 );
        $body4       = (array) @\json_decode( $body3, true );
        $this->assertEquals( $expected, $body4 );
        $RestServer->__destruct();
    }

    /**
     * testRestServer6 provider
     */
    public function RestServer6Provider()
    {
        $dataArr       = [];

        $HandlerClass  = new HandlerClass(); // --------------- test data set #0 . exception
        $ServiceClass3 = new ServiceClass3();
        $dataArr[]    = [
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::HANDLERS => [
                    [  // handler 1
                        $HandlerClass,
                        'templateHandler4Exception',
                    ],
                ],
                RestServer::SERVICES => [
                    [  // service definition 1
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user[/{id:\w+}]',
                        RestServer::CALLBACK => [
                            $ServiceClass3,
                            'templateService3',
                        ],
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user?id=testUser',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'GET',
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            [],         // (array) body
            null,       // cookies
            null,       // files
        ];

        $HandlerClass  = new HandlerClass(); // --------------- test data set #1 . err
        $ServiceClass3 = new ServiceClass3();
        $dataArr[]    = [
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::HANDLERS => [
                    [  // handler 1
                        $HandlerClass,
                        'templateHandler4Err',
                    ],
                ],
                RestServer::SERVICES => [
                    [  // service definition 1
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user[/{id:\w+}]',
                        RestServer::CALLBACK => [
                            $ServiceClass3,
                            'templateService3',
                        ],
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user?id=testUser',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'GET',
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            [],         // (array) body
            null,       // cookies
            null,       // files
        ];

        return $dataArr;
    }

    /**
     * test RestServer6 - testing handler generating PHP err/throwing exception
     *
     * @test
     * @dataProvider RestServer6Provider
     */
    public function testRestServer6(
        array $config,
        array $server  = null,
        array $query   = null,
        $body          = null,
        array $cookies = null,
        array $files   = null
    ) {
        $RestServer = new RestServer( null, $server, $query, $body, $cookies, $files );
        $RestServer->setConfig( $config );
        if ( defined( 'LOG' ) && LOG ) {
            RestServer::setLogger( new RestServerLogger());
        }

        $response = $RestServer->processRequest();

        $this->assertEquals( 500, $response->getStatusCode());
        $RestServer->__destruct();
    }

    /**
     * testRestServer7 provider
     */
    public function RestServer7Provider()
    {
        $dataArr       = [];

        $HandlerClass  = new HandlerClass(); // --------------- test data set #0 - exception
        $ServiceClass3 = new ServiceClass3();
        $dataArr[]    = [
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::SERVICES => [
                    [  // service definition 1
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user[/{id:\w+}]',
                        RestServer::CALLBACK => [
                            $ServiceClass3,
                            'templateService3Exception',
                        ],
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user?id=testUser',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'GET',
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            [],         // (array) body
            null,       // cookies
            null,       // files
        ];

        $HandlerClass  = new HandlerClass(); // --------------- test data set #1 - php error
        $ServiceClass3 = new ServiceClass3();
        $dataArr[]    = [
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::SERVICES => [
                    [  // service definition 1
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user[/{id:\w+}]',
                        RestServer::CALLBACK => [
                            $ServiceClass3,
                            'templateService3Err',
                        ],
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user?id=testUser',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'GET',
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            [],         // (array) body
            null,       // cookies
            null,       // files
        ];

        return $dataArr;
    }

    /**
     * test RestServer7 - testing rest service callback generating PHP err/throwing exception
     *
     * @test
     * @dataProvider RestServer7Provider
     */
    public function testRestServer7(
        array $config,
        array $server  = null,
        array $query   = null,
        $body          = null,
        array $cookies = null,
        array $files   = null
    ) {
        $RestServer = new RestServer( null, $server, $query, $body, $cookies, $files );
        $RestServer->setConfig( $config );
        if ( defined( 'LOG' ) && LOG ) {
            RestServer::setLogger( new RestServerLogger());
        }

        $response = $RestServer->processRequest();

        $this->assertEquals( 500, $response->getStatusCode());
        $RestServer->__destruct();
    }

}
