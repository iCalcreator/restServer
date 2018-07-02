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

use Kigkonsult\RestServer\Handlers\AuthenticationHandler;

/**
 * Configuration for the builtin AuthenticationHandler
 * AuthenticationHandler provides simple Basic and Digest authentication.
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 *
 * @see https://en.wikipedia.org/wiki/Basic_access_authentication
 * @see https://tools.ietf.org/html/rfc7617
 * @see https://en.wikipedia.org/wiki/Digest_access_authentication
 * @see https://tools.ietf.org/html/rfc7616
 *
 * RestServer authentication work in three modes:
 *
 *   1. Basic, static usernames and passwords
 *      require config
 *        required
 *        realm
 *        array, [ username => password ]
 *
 *   2. Basic, username and password are checked using callback
 *      requires config
 *        required
 *        realm
 *        callback (array)
 *          callback (callable)
 *          keymap (array) for callback argument values
 *
 *   3. Digest, using two callbacks
 *
 *      All request Authorization (Digest) header values are checked using 2:nd callback
 *
 *      Any (401) response header values 'realm', 'domain', 'nonce', 'opaque', 'stale', 'algorithm', 'qop'
 *        are fetched and set using 1:st callback response
 *
 *      requires config
 *        required
 *        callback (array)
 *          1:st callback (callable)
 *          keymap (array) for 1:st callback argument values
 *          2:nd callback (callable)
 *          keymap (array) for 2:nd callback argument values
 *
 * include config ex:
 * <code>
 * $config[AuthenticationHandler::AUTHORIZATION] = include 'cfg/cfg.3.auth.php';
 * </code>
 * You may also concatenate auth config with restServer config.
 *
 * NOTE :
 *
 *   If NOT using authentication, do NOT include authentication config!
 *
 *   Unvalid configuration gives a 500 response.
 *
 *   No trailing 'charset' in response WWW-Authenticate header, utf8 is assumed.
 *
 *   There is NO management (ignored) for headers
 *     Authentication-Info
 *     Proxy-Authentication-Info
 *     Proxy-Authenticate
 *     Proxy-Authorization
 */

$authcfg = [];

/** ***************************************************************************
 * General authorization config
 *
 * Always used if auth is activated.
 */
/**
 * Ignore unrequired authorization headers
 *
 * value type : bool
 * default false (or not set)
 * If NOT ignored and an unrequired auth is found in incoming request headers,
 * a 403 response is returned (below).
 */
$authcfg[RestServer::IGNORE] = true;

/**
 * Default for (opt) logging
 *   response status 4xx results in logging with prio warning
 *   response status 500 results in logging with prio error
 */

/**
 * StatusCode for response and Authorization Required
 * Will be supplemented by a 'WWW-Authenticate' header
 *
 * value type : int|array
 * default 401, Unauthorized, set only here if other !!
 * Due to security, you can alter logging prio to error
 * using value type array : [ 401, RestServer::ERROR ]
 */
$authcfg[AuthenticationHandler::ERRORCODE1] = 401;

/**
 * StatusCode for response when authorization header(s) is
 * - found
 * - not required
 * - NOT ignored
 *
 * value type : int|array
 * default 403, 'Forbidden', set only here if other !!
 * Due to security, you can alter logging prio to error
 * using value type array : [ 403, RestServer::ERROR ]
 */
$authcfg[AuthenticationHandler::ERRORCODE2] = 403;

/**
 * StatusCode for response when
 * - directive or its value is improper
 * - required directives are missing
 *
 * value type : int|array
 * default (int) 400, 'Bad Request', set only here if other !!
 *
 * Due to security, you can alter logging prio to error
 * using value type array : [ 400, RestServer::ERROR ]
 */
$authcfg[AuthenticationHandler::ERRORCODE3] = 400;

/**
 * StatusCode for response on (authorization) server error
 *
 * value type : int
 * default 500, 'Internal server error', set only here if other !!
 */
$authcfg[AuthenticationHandler::ERRORCODE4] = 500;

/**
 * RestServer authentication work in three modes:
 *
 *   1. Basic, static usernames and passwords
 *      require config
 *        required
 *        realm
 *        array, [ username => password ]
 *
 *   2. Basic, username and password are checked using callback
 *      requires config
 *        required
 *        realm
 *        callback (array)
 *          callback (callable)
 *          keymap (array) for callback argument values
 *
 *   3. Digest, using two callbacks
 *
 *      All request Authorization (Digest) header values are checked using 2:nd callback
 *
 *      Any (401) response header values
 *        'realm', 'domain', 'nonce', 'opaque', 'stale', 'algorithm' and/or 'qop'
 *        are fetched and set using 1:st callback response
 *
 *      requires config
 *        required
 *        callback (array)
 *          1:st callback (callable)
 *          keymap (array) for 1:st callback argument values
 *          2:nd callback (callable)
 *          keymap (array) for 2:nd callback argument values
 *
 * You can only have ONE configuration active, the others MUST be disabled!!
 */
/** ***************************************************************************
 * Mode 1
 *
 *   Basic, static (single) username and password
 *      require config
 *        required
 *        realm
 *        array, [ *(username, password)]
 */

/**
 * Required authentication method
 *
 * value type : string
 */
$authCfg[AuthenticationHandler::REQUIRED] = AuthenticationHandler::BASIC;

/**
 * Realm, used in the response 401 WWW-Authenticate header (when auth required but not found)
 *
 * value type : string
 */
$authCfg[AuthenticationHandler::REALM] = 'This is the realm value';

/**
 * array for userid+passwords
 *
 * value type : array
 */
$authCfg[AuthenticationHandler::PHP_AUTH_USER] = [];

/**
 * One set of password and password
 *
 * value type : string
 */
$authCfg[AuthenticationHandler::PHP_AUTH_USER]['yourUserId'] = 'yourPassword';

/** ***************************************************************************
 * Mode 2
 *
 *   Basic, username and password are checked using callback
 *      requires config
 *        required
 *        realm
 *        callback (array)
 *          callback (callable)
 *          keymap (array) for callback argument values
 */

/**
 * Required authentication method
 *
 * value type : string
 */
$authCfg[AuthenticationHandler::REQUIRED] = AuthenticationHandler::BASIC;

/**
 * Realm, used in the response 401 WWW-Authenticate header (when auth required but not found)
 *
 * value type : string
 */
$authCfg[AuthenticationHandler::REALM] = 'This is the realm value';

/**
 * Callback settings
 *
 * value type : array
 */
$authCfg[RestServer::CALLBACK] = [];

/**
 * Element 1
 * Basic authentication callback, MUST return bool true/false
 *
 * value type : array|callable
 *
 * A callable can be
 *   simple function
 *   anonymous function
 *   instantiated object+method, passed as an array             : [objectInstance, methodName]
 *   class name and static (factory?) method, passed as an array: [namespaceClassName, methodName]
 *   instantiated object, class has an (magic) __call method    : objectInstance
 *   class name, class has an (magic) __callStatic method       : namespaceClassName
 *   instantiated object, class has an (magic) __invoke method  : objectInstance
 */
$authCfg[RestServer::CALLBACK][0] = ['class', 'method'];

/**
 * Element 2
 * a keymap for (subset? of) callback argument values
 * realm are fetched from config (above)
 * userId/password values are fetched from request auth header
 * (opt. CORRELATIONID for traceabilty?)
 * You can remove (all but user+pw) and alter in any order,
 * depending on the callback arguments and order
 * If any part is not found in the request headers,
 * a 400, 'Bad Request' in response
 *
 * value types : string[]
 */
$authCfg[RestServer::CALLBACK][1] = [
    AuthenticationHandler::PHP_AUTH_USER, // request userId
    AuthenticationHandler::PHP_AUTH_PW,   // request password
    AuthenticationHandler::REALM,
    RestServer::CORRELATIONID,
    'fix_value1',                         // will include fix value
    'fix_value_n',                        // etc
];

/** ***************************************************************************
 * Mode 3
 *
 *   Digest, using two callbacks
 *
 *      A 401 response (if any) header values
 *        are fetched using 1:st callback return values
 *        and (concatenated) set as response 'WWW-Authenticate' header value
 *
 *      An incoming request contains Authorization (Digest) header
 *      values are checked using 2:st callback
 *
 *      requires config
 *        required
 *        callback (array)
 *          1:st callback (callable)
 *          keymap (array) for 1:st callback argument values
 *          2:nd callback (callable)
 *          keymap (array) for 2:nd callback argument values
 */

/**
 * Required authentication method
 *
 * value type : string
 */
$authCfg[AuthenticationHandler::REQUIRED] = AuthenticationHandler::DIGEST;

/**
 * Callbacks settings
 *
 * value type : array
 */
$authCfg[RestServer::CALLBACK] = [];

/**
 * Element 1
 * Digest 1:st callback
 *
 * value type : array|callable
 *
 * Callback MUST return an (assoc) array with key/values of (subset? of) the
 * following digest 'WWW-Authenticate' header parameters :
 *   AuthenticationHandler::REALM,
 *   AuthenticationHandler::DOMAIN,
 *   AuthenticationHandler::NONCE,
 *   AuthenticationHandler::OPAQUE,
 *   AuthenticationHandler::STALE,
 *   AuthenticationHandler::ALGORITHM,
 *   AuthenticationHandler::QOP
 *
 * The array key/values (concatenated) are used in (401) response 'WWW-Authenticate' header value
 *
 * If the optional charset and userhash parameters are included, they are ignored.
 * You don't need to double quote values.
 */
$authCfg[RestServer::CALLBACK][0] = ['classInstance', 'method'];

/**
 * Element 2
 * A keymap (array) for 1:st callback argument values
 * also contains opt. fixed values
 * (opt. CORRELATIONID for traceabilty?)
 * Arrange in order
 *
 * value types : mixed[]
 */
$authCfg[RestServer::CALLBACK][1] = [
    RestServer::CORRELATIONID,
    'fix_value1',                           // will include fix value
    'fix_value_n',                          // etc
];

/**
 * Element 3
 * Digest 2:st callback,
 * Evaluate parsed request (Digest) 'Authorization' header values
 * MUST return (bool) true/false
 *
 * value type : array|callable
 */
$authCfg[RestServer::CALLBACK][2] = ['classInstance', 'method'];

/**
 * Element 4
 * A keymap (array) for callback argument values
 *   all AuthenticationHandler values are fetched from (unpacked) request auth header
 *   (username* and userhash are excluded),
 *   opt method from request (may be required to calculated response)
 *   opt. CORRELATIONID for traceabilty?
 * You can remove (all but username) and arrange in any order,
 * depending on the callback arguments.
 * If any part is not found in the request header,
 * a 401 response is returned as well as invalid authentication.
 *
 * value types : string[]
 */
$authCfg[RestServer::CALLBACK][3] = [
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
    'fix_value1',                           // will include fix value
    'fix_value_n',                          // etc
];

/** ***************************************************************************
 * Add to main config
 * <code>
 * $config[AuthenticationHandler::AUTHORIZATION] = include 'cfg/cfg.3.auth.php';
 * </code>
 */
return $authcfg;
