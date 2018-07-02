<?php
/**
 * Created by PhpStorm.
 * User: kig
 * Date: 2018-04-22
 * Time: 18:06
 */

namespace Kigkonsult\RestServer\Handlers;

use PHPUnit\Framework\TestCase;
use Zend\Diactoros\ServerRequest;
use Kigkonsult\RestServer\Response;
use Kigkonsult\RestServer\RestServer;
use Kigkonsult\RestServer\StreamFactory;
use Kigkonsult\RestServer\RestServerLogger;
use Exception;

/**
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */

/**
 * Basic 2, username and password are checked using callback
 */
class BasicValidation2Class
{
    const PHP_AUTH_USER = 'userid';
    const PHP_AUTH_PW   = 'password';
    const CONSUMER      = 'consumer';

    /**
     * Validates userid, password...
     *
     * @param string $consumer
     * @param string $realm
     * @param string $userid
     * @param string $password
     * @param string $corrId
     * @return bool
     * @static
     */
    public static function validate2method(
        $consumer,
        $realm,
        $userid,
        $password,
        $corrId
    ) {
        if(( self::CONSUMER      == $consumer ) &&
           ( ! empty( $realm ))                 &&
           ( self::PHP_AUTH_USER == $userid )   &&
           ( self::PHP_AUTH_PW   == $password ) &&
            ( ! empty( $corrId ))) {
            return true;
        }
        return false;
    }

    /**
     * Validate method with php err
     *
     * @param string $consumer
     * @param string $realm
     * @param string $userid
     * @param string $password
     * @param string $corrId
     * @return bool
     */
    public function validate2methodErr(
        $consumer,
        $realm,
        $userid,
        $password,
        $corrId
    ) {
        $ErrorClass = new ErrorClass();
        $ErrorClass->getValues()['test'] = 'test';

        return self::validate2method(
            $ErrorClass->getValues()['test'],
            $realm,
            $userid,
            $password,
            $corrId
        );
    }

    /**
     * Testing throwing exception
     *
     * @return bool
     * @throws Exception
     */
    public function callbackThrowingsException()
    {
        if( true ) {
            throw new Exception( 'testing callback throwing exception' );
        }
        return true;
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
 * Digest 3, request params are checked using callback as well as response request expected header params
 */
class DigestValidation3Class
{
    const REALM       = 'This is the realm value';
    const DOMAIN      = 'example.com';
    const STALE       = 'true';

    const CONSUMER    = 'consumer';
    const USERNAME    = 'user';
    const PASSWORD    = 'password';
    const NONCE       = 'dcd98b7102dd2f0e8b11d0f600bfb0c093';
    const URI         = '/dir/index.html';
    const ALGORITHM   = 'MD5';
    const CNONCE      = '0a4f113b';
    const OPAQUE      = '5ccc069c403ebaf9f0171e9517f40e41';
    const QOP         = 'auth';
    const NC          = 1;
    const METHOD      = 'GET';
    /**
     * Validates userid, pasword...
     *
     * @param string $consumer
     * @param string $corrId
     * @return array
     * @static
     */
    public static function getResponseRequestExpectedHeaderParams(
        $consumer,
        $corrId
    ) {
        if(( self::CONSUMER      == $consumer ) &&
            ( ! empty( $corrId ))) {
            return [
                AuthenticationHandler::REALM     => self::REALM,
                AuthenticationHandler::DOMAIN    => self::DOMAIN,
                AuthenticationHandler::STALE     => self::STALE,
                AuthenticationHandler::ALGORITHM => self::ALGORITHM,
                AuthenticationHandler::QOP       => self::QOP,
            ];
        }
        else
            return [];
    }
    /**
     * Validates request auth header value(s)
     *
     * @param string $consumer
     * @param string $realm
     * @param string $userid
     * @param string $password
     * @param string $corrId
     * @return bool
     * @static
     */
    public static function validate3method(
        $consumer,
        $userid,
        $realm,
        $nonce,
        $uri,
        $response,
        $algorithm,
        $cnonce,
        $opaque,
        $qop,
        $nc,
        $method,
        $corrId
    ) {
        $colon     = ':';
        $HA1       = md5( DigestValidation3Class::USERNAME . $colon
                        . DigestValidation3Class::REALM    . $colon
                        . DigestValidation3Class::PASSWORD );
        $HA2       = md5( $method . $colon . DigestValidation3Class::URI );
        $response2 = md5( $HA1 . $colon
                        . DigestValidation3Class::NONCE  . $colon
                        . DigestValidation3Class::NC     . $colon
                        . DigestValidation3Class::CNONCE . $colon
                        . DigestValidation3Class::QOP    . $colon
                        . $HA2 );
        if(( self::CONSUMER     == $consumer )  &&
           ( self::USERNAME     == $userid )    &&
           ( self::REALM        == $realm )     &&
           ( self::NONCE        == $nonce )     &&
           ( self::URI          == $uri )       &&
           ( $response2         == $response )  &&
           ( self::ALGORITHM    == $algorithm ) &&
           ( self::CNONCE       == $cnonce )    &&
           ( self::OPAQUE       == $opaque )    &&
           ( self::QOP          == $qop )       &&
           ( self::NC           == $nc )        &&
           ( self::METHOD       == $method )    &&
           ( ! empty( $corrId ))) {
            return true;
        }
        return false;
    }
}

/**
 * class AuthenticationHandlerTest
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */
class AuthenticationHandlerTest extends TestCase
{
    /**
     * testValidateAuthentication1 provider
     */
    public function validateAuthentication1provider() {
        $dataArr   = [];

        $dataArr[] = [ // test set #0, NO auth at all
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
            ],
            200,
        ];

        $dataArr[] = [ // test set #1, Basic auth 1, userid+passwd in config, ok
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    RestServer::IGNORE                   => true, // skip testing headers
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'realm',
                    AuthenticationHandler::PHP_AUTH_USER => [
                        'user1' => 'passwd1',
                        'user2' => 'passwd2',
                    ],
                ],
            ],
            200,
        ];

        $dataArr[] = [ // test set #2, Basic auth 1, userid+passwd in config, err - realm missing
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    RestServer::IGNORE                   => true, // skip testing headers
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::PHP_AUTH_USER => [
                        'user' => 'passwd',
                    ],
                ],
            ],
            500,
        ];

        $dataArr[] = [ // test set #3, Basic auth 1, userid+passwd in config, err - user missing
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    RestServer::IGNORE                   => true, // skip testing headers
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'realm',
                    AuthenticationHandler::PHP_AUTH_USER => 'passwd'
                ]
            ],
            500,
        ];

        $dataArr[] = [ // test set #4, Basic auth 1, userid+passwd in config, err - passwd missing
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    RestServer::IGNORE                   => true, // skip testing headers
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'realm',
                    AuthenticationHandler::PHP_AUTH_USER => 'user',
                ]
            ],
            500,
        ];

        $dataArr[] = [ // test set #5, Basic auth 2, callback, ok
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    RestServer::IGNORE                   => true, // skip testing headers
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'realm',
                    RestServer::CALLBACK                 => [
                        // 1:st element, callback
                        function (
                            $userid,
                            $password,
                            $realm,
                            $corrId,
                            $customer
                        ) {
                            return true; // accept all
                        },
                        // 2:nd element, callback arg. description
                        [
                            AuthenticationHandler::PHP_AUTH_USER,
                            AuthenticationHandler::PHP_AUTH_PW,
                            AuthenticationHandler::REALM,
                            RestServer::CORRELATIONID,
                            'RestServer',
                        ]
                    ],
                ]
            ],
            200,
        ];

        $dataArr[] = [ // test set #6, Basic auth 2, callback, err - realm missing
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    RestServer::IGNORE                   => true, // skip testing headers
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    RestServer::CALLBACK                 => [
                        // 1:st element, callback
                        function (
                            $userid,
                            $password,
                            $realm,
                            $corrId,
                            $customer
                        ) {
                            return true; // accept all
                        },
                        // 2:nd element, callback arg. description
                        [
                            AuthenticationHandler::PHP_AUTH_USER,
                            AuthenticationHandler::PHP_AUTH_PW,
                            AuthenticationHandler::REALM,
                            RestServer::CORRELATIONID,
                            'RestServer',
                        ]
                    ],
                ]
            ],
            500,
        ];

        $dataArr[] = [ // test set #7, Basic auth 2, callback, err - callback missing
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    RestServer::IGNORE                   => true, // skip testing headers
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                ]
            ],
            500,
        ];

        $dataArr[] = [ // test set #8, Basic auth 2, callback, err - callback error
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    RestServer::IGNORE                   => true, // skip testing headers
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'realm',
                    RestServer::CALLBACK                 => [
                        123,
                        // 2:nd element, callback arg. description
                        [
                            AuthenticationHandler::PHP_AUTH_USER,
                            AuthenticationHandler::PHP_AUTH_PW,
                            AuthenticationHandler::REALM,
                            RestServer::CORRELATIONID,
                            'RestServer',
                        ]
                    ],
                ]
            ],
            500,
        ];

        $dataArr[] = [ // test set #9, Basic auth 2, callback, err - callback description error
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    RestServer::IGNORE                   => true, // skip testing headers
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'realm',
                    RestServer::CALLBACK                 => [
                        // 1:st element, callback
                        function (
                            $userid,
                            $password,
                            $realm,
                            $corrId,
                            $customer
                        ) {
                            return true; // accept all
                        },
                        // 2:nd element, callback arg. description
                        'error'
                    ],
                ]
            ],
            500,
        ];

        $dataArr[] = [ // test set #10, Digest auth 3, callback, ok
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    RestServer::IGNORE                   => true, // skip testing headers
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => [
                        // 1:st element, callback, auth required
                        function (
                            $userid,
                            $password,
                            $realm,
                            $corrId,
                            $customer
                        ) {
                            return true; // accept all
                        },
                        // 2:nd element, callback arg. description
                        [
                            RestServer::CORRELATIONID,
                            'RestServer',
                        ],
                        // 3:st element, callback, auth validation
                        function (
                            $userid,
                            $realm,
                            $nounce,
                            $uri,
                            $response,
                            $algorithm,
                            $cnonce,
                            $corrId,
                            $customer
                        ) {
                            return true; // accept all
                        },
                        // 4:st element, callback arg description
                        [
                            AuthenticationHandler::USERNAME,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::NONCE,
                            AuthenticationHandler::URI,
                            AuthenticationHandler::RESPONSE,
                            AuthenticationHandler::ALGORITHM,
                            AuthenticationHandler::CNONCE,
                            AuthenticationHandler::OPAQUE,
                            AuthenticationHandler::QOP,
                            AuthenticationHandler::NC,
                            RestServer::CORRELATIONID,
                            'RestServer',

                        ]
                    ],
                ]
            ],
            200,
        ];

        $dataArr[] = [ // test set #11, Digest auth 3, callback, err - callback no callback
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    RestServer::IGNORE                   => true, // skip testing headers
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => 123,
                ]
            ],
            500,
        ];

        $dataArr[] = [ // test set #12, Digest auth 3, callback, err - 1:st callback missing
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    RestServer::IGNORE                   => true, // skip testing headers
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => [
                        // 1:st element, callback, auth required
                        // 2:nd element, callback arg. description
                        [
                            RestServer::CORRELATIONID,
                            'RestServer',
                        ],
                        // 3:st element, callback, auth validation
                        function (
                            $userid,
                            $realm,
                            $nounce,
                            $uri,
                            $response,
                            $algorithm,
                            $cnonce,
                            $corrId,
                            $customer
                        ) {
                            return true; // accept all
                        },
                        // 4:st element, callback arg description
                        [
                            AuthenticationHandler::USERNAME,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::NONCE,
                            AuthenticationHandler::URI,
                            AuthenticationHandler::RESPONSE,
                            AuthenticationHandler::ALGORITHM,
                            AuthenticationHandler::CNONCE,
                            AuthenticationHandler::OPAQUE,
                            AuthenticationHandler::QOP,
                            AuthenticationHandler::NC,
                            RestServer::CORRELATIONID,
                            'RestServer',

                        ]
                    ],
                ],
            ],
            500,
        ];

        $dataArr[] = [ // test set #13, Digest auth 3, callback, err - 1:st callback no callback
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    RestServer::IGNORE                   => true, // skip testing headers
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => [
                        // 1:st element, callback, auth required
                        123,
                        // 2:nd element, callback arg. description
                        [
                            RestServer::CORRELATIONID,
                            'RestServer',
                        ],
                        // 3:st element, callback, auth validation
                        function (
                            $userid,
                            $realm,
                            $nounce,
                            $uri,
                            $response,
                            $algorithm,
                            $cnonce,
                            $corrId,
                            $customer
                        ) {
                            return true; // accept all
                        },
                        // 4:st element, callback arg description
                        [
                            AuthenticationHandler::USERNAME,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::NONCE,
                            AuthenticationHandler::URI,
                            AuthenticationHandler::RESPONSE,
                            AuthenticationHandler::ALGORITHM,
                            AuthenticationHandler::CNONCE,
                            AuthenticationHandler::OPAQUE,
                            AuthenticationHandler::QOP,
                            AuthenticationHandler::NC,
                            RestServer::CORRELATIONID,
                            'RestServer',

                        ]
                    ],
                ],
            ],
            500,
        ];

        $dataArr[] = [ // test set #14, Digest auth 3, callback, 1:st callback args no array
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    RestServer::IGNORE                   => true, // skip testing headers
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => [
                        // 1:st element, callback, auth required
                        function (
                            $userid,
                            $password,
                            $realm,
                            $corrId,
                            $customer
                        ) {
                            return true; // accept all
                        },
                        // 2:nd element, callback arg. description
                        123,
                        // 3:st element, callback, auth validation
                        function (
                            $userid,
                            $realm,
                            $nounce,
                            $uri,
                            $response,
                            $algorithm,
                            $cnonce,
                            $corrId,
                            $customer
                        ) {
                            return true; // accept all
                        },
                        // 4:st element, callback arg description
                        [
                            AuthenticationHandler::USERNAME,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::NONCE,
                            AuthenticationHandler::URI,
                            AuthenticationHandler::RESPONSE,
                            AuthenticationHandler::ALGORITHM,
                            AuthenticationHandler::CNONCE,
                            AuthenticationHandler::OPAQUE,
                            AuthenticationHandler::QOP,
                            AuthenticationHandler::NC,
                            RestServer::CORRELATIONID,
                            'RestServer',

                        ]
                    ],
                ]
            ],
            500,
        ];

        $dataArr[] = [ // test set #15, Digest auth 3, callback, err - 2:nd callback no callback
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    RestServer::IGNORE                   => true, // skip testing headers
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => [
                        // 1:st element, callback, auth required
                        function (
                            $userid,
                            $password,
                            $realm,
                            $corrId,
                            $customer
                        ) {
                            return true; // accept all
                        },
                        // 2:nd element, callback arg. description
                        [
                            RestServer::CORRELATIONID,
                            'RestServer',
                        ],
                        // 3:st element, callback, auth validation
                        123,
                        // 4:st element, callback arg description
                        [
                            AuthenticationHandler::USERNAME,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::NONCE,
                            AuthenticationHandler::URI,
                            AuthenticationHandler::RESPONSE,
                            AuthenticationHandler::ALGORITHM,
                            AuthenticationHandler::CNONCE,
                            AuthenticationHandler::OPAQUE,
                            AuthenticationHandler::QOP,
                            AuthenticationHandler::NC,
                            RestServer::CORRELATIONID,
                            'RestServer',

                        ]
                    ],
                ]
            ],
            500,
        ];

        $dataArr[] = [ // test set #16, Digest auth 3, callback, err - 2:nd callback args no array
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    RestServer::IGNORE                   => true, // skip testing headers
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => [
                        // 1:st element, callback, auth required
                        function (
                            $userid,
                            $password,
                            $realm,
                            $corrId,
                            $customer
                        ) {
                            return true; // accept all
                        },
                        // 2:nd element, callback arg. description
                        [
                            RestServer::CORRELATIONID,
                            'RestServer',
                        ],
                        // 3:st element, callback, auth validation
                        function (
                            $userid,
                            $realm,
                            $nounce,
                            $uri,
                            $response,
                            $algorithm,
                            $cnonce,
                            $corrId,
                            $customer
                        ) {
                            return true; // accept all
                        },
                        // 4:st element, callback arg description
                        123
                    ],
                ]
            ],
            500,
        ];

        return $dataArr;
    }

    /**
     * test validateAuthentication config
     *
     * @test
     * @dataProvider validateAuthentication1provider
     */
    public function testValidateAuthentication1(
        array $config,
        $status
    ) {
        $request = new ServerRequest(
            [],                            // serverParams
            [],                            // $uploadedFiles
            null,                      // uri
            null,                   // method
            StreamFactory::createStream(), // body
            []                             // headers
        );
        $request                    = $request->withAttribute( RestServer::CONFIG, $config );
        list( $request, $response ) = AuthenticationHandler::validateAuthentication(
            $request,
            new Response()
        );
        $this->assertEquals( $status, $response->getStatusCode());
    }

    /**
     * testValidateAuthentication2 provider
     */
    public function validateAuthentication2provider() {
        $dataArr   = [];

        $dataArr[] = [ // test set #0, Auth not expected and not found
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
            ],
            [],
            200,
        ];

        $dataArr[] = [ // test set #1, Auth not expected and not found
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    RestServer::IGNORE               => true, // skip testing headers
                ]
            ],
            [],
            200,
        ];

        $dataArr[] = [ // test set #2, Auth not expected but found and ignored, ok
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    RestServer::IGNORE               => true, // skip testing headers
                ]
            ],
            [
                AuthenticationHandler::AUTHORIZATION => 'Basic blablabla'
            ],
            200,
        ];

        $dataArr[] = [ // test set #3, Auth not expected but found and not ignored, error 2
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
            ],
            [
                AuthenticationHandler::AUTHORIZATION => 'Basic blablabla'
            ],
            403,
        ];

        return $dataArr;
    }
    /**
     * test validateAuthentication headers and ignore etc
     *
     * @test
     * @dataProvider validateAuthentication2provider
     */
    public function testValidateAuthentication2(
        array $config,
        array $headers,
        $status
    ) {
        $request = new ServerRequest(
            [],                            // serverParams
            [],                            // $uploadedFiles
            null,                      // uri
            null,                   // method
            StreamFactory::createStream(), // body
            $headers                       // headers
        );
        $request                    = $request->withAttribute( RestServer::CONFIG, $config );
        list( $request, $response ) = AuthenticationHandler::validateAuthentication(
            $request,
            new Response()
        );
        $this->assertEquals( $status, $response->getStatusCode());
    }

    /**
     * testValidateAuthentication3 provider
     */
    public function validateAuthentication3provider() {
        $dataArr   = [];

        // test set #0, ok
        $userId    = 'userid';
        $passWord  = 'password';
        $dataArr[] = [
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'This is the realm value',
                    AuthenticationHandler::PHP_AUTH_USER => [
                        'userId1' => 'passWord1',
                        $userId   => $passWord,
                    ],
                ],
            ],
            [
                AuthenticationHandler::AUTHORIZATION => 'Basic ' . base64_encode( $userId . ':' . $passWord ),
            ],
            200,
        ];

        // test set #1, invalid auth type, return 400
        $userId    = 'userid';
        $passWord  = 'password';
        $dataArr[] = [
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'This is the realm value',
                    AuthenticationHandler::PHP_AUTH_USER => [
                        $userId => $passWord,
                    ],
                ],
            ],
            [
                AuthenticationHandler::AUTHORIZATION => AuthenticationHandler::DIGEST . ' ' . base64_encode( $userId . ':' . $passWord ),
            ],
            400,
        ];

        // test set #2, no auth headers at all, return 401
        $userId    = 'userid';
        $passWord  = 'password';
        $dataArr[] = [
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'This is the realm value',
                    AuthenticationHandler::PHP_AUTH_USER => [
                        $userId => $passWord,
                    ],
                ],
            ],
            [
            ],
            401,
        ];

        // test set #3, invalid password, return 400
        $userId    = 'userid';
        $passWord  = 'password';
        $dataArr[] = [
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'This is the realm value',
                    AuthenticationHandler::PHP_AUTH_USER => [
                        $userId => 'error',
                    ],
                ],
            ],
            [
                AuthenticationHandler::AUTHORIZATION => AuthenticationHandler::BASIC . ' ' . base64_encode( $userId . ':' . $passWord ),
            ],
            400,
        ];

        // test set #4, invalid auth string, return 400
        $userId    = 'userid';
        $passWord  = 'password';
        $dataArr[] = [
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'This is the realm value',
                    AuthenticationHandler::PHP_AUTH_USER => [
                        $userId => $passWord,
                    ],
                ],
            ],
            [
                AuthenticationHandler::AUTHORIZATION => 'Basic ' . base64_encode( $userId . '-' . $passWord ),
            ],
            400,
        ];

        // test set #5, ok
        $userId    = 'userid';
        $passWord  = 'password';
        $dataArr[] = [
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'This is the realm value',
                    AuthenticationHandler::PHP_AUTH_USER => [
                        $userId => $passWord,
                    ],
                ],
            ],
            [
                AuthenticationHandler::AUTH_TYPE     => AuthenticationHandler::BASIC,
                AuthenticationHandler::PHP_AUTH_USER => $userId,
                AuthenticationHandler::PHP_AUTH_PW   => $passWord,
            ],
            200,
        ];

        // test set #6, invalid auth type, return 400
        $userId    = 'userid';
        $passWord  = 'password';
        $dataArr[] = [
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'This is the realm value',
                    AuthenticationHandler::PHP_AUTH_USER => [
                        $userId => $passWord,
                    ],
                ],
            ],
            [
                AuthenticationHandler::AUTH_TYPE     => AuthenticationHandler::DIGEST,
                AuthenticationHandler::PHP_AUTH_USER => $userId,
                AuthenticationHandler::PHP_AUTH_PW   => $passWord,
            ],
            400,
        ];

        // test set #7, invalid user, return 400
        $userId    = 'userid';
        $passWord  = 'password';
        $dataArr[] = [
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'This is the realm value',
                    AuthenticationHandler::PHP_AUTH_USER => [
                        $userId => $passWord,
                    ],
                ],
            ],
            [
                AuthenticationHandler::AUTH_TYPE     => AuthenticationHandler::BASIC,
                AuthenticationHandler::PHP_AUTH_USER => 'error',
                AuthenticationHandler::PHP_AUTH_PW   => $passWord,
            ],
            400,
        ];

        // test set #8, missing user, return 400
        $userId    = 'userid';
        $passWord  = 'password';
        $dataArr[] = [
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'This is the realm value',
                    AuthenticationHandler::PHP_AUTH_USER => [
                        $userId => $passWord,
                    ],
                ],
            ],
            [
                AuthenticationHandler::AUTH_TYPE     => AuthenticationHandler::BASIC,
                AuthenticationHandler::PHP_AUTH_PW   => $passWord,
            ],
            400,
        ];

        // test set #9, only header AUTH_TYPE Basic, return 400
        $userId    = 'userid';
        $passWord  = 'password';
        $dataArr[] = [
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'This is the realm value',
                    AuthenticationHandler::PHP_AUTH_USER => [
                        $userId => $passWord,
                    ],
                ],
            ],
            [
                AuthenticationHandler::AUTH_TYPE     => AuthenticationHandler::BASIC,
            ],
            400,
        ];

        // test set #10, only header AUTH_TYPE Digest, return 400
        $userId    = 'userid';
        $passWord  = 'password';
        $dataArr[] = [
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'This is the realm value',
                    AuthenticationHandler::PHP_AUTH_USER => [
                        $userId => $passWord,
                    ],
                ],
            ],
            [
                AuthenticationHandler::AUTH_TYPE     => AuthenticationHandler::BASIC,
            ],
            400,
        ];

        return $dataArr;
    }

    /**
     * test validateAuthentication Basic 1
     *
     * @test
     * @dataProvider validateAuthentication3provider
     */
    public function testValidateAuthentication3(
        array $config,
        array $headers,
        $status
    ) {
        $request = new ServerRequest(
            [],                            // serverParams
            [],                            // $uploadedFiles
            null,                      // uri
            null,                   // method
            StreamFactory::createStream(), // body
            $headers                       // headers
        );
        $request                    = $request->withAttribute( RestServer::CONFIG, $config );
        list( $request, $response ) = AuthenticationHandler::validateAuthentication(
            $request,
            new Response()
        );
        switch( $status ) {
            case 200 :
                break;
            case 400 :
                break;
            case 401 :
                $this->assertTrue( $response->hasHeader( AuthenticationHandler::WWW_AUTHENTICATE ));
                $headerValue = $response->getHeader( AuthenticationHandler::WWW_AUTHENTICATE )[0];
                $authCfg     = $config[AuthenticationHandler::AUTHORIZATION];
                $expectedVal = sprintf( '%s %s="%s"',
                                        $authCfg[AuthenticationHandler::REQUIRED],
                                        strtolower( AuthenticationHandler::REALM ),
                                        $authCfg[AuthenticationHandler::REALM] );
                $this->assertEquals( $expectedVal, $headerValue);
                break;
            case 500 :
                break;
        }
        $this->assertEquals( $status, $response->getStatusCode());
    }

    /**
     * testValidateAuthentication4 provider
     */
    public function validateAuthentication4provider() {
        $dataArr = [];

        // test set #0, ok
        $userId    = 'userid';
        $passWord  = 'password';
        $dataArr[] = [
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'This is the realm value',
                    RestServer::CALLBACK                 => [
                        [
                            __NAMESPACE__ . '\\BasicValidation2Class',
                            'validate2method',
                        ],
                        [
                            BasicValidation2Class::CONSUMER,      // fix value
                            AuthenticationHandler::REALM,         // from config
                            AuthenticationHandler::PHP_AUTH_USER, // from header
                            AuthenticationHandler::PHP_AUTH_PW,   // from header
                            RestServer::CORRELATIONID,            // from config
                        ]
                    ]
                ],
            ],
            [
                AuthenticationHandler::AUTHORIZATION => 'Basic ' . base64_encode( BasicValidation2Class::PHP_AUTH_USER . ':' . BasicValidation2Class::PHP_AUTH_PW ),
            ],
            200,
        ];

        // test set #1, invalid password, return 400
        $userId    = 'userid';
        $passWord  = 'password';
        $dataArr[] = [
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'This is the realm value',
                    RestServer::CALLBACK                 => [
                        [
                            __NAMESPACE__ . '\\BasicValidation2Class',
                            'validate2method',
                        ],
                        [
                            BasicValidation2Class::CONSUMER,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::PHP_AUTH_USER,
                            AuthenticationHandler::PHP_AUTH_PW,
                            RestServer::CORRELATIONID,
                        ]
                    ]
                ],
            ],
            [
                AuthenticationHandler::AUTHORIZATION => 'Basic ' . base64_encode( BasicValidation2Class::PHP_AUTH_USER . ':error' ),
            ],
            400,
        ];

        // test set #2, callback with php error, return 500
        $userId    = 'userid';
        $passWord  = 'password';
        $dataArr[] = [
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::BASIC,
                    AuthenticationHandler::REALM         => 'This is the realm value',
                    RestServer::CALLBACK                 => [
                        [
                            __NAMESPACE__ . '\\BasicValidation2Class',
                            'validate2methodErr',
                        ],
                        [
                            BasicValidation2Class::CONSUMER,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::PHP_AUTH_USER,
                            AuthenticationHandler::PHP_AUTH_PW,
                            RestServer::CORRELATIONID,
                        ]
                    ]
                ],
            ],
            [
                AuthenticationHandler::AUTHORIZATION => 'Basic ' . base64_encode( BasicValidation2Class::PHP_AUTH_USER . ':' . BasicValidation2Class::PHP_AUTH_PW ),
            ],
            500,
        ];


        return $dataArr;
    }

    /**
     * test validateAuthentication Basic 2, validating using callback
     *
     * @test
     * @dataProvider validateAuthentication4provider
     */
    public function testValidateAuthentication4(
        array $config,
        array $headers,
        $status
    ) {
        $request = new ServerRequest(
            [],                            // serverParams
            [],                            // $uploadedFiles
            null,                      // uri
            null,                   // method
            StreamFactory::createStream(), // body
            $headers                       // headers
        );
        $request                    = $request->withAttribute( RestServer::CONFIG, $config );
        list( $request, $response ) = AuthenticationHandler::validateAuthentication(
            $request,
            new Response()
        );
        switch( $status ) {
            case 200 :
                break;
            case 400 :
                break;
            case 401 :
                $this->assertTrue( $response->hasHeader( AuthenticationHandler::WWW_AUTHENTICATE ));
                $headerValue = $response->getHeader( AuthenticationHandler::WWW_AUTHENTICATE )[0];
                $authCfg     = $config[AuthenticationHandler::AUTHORIZATION];
                $expectedVal = sprintf( '%s %s="%s"',
                                        $authCfg[AuthenticationHandler::REQUIRED],
                                        strtolower( AuthenticationHandler::REALM ),
                                        $authCfg[AuthenticationHandler::REALM] );
                $this->assertEquals( $expectedVal, $headerValue);
                break;
             case 500 :
                // fall through
            default :
                break;
        }
        $this->assertEquals( $status, $response->getStatusCode());
    }

    /**
     * testValidateAuthentication5 provider
     */
    public function validateAuthentication5provider() {
        $dataArr = [];

        // test set #0, missing request header (ie test response 401)
        $dataArr[] = [
            'GET',
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => [
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'getResponseRequestExpectedHeaderParams',
                        ],
                        [
                            DigestValidation3Class::CONSUMER,
                            RestServer::CORRELATIONID,
                        ],
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'validate3method',
                        ],
                        [
                            DigestValidation3Class::CONSUMER, // fix value
                            AuthenticationHandler::USERNAME,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::NONCE,
                            AuthenticationHandler::URI,
                            AuthenticationHandler::RESPONSE,
                            AuthenticationHandler::ALGORITHM,
                            AuthenticationHandler::CNONCE,
                            AuthenticationHandler::OPAQUE,
                            AuthenticationHandler::QOP,
                            AuthenticationHandler::USERNAME,
                            AuthenticationHandler::NC,
                            RestServer::CORRELATIONID,
                        ],
                    ]
                ],
            ],
            [ // no auth header
            ],
            401,
        ];

        // test set #1, ok request AUTHORIZATION header (ie test response 200)
        $colon    = ':';
        $comma    = ',';
        $eqq      = '="';
        $qend     = '"';
        $method   = 'GET';
        $HA1      = md5( DigestValidation3Class::USERNAME . $colon
                       . DigestValidation3Class::REALM . $colon
                       . DigestValidation3Class::PASSWORD );
        $HA2      = md5( $method . $colon . DigestValidation3Class::URI );
        $response = md5( $HA1 . $colon
                       . DigestValidation3Class::NONCE  . $colon
                       . DigestValidation3Class::NC     . $colon
                       . DigestValidation3Class::CNONCE . $colon
                       . DigestValidation3Class::QOP    . $colon
                       . $HA2 );
        $dataArr[] = [
            'GET',
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => [
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'getResponseRequestExpectedHeaderParams',
                        ],
                        [
                            DigestValidation3Class::CONSUMER,
                            RestServer::CORRELATIONID,
                        ],
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'validate3method',
                        ],
                        [
                            DigestValidation3Class::CONSUMER, // fix value
                            AuthenticationHandler::USERNAME,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::NONCE,
                            AuthenticationHandler::URI,
                            AuthenticationHandler::RESPONSE,
                            AuthenticationHandler::ALGORITHM,
                            AuthenticationHandler::CNONCE,
                            AuthenticationHandler::OPAQUE,
                            AuthenticationHandler::QOP,
                            AuthenticationHandler::NC,
                            RestServer::METHOD,
                            RestServer::CORRELATIONID,
                        ],
                    ]
                ],
            ],
            [ // a auth header
                AuthenticationHandler::AUTHORIZATION => 'Digest '
                             . AuthenticationHandler::USERNAME  . $eqq . DigestValidation3Class::USERNAME  . $qend
                    . $comma . AuthenticationHandler::REALM     . $eqq . DigestValidation3Class::REALM     . $qend
                    . $comma . AuthenticationHandler::NONCE     . $eqq . DigestValidation3Class::NONCE     . $qend
                    . $comma . AuthenticationHandler::URI       . $eqq . DigestValidation3Class::URI       . $qend
                    . $comma . AuthenticationHandler::RESPONSE  . $eqq . $response                         . $qend
                    . $comma . AuthenticationHandler::ALGORITHM . $eqq . DigestValidation3Class::ALGORITHM . $qend
                    . $comma . AuthenticationHandler::CNONCE    . $eqq . DigestValidation3Class::CNONCE    . $qend
                    . $comma . AuthenticationHandler::OPAQUE    . $eqq . DigestValidation3Class::OPAQUE    . $qend
                    . $comma . AuthenticationHandler::NC        . $eqq . DigestValidation3Class::NC        . $qend
                    . $comma . AuthenticationHandler::QOP       . $eqq . DigestValidation3Class::QOP       . $qend,
            ],
            200,
        ];

        // test set #2, ok request PHP_AUTH_DIGEST header (ie test response 200)
        $colon    = ':';
        $comma    = ',';
        $eqq      = '="';
        $qend     = '"';
        $method   = 'GET';
        $HA1      = md5( DigestValidation3Class::USERNAME . $colon
                       . DigestValidation3Class::REALM . $colon
                       . DigestValidation3Class::PASSWORD );
        $HA2      = md5( $method . $colon . DigestValidation3Class::URI );
        $response = md5( $HA1 . $colon
                       . DigestValidation3Class::NONCE  . $colon
                       . DigestValidation3Class::NC     . $colon
                       . DigestValidation3Class::CNONCE . $colon
                       . DigestValidation3Class::QOP    . $colon
                       . $HA2 );
        $dataArr[] = [
            'GET',
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => [
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'getResponseRequestExpectedHeaderParams',
                        ],
                        [
                            DigestValidation3Class::CONSUMER,
                            RestServer::CORRELATIONID,
                        ],
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'validate3method',
                        ],
                        [
                            DigestValidation3Class::CONSUMER, // fix value
                            AuthenticationHandler::USERNAME,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::NONCE,
                            AuthenticationHandler::URI,
                            AuthenticationHandler::RESPONSE,
                            AuthenticationHandler::ALGORITHM,
                            AuthenticationHandler::CNONCE,
                            AuthenticationHandler::OPAQUE,
                            AuthenticationHandler::QOP,
                            AuthenticationHandler::NC,
                            RestServer::METHOD,
                            RestServer::CORRELATIONID,
                        ],
                    ]
                ],
            ],
            [ // a auth header
                AuthenticationHandler::PHP_AUTH_DIGEST =>
                               AuthenticationHandler::USERNAME  . $eqq . DigestValidation3Class::USERNAME  . $qend
                    . $comma . AuthenticationHandler::REALM     . $eqq . DigestValidation3Class::REALM     . $qend
                    . $comma . AuthenticationHandler::NONCE     . $eqq . DigestValidation3Class::NONCE     . $qend
                    . $comma . AuthenticationHandler::URI       . $eqq . DigestValidation3Class::URI       . $qend
                    . $comma . AuthenticationHandler::RESPONSE  . $eqq . $response                         . $qend
                    . $comma . AuthenticationHandler::ALGORITHM . $eqq . DigestValidation3Class::ALGORITHM . $qend
                    . $comma . AuthenticationHandler::CNONCE    . $eqq . DigestValidation3Class::CNONCE    . $qend
                    . $comma . AuthenticationHandler::OPAQUE    . $eqq . DigestValidation3Class::OPAQUE    . $qend
                    . $comma . AuthenticationHandler::NC        . $eqq . DigestValidation3Class::NC        . $qend
                    . $comma . AuthenticationHandler::QOP       . $eqq . DigestValidation3Class::QOP       . $qend,
            ],
            200,
        ];

        // test set #3, err; request AUTHORIZATION header ok BUT request AUTH_TYPE wrong, ie return 400
        $colon    = ':';
        $comma    = ',';
        $eqq      = '="';
        $qend     = '"';
        $method   = 'GET';
        $HA1      = md5( DigestValidation3Class::USERNAME . $colon
                         . DigestValidation3Class::REALM . $colon
                         . DigestValidation3Class::PASSWORD );
        $HA2      = md5( $method . $colon . DigestValidation3Class::URI );
        $response = md5( $HA1 . $colon
                         . DigestValidation3Class::NONCE  . $colon
                         . DigestValidation3Class::NC     . $colon
                         . DigestValidation3Class::CNONCE . $colon
                         . DigestValidation3Class::QOP    . $colon
                         . $HA2 );
        $dataArr[] = [
            'GET',
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => [
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'getResponseRequestExpectedHeaderParams',
                        ],
                        [
                            DigestValidation3Class::CONSUMER,
                            RestServer::CORRELATIONID,
                        ],
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'validate3method',
                        ],
                        [
                            DigestValidation3Class::CONSUMER, // fix value
                            AuthenticationHandler::USERNAME,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::NONCE,
                            AuthenticationHandler::URI,
                            AuthenticationHandler::RESPONSE,
                            AuthenticationHandler::ALGORITHM,
                            AuthenticationHandler::CNONCE,
                            AuthenticationHandler::OPAQUE,
                            AuthenticationHandler::QOP,
                            AuthenticationHandler::NC,
                            RestServer::METHOD,
                            RestServer::CORRELATIONID,
                        ],
                    ]
                ],
            ],
        [ // a auth headers
                AuthenticationHandler::AUTH_TYPE     => AuthenticationHandler::BASIC,
                AuthenticationHandler::AUTHORIZATION => 'Digest '
                    . AuthenticationHandler::USERNAME  . $eqq . DigestValidation3Class::USERNAME  . $qend
                    . $comma . AuthenticationHandler::REALM     . $eqq . DigestValidation3Class::REALM     . $qend
                    . $comma . AuthenticationHandler::NONCE     . $eqq . DigestValidation3Class::NONCE     . $qend
                    . $comma . AuthenticationHandler::URI       . $eqq . DigestValidation3Class::URI       . $qend
                    . $comma . AuthenticationHandler::RESPONSE  . $eqq . $response                         . $qend
                    . $comma . AuthenticationHandler::ALGORITHM . $eqq . DigestValidation3Class::ALGORITHM . $qend
                    . $comma . AuthenticationHandler::CNONCE    . $eqq . DigestValidation3Class::CNONCE    . $qend
                    . $comma . AuthenticationHandler::OPAQUE    . $eqq . DigestValidation3Class::OPAQUE    . $qend
                    . $comma . AuthenticationHandler::NC        . $eqq . DigestValidation3Class::NC        . $qend
                    . $comma . AuthenticationHandler::QOP       . $eqq . DigestValidation3Class::QOP       . $qend,
            ],
            400,
        ];

        // test set #4, err; only an (Basic) auth_type header (ie test response 400)
        $colon    = ':';
        $comma    = ',';
        $eqq      = '="';
        $qend     = '"';
        $method   = 'GET';
        $HA1      = md5( DigestValidation3Class::USERNAME . $colon
                         . DigestValidation3Class::REALM . $colon
                         . DigestValidation3Class::PASSWORD );
        $HA2      = md5( $method . $colon . DigestValidation3Class::URI );
        $response = md5( $HA1 . $colon
                         . DigestValidation3Class::NONCE  . $colon
                         . DigestValidation3Class::NC     . $colon
                         . DigestValidation3Class::CNONCE . $colon
                         . DigestValidation3Class::QOP    . $colon
                         . $HA2 );
        $dataArr[] = [
            'GET',
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => [
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'getResponseRequestExpectedHeaderParams',
                        ],
                        [
                            DigestValidation3Class::CONSUMER,
                            RestServer::CORRELATIONID,
                        ],
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'validate3method',
                        ],
                        [
                            DigestValidation3Class::CONSUMER, // fix value
                            AuthenticationHandler::USERNAME,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::NONCE,
                            AuthenticationHandler::URI,
                            AuthenticationHandler::RESPONSE,
                            AuthenticationHandler::ALGORITHM,
                            AuthenticationHandler::CNONCE,
                            AuthenticationHandler::OPAQUE,
                            AuthenticationHandler::QOP,
                            AuthenticationHandler::NC,
                            RestServer::METHOD,
                            RestServer::CORRELATIONID,
                        ],
                    ]
                ],
            ],
            [ // a wrong auth header
                AuthenticationHandler::AUTH_TYPE     => AuthenticationHandler::BASIC,
            ],
            400,
        ];

        // test set #5, err; only an (Digest) auth_type header (ie test response 400)
        $colon    = ':';
        $comma    = ',';
        $eqq      = '="';
        $qend     = '"';
        $method   = 'GET';
        $HA1      = md5( DigestValidation3Class::USERNAME . $colon
                         . DigestValidation3Class::REALM . $colon
                         . DigestValidation3Class::PASSWORD );
        $HA2      = md5( $method . $colon . DigestValidation3Class::URI );
        $response = md5( $HA1 . $colon
                         . DigestValidation3Class::NONCE  . $colon
                         . DigestValidation3Class::NC     . $colon
                         . DigestValidation3Class::CNONCE . $colon
                         . DigestValidation3Class::QOP    . $colon
                         . $HA2 );
        $dataArr[] = [
            'GET',
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => [
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'getResponseRequestExpectedHeaderParams',
                        ],
                        [
                            DigestValidation3Class::CONSUMER,
                            RestServer::CORRELATIONID,
                        ],
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'validate3method',
                        ],
                        [
                            DigestValidation3Class::CONSUMER, // fix value
                            AuthenticationHandler::USERNAME,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::NONCE,
                            AuthenticationHandler::URI,
                            AuthenticationHandler::RESPONSE,
                            AuthenticationHandler::ALGORITHM,
                            AuthenticationHandler::CNONCE,
                            AuthenticationHandler::OPAQUE,
                            AuthenticationHandler::QOP,
                            AuthenticationHandler::NC,
                            RestServer::METHOD,
                            RestServer::CORRELATIONID,
                        ],
                    ]
                ],
            ],
            [ // a wrong auth header
                AuthenticationHandler::AUTH_TYPE     => AuthenticationHandler::DIGEST,
            ],
            400,
        ];

        // test set #6, missing part in request header (ie test response 400)
        $colon    = ':';
        $comma    = ',';
        $eqq      = '="';
        $qend     = '"';
        $method   = 'GET';
        $HA1      = md5( DigestValidation3Class::USERNAME . $colon
                       . DigestValidation3Class::REALM . $colon
                       . DigestValidation3Class::PASSWORD );
        $HA2      = md5( $method . $colon . DigestValidation3Class::URI );
        $response = md5( $HA1 . $colon
                       . DigestValidation3Class::NONCE  . $colon
                       . DigestValidation3Class::NC     . $colon
                       . DigestValidation3Class::CNONCE . $colon
                       . DigestValidation3Class::QOP    . $colon
                       . $HA2 );
        $dataArr[] = [
            'GET',
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => [
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'getResponseRequestExpectedHeaderParams',
                        ],
                        [
                            DigestValidation3Class::CONSUMER,
                            RestServer::CORRELATIONID,
                        ],
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'validate3method',
                        ],
                        [
                            DigestValidation3Class::CONSUMER, // fix value
                            AuthenticationHandler::USERNAME,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::NONCE,
                            AuthenticationHandler::URI,
                            AuthenticationHandler::RESPONSE,
                            AuthenticationHandler::ALGORITHM,
                            AuthenticationHandler::CNONCE,
                            AuthenticationHandler::OPAQUE,
                            AuthenticationHandler::QOP,
                            AuthenticationHandler::NC,
                            RestServer::METHOD,
                            RestServer::CORRELATIONID,
                        ],
                    ]
                ],
            ],
            [ // a auth header with a missing part
                AuthenticationHandler::AUTHORIZATION => 'Digest '
                             . AuthenticationHandler::USERNAME  . $eqq . DigestValidation3Class::USERNAME  . $qend
                    . $comma . AuthenticationHandler::REALM     . $eqq . DigestValidation3Class::REALM     . $qend
                    . $comma . AuthenticationHandler::NONCE     . $eqq . DigestValidation3Class::NONCE     . $qend
                    . $comma . AuthenticationHandler::URI       . $eqq . DigestValidation3Class::URI       . $qend
                    . $comma . AuthenticationHandler::RESPONSE  . $eqq . $response                         . $qend
                    . $comma . AuthenticationHandler::ALGORITHM . $eqq . DigestValidation3Class::ALGORITHM . $qend
                    . $comma . AuthenticationHandler::CNONCE    . $eqq . DigestValidation3Class::CNONCE    . $qend
                    //                  . $comma . AuthenticationHandler::OPAQUE    . $eqq . DigestValidation3Class::OPAQUE    . $qend
                    . $comma . AuthenticationHandler::NC        . $eqq . DigestValidation3Class::NC        . $qend
                    . $comma . AuthenticationHandler::QOP       . $eqq . DigestValidation3Class::QOP       . $qend,
            ],
            400,
        ];

        // test set #7, error part in request header (ie test response 400)
        $colon    = ':';
        $comma    = ',';
        $eqq      = '="';
        $qend     = '"';
        $method   = 'GET';
        $HA1      = md5( DigestValidation3Class::USERNAME . $colon
                       . DigestValidation3Class::REALM . $colon
                       . DigestValidation3Class::PASSWORD );
        $HA2      = md5( $method . $colon . DigestValidation3Class::URI );
        $response = md5( $HA1 . $colon
                       . DigestValidation3Class::NONCE  . $colon
                       . DigestValidation3Class::NC     . $colon
                       . DigestValidation3Class::CNONCE . $colon
                       . DigestValidation3Class::QOP    . $colon
                       . $HA2 );
        $dataArr[] = [
            'GET',
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => [
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'getResponseRequestExpectedHeaderParams',
                        ],
                        [
                            DigestValidation3Class::CONSUMER,
                            RestServer::CORRELATIONID,
                        ],
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'validate3method',
                        ],
                        [
                            DigestValidation3Class::CONSUMER, // fix value
                            AuthenticationHandler::USERNAME,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::NONCE,
                            AuthenticationHandler::URI,
                            AuthenticationHandler::RESPONSE,
                            AuthenticationHandler::ALGORITHM,
                            AuthenticationHandler::CNONCE,
                            AuthenticationHandler::OPAQUE,
                            AuthenticationHandler::QOP,
                            AuthenticationHandler::NC,
                            RestServer::METHOD,
                            RestServer::CORRELATIONID,
                        ],
                    ]
                ],
            ],
            [ // a auth header with a missing part
                AuthenticationHandler::AUTHORIZATION => 'Digest '
                             . AuthenticationHandler::USERNAME  . $eqq . DigestValidation3Class::USERNAME  . $qend
                    . $comma . AuthenticationHandler::REALM     . $eqq . DigestValidation3Class::REALM     . $qend
                    . $comma . AuthenticationHandler::NONCE     . $eqq . 'error'                           . $qend
                    . $comma . AuthenticationHandler::URI       . $eqq . DigestValidation3Class::URI       . $qend
                    . $comma . AuthenticationHandler::RESPONSE  . $eqq . $response                         . $qend
                    . $comma . AuthenticationHandler::ALGORITHM . $eqq . DigestValidation3Class::ALGORITHM . $qend
                    . $comma . AuthenticationHandler::CNONCE    . $eqq . DigestValidation3Class::CNONCE    . $qend
                    . $comma . AuthenticationHandler::OPAQUE    . $eqq . DigestValidation3Class::OPAQUE    . $qend
                    . $comma . AuthenticationHandler::NC        . $eqq . DigestValidation3Class::NC        . $qend
                    . $comma . AuthenticationHandler::QOP       . $eqq . DigestValidation3Class::QOP       . $qend,
            ],
            400,
        ];

        // test set #8, error in request header, missing username (ie test response 400)
        $colon    = ':';
        $comma    = ',';
        $eqq      = '="';
        $qend     = '"';
        $method   = 'GET';
        $HA1      = md5( '' // DigestValidation3Class::USERNAME . $colon
                       . DigestValidation3Class::REALM . $colon
                       . DigestValidation3Class::PASSWORD );
        $HA2      = md5( $method . $colon . DigestValidation3Class::URI );
        $response = md5( $HA1 . $colon
                       . DigestValidation3Class::NONCE  . $colon
                       . DigestValidation3Class::NC     . $colon
                       . DigestValidation3Class::CNONCE . $colon
                       . DigestValidation3Class::QOP    . $colon
                       . $HA2 );
        $dataArr[] = [
            'GET',
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => [
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'getResponseRequestExpectedHeaderParams',
                        ],
                        [
                            DigestValidation3Class::CONSUMER,
                            RestServer::CORRELATIONID,
                        ],
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'validate3method',
                        ],
                        [
                            DigestValidation3Class::CONSUMER, // fix value
                            AuthenticationHandler::USERNAME,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::NONCE,
                            AuthenticationHandler::URI,
                            AuthenticationHandler::RESPONSE,
                            AuthenticationHandler::ALGORITHM,
                            AuthenticationHandler::CNONCE,
                            AuthenticationHandler::OPAQUE,
                            AuthenticationHandler::QOP,
                            AuthenticationHandler::NC,
                            RestServer::METHOD,
                            RestServer::CORRELATIONID,
                        ],
                    ]
                ],
            ],
            [ // a auth header with a missing part
                AuthenticationHandler::AUTHORIZATION => 'Digest '
                             . AuthenticationHandler::USERNAME  . $eqq . DigestValidation3Class::USERNAME  . $qend
                    . $comma . AuthenticationHandler::REALM     . $eqq . DigestValidation3Class::REALM     . $qend
                    . $comma . AuthenticationHandler::NONCE     . $eqq . 'error'                           . $qend
                    . $comma . AuthenticationHandler::URI       . $eqq . DigestValidation3Class::URI       . $qend
                    . $comma . AuthenticationHandler::RESPONSE  . $eqq . $response                         . $qend
                    . $comma . AuthenticationHandler::ALGORITHM . $eqq . DigestValidation3Class::ALGORITHM . $qend
                    . $comma . AuthenticationHandler::CNONCE    . $eqq . DigestValidation3Class::CNONCE    . $qend
                    . $comma . AuthenticationHandler::OPAQUE    . $eqq . DigestValidation3Class::OPAQUE    . $qend
                    . $comma . AuthenticationHandler::NC        . $eqq . DigestValidation3Class::NC        . $qend
                    . $comma . AuthenticationHandler::QOP       . $eqq . DigestValidation3Class::QOP       . $qend,
            ],
            400,
        ];

        // test set #9, due to error part in request header, test 1:st callback, return empty list
        $colon    = ':';
        $comma    = ',';
        $eqq      = '="';
        $qend     = '"';
        $method   = 'GET';
        $HA1      = md5( DigestValidation3Class::USERNAME . $colon
                       . DigestValidation3Class::REALM . $colon
                       . DigestValidation3Class::PASSWORD );
        $HA2      = md5( $method . $colon . DigestValidation3Class::URI );
        $response = md5( $HA1 . $colon
                       . DigestValidation3Class::NONCE  . $colon
                       . DigestValidation3Class::NC     . $colon
                       . DigestValidation3Class::CNONCE . $colon
                       . DigestValidation3Class::QOP    . $colon
                       . $HA2 );
        $dataArr[] = [
            'GET',
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => [
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'getResponseRequestExpectedHeaderParams',
                        ],
                        [
                            'OtherConsumer',  // cause empty return list
                            RestServer::CORRELATIONID,
                        ],
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'validate3method',
                        ],
                        [
                            DigestValidation3Class::CONSUMER, // fix value
                            AuthenticationHandler::USERNAME,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::NONCE,
                            AuthenticationHandler::URI,
                            AuthenticationHandler::RESPONSE,
                            AuthenticationHandler::ALGORITHM,
                            AuthenticationHandler::CNONCE,
                            AuthenticationHandler::OPAQUE,
                            AuthenticationHandler::QOP,
                            AuthenticationHandler::NC,
                            RestServer::METHOD,
                            RestServer::CORRELATIONID,
                        ],
                    ]
                ],
            ],
            [ // a auth header with a missing part
                AuthenticationHandler::AUTHORIZATION => 'Digest '
                             . AuthenticationHandler::USERNAME  . $eqq . DigestValidation3Class::USERNAME  . $qend
                    . $comma . AuthenticationHandler::REALM     . $eqq . DigestValidation3Class::REALM     . $qend
                    . $comma . AuthenticationHandler::NONCE     . $eqq . 'error'                           . $qend
                    . $comma . AuthenticationHandler::URI       . $eqq . DigestValidation3Class::URI       . $qend
                    . $comma . AuthenticationHandler::RESPONSE  . $eqq . $response                         . $qend
                    . $comma . AuthenticationHandler::ALGORITHM . $eqq . DigestValidation3Class::ALGORITHM . $qend
                    . $comma . AuthenticationHandler::CNONCE    . $eqq . DigestValidation3Class::CNONCE    . $qend
                    . $comma . AuthenticationHandler::OPAQUE    . $eqq . DigestValidation3Class::OPAQUE    . $qend
                    . $comma . AuthenticationHandler::NC        . $eqq . DigestValidation3Class::NC        . $qend
                    . $comma . AuthenticationHandler::QOP       . $eqq . DigestValidation3Class::QOP       . $qend,
            ],
            400,
        ];

        // test set #10, test exception from 1:st callback, NO auth headers at all
        $colon    = ':';
        $comma    = ',';
        $eqq      = '="';
        $qend     = '"';
        $method   = 'GET';
        $HA1      = md5( DigestValidation3Class::USERNAME . $colon
                       . DigestValidation3Class::REALM . $colon
                       . DigestValidation3Class::PASSWORD );
        $HA2      = md5( $method . $colon . DigestValidation3Class::URI );
        $response = md5( $HA1 . $colon
                       . DigestValidation3Class::NONCE  . $colon
                       . DigestValidation3Class::NC     . $colon
                       . DigestValidation3Class::CNONCE . $colon
                       . DigestValidation3Class::QOP    . $colon
                       . $HA2 );
        $dataArr[] = [
            'GET',
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => [
                        [
                            __NAMESPACE__ . '\\BasicValidation2Class',
                            'callbackThrowingsException',
                        ],
                        [
                            DigestValidation3Class::CONSUMER,
                            RestServer::CORRELATIONID,
                        ],
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'validate3method',
                        ],
                        [
                            DigestValidation3Class::CONSUMER, // fix value
                            AuthenticationHandler::USERNAME,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::NONCE,
                            AuthenticationHandler::URI,
                            AuthenticationHandler::RESPONSE,
                            AuthenticationHandler::ALGORITHM,
                            AuthenticationHandler::CNONCE,
                            AuthenticationHandler::OPAQUE,
                            AuthenticationHandler::QOP,
                            AuthenticationHandler::NC,
                            RestServer::METHOD,
                            RestServer::CORRELATIONID,
                        ],
                    ]
                ],
            ],
            [ // NO auth header
            ],
            500,
        ];

        // test set #11, test exception from 2:nd callback
        $colon    = ':';
        $comma    = ',';
        $eqq      = '="';
        $qend     = '"';
        $method   = 'GET';
        $HA1      = md5( DigestValidation3Class::USERNAME . $colon
                       . DigestValidation3Class::REALM . $colon
                       . DigestValidation3Class::PASSWORD );
        $HA2      = md5( $method . $colon . DigestValidation3Class::URI );
        $response = md5( $HA1 . $colon
                       . DigestValidation3Class::NONCE  . $colon
                       . DigestValidation3Class::NC     . $colon
                       . DigestValidation3Class::CNONCE . $colon
                       . DigestValidation3Class::QOP    . $colon
                       . $HA2 );
        $dataArr[] = [
            'GET',
            [
                RestServer::CORRELATIONID => RestServer::getGuid(),
                AuthenticationHandler::AUTHORIZATION => [
                    AuthenticationHandler::REQUIRED      => AuthenticationHandler::DIGEST,
                    RestServer::CALLBACK                 => [
                        [
                            __NAMESPACE__ . '\\DigestValidation3Class',
                            'getResponseRequestExpectedHeaderParams',
                        ],
                        [
                            DigestValidation3Class::CONSUMER,
                            RestServer::CORRELATIONID,
                        ],
                        [
                            __NAMESPACE__ . '\\DigestValidation2Class',
                            'callbackThrowingsException',
                        ],
                        [
                            DigestValidation3Class::CONSUMER, // fix value
                            AuthenticationHandler::USERNAME,
                            AuthenticationHandler::REALM,
                            AuthenticationHandler::NONCE,
                            AuthenticationHandler::URI,
                            AuthenticationHandler::RESPONSE,
                            AuthenticationHandler::ALGORITHM,
                            AuthenticationHandler::CNONCE,
                            AuthenticationHandler::OPAQUE,
                            AuthenticationHandler::QOP,
                            AuthenticationHandler::NC,
                            RestServer::METHOD,
                            RestServer::CORRELATIONID,
                        ],
                    ]
                ],
            ],
            [ // a auth header
                AuthenticationHandler::AUTHORIZATION => 'Digest '
                             . AuthenticationHandler::USERNAME  . $eqq . DigestValidation3Class::USERNAME  . $qend
                    . $comma . AuthenticationHandler::REALM     . $eqq . DigestValidation3Class::REALM     . $qend
                    . $comma . AuthenticationHandler::NONCE     . $eqq . DigestValidation3Class::NONCE     . $qend
                    . $comma . AuthenticationHandler::URI       . $eqq . DigestValidation3Class::URI       . $qend
                    . $comma . AuthenticationHandler::RESPONSE  . $eqq . $response                         . $qend
                    . $comma . AuthenticationHandler::ALGORITHM . $eqq . DigestValidation3Class::ALGORITHM . $qend
                    . $comma . AuthenticationHandler::CNONCE    . $eqq . DigestValidation3Class::CNONCE    . $qend
                    . $comma . AuthenticationHandler::OPAQUE    . $eqq . DigestValidation3Class::OPAQUE    . $qend
                    . $comma . AuthenticationHandler::NC        . $eqq . DigestValidation3Class::NC        . $qend
                    . $comma . AuthenticationHandler::QOP       . $eqq . DigestValidation3Class::QOP       . $qend,
            ],
            500,
        ];

        return $dataArr;
    }

    /**
     * test validateAuthentication Digest 3 (validating using callback)
     *
     * @test
     * @dataProvider validateAuthentication5provider
     */
    public function testValidateAuthentication5(
              $method,
        array $config,
        array $headers,
        $status
    ) {
        $request = new ServerRequest(
            [],                            // serverParams
            [],                            // $uploadedFiles
            null,                      // uri
            null,                   // method
            StreamFactory::createStream(), // body
            $headers                       // headers
        );
        $request                    = $request->withMethod( $method )
                                              ->withAttribute( RestServer::CONFIG, $config );
        list( $request, $response ) = AuthenticationHandler::validateAuthentication(
            $request,
            new Response()
        );
        switch( $status ) {
            case 200 :
                break;
            case 400 :
                break;
            case 401 :
                $this->assertTrue( $response->hasHeader( AuthenticationHandler::WWW_AUTHENTICATE ));
                $headerValue  = $response->getHeader( AuthenticationHandler::WWW_AUTHENTICATE )[0];
                $authCfg      = $config[AuthenticationHandler::AUTHORIZATION];
                $expectedVal  = ucfirst( $authCfg[AuthenticationHandler::REQUIRED] );
                $expectedVal .= sprintf( ' %s="%s"',
                                         AuthenticationHandler::REALM,
                                         DigestValidation3Class::REALM );
                $expectedVal .= sprintf( ',%s="%s"',
                                         AuthenticationHandler::DOMAIN,
                                         DigestValidation3Class::DOMAIN );
                $expectedVal .= sprintf( ',%s=%s',
                                         AuthenticationHandler::STALE,
                                         DigestValidation3Class::STALE );
                $expectedVal .= sprintf( ',%s=%s',
                                         AuthenticationHandler::ALGORITHM,
                                         DigestValidation3Class::ALGORITHM );
                $expectedVal .= sprintf( ',%s="%s"',
                                         AuthenticationHandler::QOP,
                                         DigestValidation3Class::QOP );
                $this->assertEquals( $expectedVal, $headerValue);
                break;
            case 500 :
                // fall through
            default :
                break;
        }
        $this->assertEquals( $status, $response->getStatusCode());
    }

}
