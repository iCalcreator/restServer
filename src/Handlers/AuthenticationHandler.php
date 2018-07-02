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

namespace Kigkonsult\RestServer\Handlers;

use Psr\Http\Message\ServerRequestInterface;
use Kigkonsult\RestServer\ResponseInterface;
use Kigkonsult\RestServer\RestServer;
use RuntimeException;
use Exception;

/**
 * AuthenticationHandler provides request authentication support
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 *
 * RestServer authentication work in three modes:
 *
 *   1. Basic, static usernames and passwords
 *      require config
 *        expected
 *        realm
 *        array, [ username => password ]
 *
 *   2. Basic, username and password are checked using callback
 *      requires config
 *        expected
 *        realm
 *        callback (array)
 *          callback (callable)
 *          keymap (array) for callback argument values
 *
 *   3. Digest, using two callbacks
 *
 *      All request Authorization (Digest) header values are checked using 2:nd callback
 *
 *      one or more (401) response header values
 *        'realm', 'domain', 'nonce', 'opaque', 'stale', 'algorithm', 'qop'
 *        are fetched and set using 1:st callback response
 *
 *      requires config
 *        expected
 *        callback (array)
 *          1:st callback (callable)
 *          keymap (array) for 1:st callback argument values
 *          2:nd callback (callable)
 *          keymap (array) for 2:nd callback argument values
 *
 * @see cfg/cfg.3.auth.php
 * @see https://en.wikipedia.org/wiki/Basic_access_authentication
 * @see https://tools.ietf.org/html/rfc7617
 * @see https://en.wikipedia.org/wiki/Digest_access_authentication
 * @see https://tools.ietf.org/html/rfc7616
 *
 */
class AuthenticationHandler extends AbstractCAHandler implements HandlerInterface
{
    /**
     * Class constants, header keys
     */
    const AUTHORIZATION    = 'Authorization';                       // Request header
    const PHP_AUTH_USER    = 'PHP-AUTH-USER';                       // Request header
    const PHP_AUTH_PW      = 'PHP-AUTH-PW';                         // Request header
    const AUTH_TYPE        = 'AUTH-TYPE';                           // Request header
    const PHP_AUTH_DIGEST  = 'PHP-AUTH-DIGEST';                     // Request header
    const WWW_AUTHENTICATE = 'WWW-Authenticate';                    // Response header

    /**
     * Class constants, cfg keys etc
     */
    const BASIC            = 'Basic';
    const DIGEST           = 'Digest';

    /**
     * Class constants, request/response header keys
     */
    const ALGORITHM        = 'algorithm';
    const DOMAIN           = 'domain';
    const CNONCE           = 'cnonce';
    const NC               = 'nc';
    const NONCE            = 'nonce';
    const OPAQUE           = 'opaque';
    const QOP              = 'qop';
    const REALM            = 'realm';
    const RESPONSE         = 'response';
    const STALE            = 'stale';
    const URI              = 'uri';
    const USERNAME         = 'username';

    /**
     * All digest request authorization header parts
     */
    private static $authHeaderParts = [
        self::USERNAME,
        self::REALM,
        self::NONCE,
        self::URI,
        self::RESPONSE,
        self::ALGORITHM,
        self::CNONCE,
        self::OPAQUE,
        self::QOP,
        self::NC,
    ];

    /**
     * All digest response (401) authorization expected header parts
     */
    private static $authExpectedHeaderParts = [
        self::REALM,
        self::DOMAIN,
        self::NONCE,
        self::OPAQUE,
        self::STALE,
        self::ALGORITHM,
        self::QOP,
    ];

    /**
     * response header WWW-Authenticate quoted parameter values
     */
    static $QUOTED = [
        self::REALM,
        self::DOMAIN,
        self::NONCE,
        self::OPAQUE,
        self::QOP,
    ];

    /**
     * Default config return error status codes
     */
    private static $DEFAULTS = [
        self::ERRORCODE1 => 401, // 'Unauthorized', authorization requested
        self::ERRORCODE2 => 403, // 'Forbidden', authorization header found but not expected and not ignored
        self::ERRORCODE3 => 400, // 'Bad Request', authorization rejected
        self::ERRORCODE4 => 500, // 'Internal server error'
    ];

    /**
     * Error status codes to check changed log prio from cfg
     */
    private static $STATUSCODESWITHALTLOGPRIO = [
        self::ERRORCODE1,
        self::ERRORCODE2,
        self::ERRORCODE3
    ];

    /**
     * misc.
     */
    private static $COLON = ':';

    /**
     * Return bool true if header is a Auth header
     *
     * @param string $header
     * @return bool
     * @static
     */
    public static function isAuthHeader(
        $header
    ) {
        static $PHPAUTH  = 'PHP_AUTH';
        $header = \strtr( $header, self::$US, self::$D );
        return (( 0 === \strcasecmp( \substr( $header, 0, 7 ), $PHPAUTH )) ||
                ( 0 === \strcasecmp( $header, self::AUTH_TYPE )));
    }

    /**
     * Handler validating authentication
     * work in three modes:
     * - Basic, static usernames and passwords
     * - Basic, username and password are checked using callback
     * - Digest, using one callback for checking and another for 401 response values
     * Review cfg/cfg.3.auth.php for details.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @static
     * @see https://en.wikipedia.org/wiki/Basic_access_authentication
     * @see https://tools.ietf.org/html/rfc7617
     * @see https://en.wikipedia.org/wiki/Digest_access_authentication
     * @see https://tools.ietf.org/html/rfc7616
     */
    public static function validateAuthentication(
        ServerRequestInterface $request,
        ResponseInterface      $response
    )
    {
        static $FMT1   = 'Authentication config in error';
        static $FMT2   = 'Authentication found but not expected';
        if( parent::earlierErrorExists( $request )) {
            return [
                $request,
                $response,
            ];
        }
        $authCfg       = parent::getConfig(
            $request,
            self::AUTHORIZATION,
            self::$DEFAULTS,
            self::$STATUSCODESWITHALTLOGPRIO
        );
        if( ! self::isAuthCfgOk( $authCfg )) {
            return self::returnAuthenticationError(
                $request,
                $response,
                $authCfg,
                new RuntimeException( $FMT1 )
            );
        } // end if

        $authIgnore    = ( isset( $authCfg[RestServer::IGNORE] ) &&
                         ( true === $authCfg[RestServer::IGNORE] ));
        $authExpected  = ( isset( $authCfg[self::REQUIRED] ) && ! $authIgnore );
        $hasAuthHeader =
            $request->hasHeader( self::AUTHORIZATION ) ||
            $request->hasHeader( self::PHP_AUTH_USER ) ||
            $request->hasHeader( self::AUTH_TYPE )     ||
            $request->hasHeader( self::PHP_AUTH_DIGEST );

        $errorMsg = null;
        switch ( true ) {
            case (( ! $authExpected || $authIgnore ) && ! $hasAuthHeader ) :
                // Auth not expected and not found, ok
                break;
            case ( $authIgnore && $hasAuthHeader ) :
                // Auth not expected but found and ignored, ok
                break;
            case ( ! $authExpected && $hasAuthHeader ) :
                // Auth not expected but found and not ignored, error 2
                return self::doLogReturn(
                    $request->withAttribute( RestServer::ERROR, true ),
                    $response->withStatus( $authCfg[self::ERRORCODE2][0] ),
                    new RuntimeException( $FMT2 ),
                    $authCfg[self::ERRORCODE2][1]
                );
                break;
            case ( self::BASIC == $authCfg[self::REQUIRED]  ) :
                // mode 1 & 2
                return self::ManageBasicAuthentication(
                    $request,
                    $response,
                    $authCfg
                );
                break;
            case ( self::DIGEST == $authCfg[self::REQUIRED]  ) :
                // mode 3
                return self::ManageDigestAuthentication(
                    $request,
                    $response,
                    $authCfg
                );
                break;
            default :
                return self::returnAuthenticationError(
                    $request,
                    $response,
                    $authCfg,
                    new RuntimeException( $FMT1 )
                );
                break;
        } // end switch
        return [
            $request,
            $response,
        ];
    }

    /**
     * Return response Authentication expected (401 + WWW_AUTHENTICATE)
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param array                  $headerParams
     * @param array                  $authCfg
     * @param string                 $errorMsg
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @access private
     * @static
     */
    private static function returnAuthenticationExpected(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $headerParams,
        array                  $authCfg,
                               $errorMsg
    ) {
        static $FMT1   = '%s ';
        static $FMT2   = '%s="%s"';
        static $FMT3   = '%s=%s';
        static $GLUE   = ',';
        $headerValue   = \sprintf( $FMT1, \ucfirst( $authCfg[self::REQUIRED] ));
        $headerParts   = [];
        foreach( $headerParams as $key => $value ) {
            $fmt       = ( \in_array( $key, self::$QUOTED )) ? $FMT2 : $FMT3;
            $headerParts[] = \sprintf( $fmt, $key, $value );
        }
        $headerValue  .= \implode( $GLUE, $headerParts );
        return self::doLogReturn(
            $request->withAttribute( RestServer::ERROR, true ),
            $response->withStatus( $authCfg[self::ERRORCODE1][0] )
                     ->withHeader( self::WWW_AUTHENTICATE, $headerValue ),
            new RuntimeException( $errorMsg ),
            $authCfg[self::ERRORCODE1][1]
        );
    }

    /**
     * Return response Bad Authentication (400)
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param array                  $authCfg
     * @param string                 $errorMsg
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @access private
     * @static
     */
    private static function returnBadAuthentication(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $authCfg,
                               $errorMsg
    ) {
        return self::doLogReturn(
            $request->withAttribute( RestServer::ERROR, true ),
            $response->withStatus( $authCfg[self::ERRORCODE3][0] ),
            new RuntimeException( $errorMsg ),
            $authCfg[self::ERRORCODE3][1]
        );
    }

    /**
     * Return (server) Authentication error
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param array                  $authCfg
     * @param Exception              $e
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @access private
     * @static
     */
    private static function returnAuthenticationError(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $authCfg,
        Exception              $e
    ) {
        return self::doLogReturn(
            $request->withAttribute( RestServer::ERROR, true ),
            $response->withStatus( $authCfg[self::ERRORCODE4] ),
            $e,
            RestServer::ERROR
        );
    }

    /**
     * Check config
     *
     * @param array $authCfg
     * @return bool
     * @access private
     * @static
     */
    private static function isAuthCfgOk(
        array $authCfg
    ) {
        switch( true ) {
            case ( ! isset( $authCfg[self::REQUIRED] )) :
                return true;
                break;
            case ( self::BASIC == $authCfg[self::REQUIRED]  ) :
                switch( true ) {
                    case ( ! isset( $authCfg[self::REALM] )) :
                        break 2;
                    case ( isset( $authCfg[self::PHP_AUTH_USER] ) &&
                       \is_array( $authCfg[self::PHP_AUTH_USER] ) &&
                         ! empty( $authCfg[self::PHP_AUTH_USER] )) :
                        return true;
                        break 2;
                    case ( ! isset( $authCfg[RestServer::CALLBACK] )) :
                        break 2;
                    case ( ! \is_callable( $authCfg[RestServer::CALLBACK][0] )) :
                        break 2;
                    case ( ! \is_array(    $authCfg[RestServer::CALLBACK][1] )) :
                        break 2;
                    default :
                        return true;
                        break;
                } // end switch
            case ( self::DIGEST == $authCfg[self::REQUIRED]  ) :
                switch( true ) {
                    case ( ! \is_array(    $authCfg[RestServer::CALLBACK] )) :
                        break 2;
                    case ( 4 != \count(    $authCfg[RestServer::CALLBACK] )) :
                        break 2;
                    case ( ! \is_callable( $authCfg[RestServer::CALLBACK][0])) :
                        break 2;
                    case ( ! \is_array(    $authCfg[RestServer::CALLBACK][1] )) :
                        break 2;
                    case ( ! \is_callable( $authCfg[RestServer::CALLBACK][2] )) :
                        break 2;
                    case ( ! \is_array(    $authCfg[RestServer::CALLBACK][3] )) :
                        break;
                    default :
                        return true;
                        break;
                } // end switch
                break;
            default :
                break;
        } // end switch
        return false;
    }

    /**
     * Manage Basic authentication
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param array                  $authCfg
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @access private
     * @static
     */
    private static function ManageBasicAuthentication(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $authCfg
    ) {
        static $FMT3 = 'Invalid Basic authentication';
        $errorMsg    = null;
        $validation  = self::validateBasicHeaders(
            $request,
            $errorMsg
        );
        if( 1 == $validation ) { // invalid auth headers
            return self::returnBadAuthentication(
                $request,
                $response,
                $authCfg,
                $errorMsg
            );
        }
        elseif( 2 == $validation ) { // missing expected auth headers
            return self::returnAuthenticationExpected(
                $request,
                $response,
                [ self::REALM => $authCfg[self::REALM] ],
                $authCfg,
                $errorMsg
            );
        }
        $basicUser = $basicPass = null;
        if( empty( $errorMsg )) {
            list( $basicUser, $basicPass ) = self::getUserPwdFromBasicAuthRequest( $request, $errorMsg );
        }
        if( ! empty( $errorMsg )) {
            return self::returnBadAuthentication(
                $request,
                $response,
                $authCfg,
                $errorMsg
            );
        }
        try {
            $authValidOk = self::validateBasicUserPwd(
                $basicUser,
                $basicPass,
                $authCfg,
                $errorMsg
            );
        } catch( Exception $e ) {
            return self::returnAuthenticationError(
                $request,
                $response,
                $authCfg,
                $e
            );
        }
        if( $authValidOk ) {
            return [
                $request,
                $response
            ];
        }
        return self::returnBadAuthentication(
            $request,
            $response,
            $authCfg,
            $FMT3
        );
    }

    /**
     * Validate request Basic headers
     *
     * @param ServerRequestInterface $request
     * @param string                 $errorMsg
     * @return int
     * @access private
     * @static
     */
    private static function validateBasicHeaders(
        ServerRequestInterface $request,
                             & $errorMsg
    ) {
        static $FMT1  = 'Invalid basic authentication headers';
        static $FMT2  = 'Basic authentication expected';
        $errorMsg     = null;
        $returnCode   = 0;
        $hasAuthType  = $request->hasHeader( self::AUTH_TYPE );
        if( $hasAuthType ) {
            $AuthType = \trim( $request->getHeader( self::AUTH_TYPE )[0] );
            if ( 0 != \strcasecmp( self::BASIC, $AuthType )) {
                $errorMsg   = $FMT2;
                return 1;
            }
        } // end if
        if( $request->hasHeader( self::AUTHORIZATION )) {
            $authHeader   = \trim( $request->getHeader( self::AUTHORIZATION )[0] );
            $authType     = \substr( $authHeader, 0, 5 );
            if( 0 != \strcasecmp( self::BASIC, $authType )) {
                $errorMsg = \sprintf( $FMT1, $authType );
                return 1;
            }
            return 0;
        }
        if( ! $request->hasHeader( self::PHP_AUTH_USER )) {
            if( $hasAuthType ) {
                $returnCode = 1;
                $errorMsg   = $FMT2;
            }
            else {
                $returnCode = 2;
                $errorMsg   = $FMT1;
            }
        }
        return $returnCode;
    }

    /**
     * Return username and passwd from request
     *
     * @param ServerRequestInterface $request
     * @param string                 $errorMsg
     * @return array [username, passwd]
     * @access private
     * @static
     */
    private static function getUserPwdFromBasicAuthRequest(
        ServerRequestInterface $request,
                             & $errorMsg
    ) {
        static $FMT2 = 'Basic auth decode error';
        $errorMsg = $basicUser = $basicPass = null;
        if( $request->hasHeader( self::PHP_AUTH_USER )) {
            $basicUser = $request->getHeader( self::PHP_AUTH_USER )[0];
            $basicPass = $request->hasHeader( self::PHP_AUTH_PW )
                       ? $request->getHeader( self::PHP_AUTH_PW )[0]
                       : null;
            return [
                $basicUser,
                $basicPass
            ];
        }
        $authHeader   = \trim( $request->getHeader( self::AUTHORIZATION )[0] );
        $authValue    = \base64_decode( \substr( \trim( $authHeader ), 6 ));
        if(( false !== $authValue ) &&
           ( false !== \strpos( $authValue, self::$COLON ))) {
            list( $basicUser, $basicPass ) = \explode( self::$COLON, $authValue );
        }
        else {
                $errorMsg = $FMT2;
        } // end else
        return [
            $basicUser,
            $basicPass
        ];
    }

    /**
     * Validate basic username and passwd
     *
     * @param string $basicUser
     * @param string $basicPass
     * @param array  $authCfg
     * @param string $errorMsg
     * @return bool
     * @throws Exception
     * @access private
     * @static
     */
    private static function validateBasicUserPwd(
               $basicUser,
               $basicPass,
        array  $authCfg,
             & $errorMsg
    ) {
        static $FMT4 = 'Basic authentication #%d failed';
        $errorMsg    = null;
        if( isset( $authCfg[self::PHP_AUTH_USER] )) {
        // auth Basic 1 mode
            foreach( $authCfg[self::PHP_AUTH_USER] as $cfgUserId => $cfgPassWord ) {
                if(( $basicUser == $cfgUserId ) &&
                   ( $basicPass == $cfgPassWord )) {
                    return true;
                }
            }
            $errorMsg = \sprintf( $FMT4, 1 );
            return false;
        } // end if
        // auth Basic 2 mode
        if( self::execCallback(
            $authCfg[RestServer::CALLBACK][0],
            $authCfg[RestServer::CALLBACK][1],
            [
                self::PHP_AUTH_USER       => $basicUser,
                self::PHP_AUTH_PW         => $basicPass,
                self::REALM               => $authCfg[self::REALM],
                RestServer::CORRELATIONID => $authCfg[RestServer::CORRELATIONID],
            ]
        )) {
            return true;
        }
        $errorMsg = \sprintf( $FMT4, 2 );
        return false;
    }

    /**
     * Exec (config) callback
     *
     * @param callable $callback
     * @param array    $arguments
     * @param array    $data
     * @return mixed
     * @throws Exception
     * @access private
     * @static
     */
    private static function execCallback(
        $callback,
        array $arguments,
        array $data
    ) {
        $args       = [];
        foreach( $arguments as $x => $callBackArg ) {
            $args[] = ( isset( $data[ $callBackArg] )) ? $data[ $callBackArg] : $callBackArg;
        } // end foreach
        $result     = null;
        $error      = null;
        \set_error_handler( RestServer::$errorHandler );
        try {
            $result = \call_user_func_array( $callback, $args );
        } catch( Exception $e ) {
            $error  = new RuntimeException( $e->getMessage(), $e->getCode(), $e );
        } finally {
            \restore_error_handler();
        }
        if( $error instanceof Exception ) {
            throw $error;
        }
        return $result;
    }

    /**
     * Manage Digest authentication
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param array                  $authCfg
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @access private
     * @static
     * @see https://evertpot.com/223/
     * @see https://secure.php.net/manual/en/features.http-auth.php
     */
    private static function ManageDigestAuthentication(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $authCfg
    ) {
        $errorMsg    = null;
        static $FMT1 = 'Digest authentication headers missing';
        $validation  = self::validateDigestHeaders(
            $request,
            $errorMsg
        );
        if( 1 == $validation ) { // invalid auth headers
            return self::returnBadAuthentication(
                $request,
                $response,
                $authCfg,
                $errorMsg
            );
        }
        elseif( 2 == $validation ) { // missing expected auth headers
            return self::getDigestResponseAuthParamsAndReturnAuthenticationExpected(
                $request,
                $response,
                $authCfg,
                $FMT1
            );
        }
        $authData    = self::getDigestAuthorizationRequestHeaderValue( $request );
        $authDataArr = self::parseRequestAuthorizationHeaderDigestValue(
            $authData,
            $authCfg,
            $errorMsg
        );
        if( ! empty( $errorMsg )) {
            return self::returnBadAuthentication(
                $request,
                $response,
                $authCfg,
                $errorMsg
            );
        } // end if
        $authDataArr[RestServer::METHOD]        = $request->getMethod();
        $authDataArr[RestServer::CORRELATIONID] = $authCfg[RestServer::CORRELATIONID];

        try { // external digest request auth approval
            $authValidOk = self::execCallback(
                $authCfg[RestServer::CALLBACK][2],
                $authCfg[RestServer::CALLBACK][3],
                $authDataArr
            );
        } catch( Exception $e ) {
            return self::returnAuthenticationError(
                $request,
                $response,
                $authCfg,
                $e
            );
        }
        if( $authValidOk ) {
            return [
                $request,
                $response
            ];
        }
        return self::returnBadAuthentication(
            $request,
            $response,
            $authCfg,
            $errorMsg
        );
    }

    /**
     * Validate request Digest headers
     *
     * @param ServerRequestInterface $request
     * @param string                 $errorMsg
     * @return int
     * @access private
     * @static
     */
    private static function validateDigestHeaders(
        ServerRequestInterface $request,
        & $errorMsg
    ) {
        static $FMT1  = 'Invalid digest authentication headers';
        static $FMT2  = 'Digest authentication expected';
        $errorMsg     = null;
        $returnCode   = 0;
        $hasAuthType  = $request->hasHeader( self::AUTH_TYPE );
        if( $hasAuthType ) {
            $AuthType = \trim( $request->getHeader( self::AUTH_TYPE )[0] );
            if ( 0 != \strcasecmp( self::DIGEST, $AuthType )) {
                $returnCode = 1;
                $errorMsg   = $FMT2;
            }
        } // end if
        if( ! $request->hasHeader( self::PHP_AUTH_DIGEST ) &&
            ! $request->hasHeader( self::AUTHORIZATION )) {
            if( $hasAuthType ) {
                $returnCode = 1;
                $errorMsg   = $FMT2;
            }
            else {
                $returnCode = 2;
                $errorMsg   = $FMT1;
            }
        }
        return $returnCode;
    }

    /**
     * Return dDigest authorization request header value
     *
     * @param ServerRequestInterface $request
     * @return string
     * @access private
     * @static
     */
    private static function getDigestAuthorizationRequestHeaderValue(
        ServerRequestInterface $request
    ) {
        if( $request->hasHeader( self::PHP_AUTH_DIGEST )) {
            return $request->getHeader( self::PHP_AUTH_DIGEST )[0];
        }
        else {
            return \substr( $request->getHeader( self::AUTHORIZATION )[0], 7 );
        }
    }

    /**
     * Fetch digest response auth params and return authorization request header
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param array                  $authCfg
     * @param string                 $errorMsg
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @access private
     * @static
     */
    private static function getDigestResponseAuthParamsAndReturnAuthenticationExpected(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $authCfg,
                               $errorMsg
    ) {
        static $FMT0 = 'Digest authentication failed, ';
        static $FMT1 = 'callback error';
        static $FMT2 = 'no or empty (valid) requestExpected arg list from callback';
        $params = $authCfg;
        try {
            $returnParams = self::execCallback(
                $authCfg[RestServer::CALLBACK][0],
                $authCfg[RestServer::CALLBACK][1],
                $params
            );
        } catch( Exception $e ) {
            return self::returnAuthenticationError(
                $request,
                $response,
                $authCfg,
                new RuntimeException( $FMT0 . $FMT1, 500, $e )
            );
        }
        if( ! is_array( $returnParams ))
            $returnParams = [ $returnParams ];
        $headerParams = [];
        foreach( $returnParams as $rKey => $rValue ) {
            $rKey     = \strtolower( $rKey );
            if( in_array( $rKey, self::$authExpectedHeaderParts ))
                $headerParams[$rKey] = $rValue;
        }
        if( empty( $headerParams )) {
            return self::returnAuthenticationError(
                $request,
                $response,
                $authCfg,
                new RuntimeException( $FMT0 . $FMT2, 500 )
            );
        }
        return self::returnAuthenticationExpected(
            $request,
            $response,
            $headerParams,
            $authCfg,
            $errorMsg
        );
    }

    /**
     * Return digest parsed authData as array
     *
     * @param String  $authData
     * @param array   $authCfg
     * @param string  $errorMsg
     * @return array|null
     * @access private
     * @static
     */
    private static function parseRequestAuthorizationHeaderDigestValue(
              $authData,
        array $authCfg,
            & $errorMsg
    ) {
        static $FMT2   = 'Digest authentication parse error';
        static $FMT3   = 'Digest authentication username parse error';
        $requiredParts = [];
        // prep required part to find in header value
        foreach( $authCfg[RestServer::CALLBACK][3] as $key ) {
            if( \in_array( $key, self::$authHeaderParts )) {
                $requiredParts[$key] = 1;
            }
        }
        $authDataArr   = self::http_digest_parse(
            $authData,
            $requiredParts
        );
        if( false === $authDataArr ) {
            $errorMsg  = $FMT2;
            return null;
        }
        elseif( ! isset( $authDataArr[self::USERNAME] )) {
            $errorMsg  = $FMT3;
            return null;
        }
        return $authDataArr;
    }

    /**
     * Return parsed http auth header
     *
     * @param string $stringToParse
     * @param array  $requiredParts
     * @return array|bool
     * @access private
     * @static
     * @see https://secure.php.net/manual/en/features.http-auth.php
     */
    private static function http_digest_parse(
               $stringToParse,
        array  $requiredParts
    ) {
        static $pattern1 = '@(';
        static $pattern2 = ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@';
        static $glue     = '|';
        $keys            = \implode( $glue, array_keys( $requiredParts ));
        $pattern         = $pattern1 . $keys . $pattern2;
        \preg_match_all( $pattern, $stringToParse, $matches, PREG_SET_ORDER );
        $data            = [];
        foreach( $matches as $m ) {
            $data[$m[1]] = $m[3] ? $m[3] : $m[4];
            unset( $requiredParts[$m[1]] );
        }
        return ( empty( $requiredParts )) ? $data : false;
    }
}
