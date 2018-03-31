<?php
/**
 * restServer, a PSR HTTP Message rest server implementation
 *
 * This file is a part of restServer.
 *
 * Copyright 2018 Kjell-Inge Gustafsson, kigkonsult, All rights reserved
 * Link      http://kigkonsult.se/restServer/index.php
 * Version   0.8.4
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

use PHPUnit\Framework\TestCase;          // PHPUnit > 6.1.0
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * key constants
 */
define( 'KEY',        'key' );

define( 'HANDLERCNT', 'handlerCnt' );

/**
 * class for handlers
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
        if ( empty( $this->value1 ) ) {
            $this->value1 = \serialize( $request->getParsedBody() );
        }

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

class RestServerTest extends TestCase
{
    /**
     * testRestServer0 provider
     */
    public function RestServer0Provider()
    {
        $dataArr = [];
        $HandlerClass = new HandlerClass();
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
            ],
            [  // server
               'REQUEST_URI'          => 'http://anyHost.com/index.php/user',
               'SERVER_NAME'          => 'anyHost.com',
               'REQUEST_METHOD'       => 'GET',
               'HTTP_ACCEPT'          => 'text/plain',
               'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            null,       // body
            null,       // cookies
            null,       // files
            null,       // expected
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

        $dataArr[0] = $dataTemplate; // --------------- test data set #2
        // anonymous function
        $dataArr[0][0][RestServer::SERVICES][] = [
            RestServer::METHOD   => 'GET',
            RestServer::URI      => '/user',
            RestServer::CALLBACK => $GLOBALS['callback2'],
        ];
        $data = [
            'param2' => 'test2'
        ];
        $dataArr[0][3] = $data;
        $dataArr[0][6] = \serialize( $data );

        $dataArr[0] = $dataTemplate; // --------------- test data set #3
        // instantiated object+method, passed as an array: object, method name
        $ServiceClass3 = new ServiceClass3();
        $dataArr[0][0][RestServer::SERVICES][] = [
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
        $dataArr[0][3] = $data;
        $dataArr[0][6] = \serialize( $data );


        $dataArr[0] = $dataTemplate; // --------------- test data set #4
        // class name and static method, passed as an array: class, method name (factory method?)
        $dataArr[0][0][RestServer::SERVICES][] = [
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
        $dataArr[0][3] = $data;
        $dataArr[0][6] = \serialize( $data );

        $dataArr[0] = $dataTemplate; // --------------- test data set #5
        // service as instantiated object, class has an (magic) __call method
        $ServiceClass5 = new ServiceClass5();
        $dataArr[0][0][RestServer::SERVICES][] = [
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
        $dataArr[0][3] = $data;
        $dataArr[0][6] = \serialize( $data );

        $dataArr[0] = $dataTemplate; // --------------- test data set #6
        // service as class name, class has an (magic) __callStatic method
        $dataArr[0][0][RestServer::SERVICES][] = [
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
        $dataArr[0][3] = $data;
        $dataArr[0][6] = \serialize( $data );

        $dataArr[0] = $dataTemplate; // --------------- test data set #7
        // service as instantiated object, class has an (magic) __invoke method
        $ServiceClass7 = new ServiceClass7();
        $dataArr[0][0][RestServer::SERVICES][] = [
            RestServer::METHOD   => 'GET',
            RestServer::URI      => '/user',
            RestServer::CALLBACK => $ServiceClass7,
        ];
        $data = [
            'param7' => 'test7'
        ];
        $dataArr[0][3] = $data;
        $dataArr[0][6] = \serialize( $data );

        return $dataArr;
    }

    /**
     * test RestServer0 - method && route found, testing all possible callbacks from config
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
        if ( LOG ) {
            $RestServer->setLogger( new RestServerLogger());
        }
        $response = $RestServer->serverCallback(
            $RestServer->server->request,
            $RestServer->server->response
        );

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
        if ( LOG ) {
            $RestServer->setLogger( new RestServerLogger());
        }

        list( $method, $uri, $callback ) = \array_values( $config[RestServer::SERVICES][1] );
        $this->assertTrue( $RestServer->detachRestService( $method, $uri ));

        $response = $RestServer->serverCallback(
            $RestServer->server->request,
            $RestServer->server->response
        );

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
        if ( LOG ) {
            $RestServer->setLogger( new RestServerLogger());
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

        $response = $RestServer->serverCallback(
            $RestServer->server->request,
            $RestServer->server->response
        );

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
        if ( LOG ) {
            $RestServer->setLogger( new RestServerLogger());
        }

        $response = $RestServer->serverCallback(
            $RestServer->server->request,
            $RestServer->server->response
        );

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
        if ( LOG ) {
            $RestServer->setLogger( new RestServerLogger());
        }
        $RestServer->addHandler( $handler );

        $response = $RestServer->serverCallback(
            $RestServer->server->request,
            $RestServer->server->response
        );

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
        if ( LOG ) {
            $RestServer->setLogger( new RestServerLogger());
        }

        $ServiceClass3->registerAsRestService( $RestServer->getAttachRestServiceCallback());
        $this->assertTrue( $RestServer->detachRestService( ServiceClass3::METHOD, ServiceClass3::ROUTE ));

        $response = $RestServer->serverCallback(
            $RestServer->server->request,
            $RestServer->server->response
        );

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
}
