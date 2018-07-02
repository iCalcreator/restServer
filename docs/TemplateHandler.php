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

/**
 * Template for Handlers
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 *
 * You can add three kind of Handlers:
 *
 * - General Handlers, fired of before service exec
 *
 * - Serializing/encoding util Handlers, described at the end
 *
 * - final handler, same as general but no response
 */

/**
 * General Handlers
 *
 * A (callable) handler can be
 *   simple function
 *   anonymous function
 *   instantiated object+method, passed as an array             : [object, methodName]
 *   class name and static (factory?) method, passed as an array: [namespaceClassName, methodName]
 *   instantiated object, class has an (magic) __call method    : object
 *   class name, class has an (magic) __callStatic method       : namespaceClassName
 *   instantiated object, class has an (magic) __invoke method  : object
 *
 * The callable MUST
 *   have two arguments, ServerRequestInterface and ResponseInterface
 *   return array [ ServerRequestInterface, ResponseInterface ]
 *
 * Exceptions (if any), thrown from handler (as well as PHP errors), are catched,
 * opt. logged and resulting in 500 response.
 *
 * The callable have access to all methods as described in
 *   Psr\Http\Message\ServerRequestInterface;
 *   Psr\Http\Message\ResponseInterface;
 *
 * A strong recommendation is to place handlers (as well as config)
 * outside webserver document root.
 *
 * You can attach general Handlers in three ways:
 *
 * $config = [];
 * $config[RestServer::HANDLERS]   = [];
 * $config[RestServer::HANDLERS][] = [ <handlerClass with namespace>, <callbackMethod> ];
 * $config[RestServer::HANDLERS][] = $anotherCallback;
 * ...
 * (new RestServer( $config ))->run();
 *
 * OR
 *
 * (new RestServer( $config ))->addHandler( $callback )->run();
 *
 * OR
 *
 * $RestServer = new RestServer( $config );
 * $RestServer->addHandler( $callback );
 * ...
 * $RestServer->run();
 *
 * The first two ends up with the RestServer::addHandler method
 * Note, RestServer::addHandler method may throw InvalidArgumentException on callback check error
 *
 * For final handler (one only), in the same way but
 *   config key is RestServer::FINALHANDLER
 *   method RestServer::addFinalHandler()
 */

namespace yourNamespace\Handlers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Kigkonsult\RestServer\RESTserver;
use Kigkonsult\RestServer\Handlers\HandlerInterface;

class TemplateHandlerClass implements HandlerInterface
{
/**
 * Class constants, headers, cfg keys etc
 */
    const TEMPLATE = 'template';

/**
 * Template handler method
 *
 * @param ServerRequestInterface $request
 * @param ResponseInterface      $response
 * @return [ ServerRequestInterface, ResponseInterface ]
 */
    public static function templateHandlerMethod(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {

        // your code here....

        // opt. access to the config
        $config = $request->getAttribute( RESTserver::CONFIG, [] );

        // opt. if logger is set, ref. RestServer.php
        $logger = RestServer::getLogger();
        if ( ! empty( $logger ) && \method_exists( $logger, RestServer::ERROR )) {
            $Logger->{RestServer::ERROR}(  'message' );
        }

        // example, read request header, set attribute
        if ( $request->hasHeader( self::TEMPLATE )) {
            $request = $request->withAttribute(
                self::TEMPLATE,
                $request->getHeader( self::TEMPLATE )
            );
        }

        $error = false;
        // on error return example (the attribute is required, will force immediate response emit)
        if( $error ) {
            return [
                $request->withAttribute( RestServer::ERROR, true ),
                $response->withStatus( 500 ), // return status for internal server error
            ];                                     // 4xx for invalid input message
                                                   // explore src/Response.php for all codes
        }
        // success return
        return [
            $request,
            $response,
        ];

    }
}

/**
 *  If you (really?) want to override or force RESPONSE default serialization/encoding :
 *  and NOT using the config fallback option (see cfg/cfg.56.cte.php),
 *  default 'Accept' (i.e. if not exists in request) : 'application/json'
 *  default 'Accept-Encoding' (i.e. if not exists in request) : 'gzip'
 *  (They are (otherwise) captured from request, if set)
 *  Make sure the new ones are supported of RestServer
 *  unSerialization/deCoding are fired of before routing callback exec,
 *    updates *request* message body/parsedBody
 *  serialization/encoding are fired of after routing callback exec,
 *    updates *response* message
 */
$request = $request->withAttribute(
    Kigkonsult\RestServer\Handlers\ContentTypeHandler::ACCEPT,
    'application/json'
);

$request = $request->withAttribute(
    Kigkonsult\RestServer\Handlers\EncodingHandler::ACCEPTENCODING,
    'gzip'
);

/**
 * Serializing/encoding util Handlers
 *
 * Serializing util Handlers MUST implement
 *   Kigkonsult\RestServer\Handlers\ContentTypeHandlers\ContentTypeInterface
 *   i.e. they MUST have two methods:
 *   unSerialize( $data, $options=null )
 *   serialize( $data, $options=null )
 *
 * The handler may throw RuntimeExceptions.
 *
 * You can add your util handler (or replace existing) using
 * <code>
 * Kigkonsult\RestServer\Handlers\ContentTypeHandler::register( 'application/janson',
 *                                                               <yourClassWithNamespace> )
 * </code>
 * There are also methods
 * Kigkonsult\RestServer\Handlers\ContentTypeHandler::unRegister( <content-type> )
 *   remove handler for <content-type> (if found, otherwise null)
 * Kigkonsult\RestServer\Handlers\ContentTypeHandler::getRegister( <content-type> )
 *   get handler directives for <content-type> (if found, otherwise null)
 *   <content-type>=null gives all directives
 *
 * You can alter or set Serializing options in config (see also cfg/cfg56.cte.php)
 * ex (for application/json)
 * <code>
 * $config['application/json'][ContentTypeHandler::UNSERIALIZEOPTIONS] = JSON_OBJECT_AS_ARRAY | JSON_BIGINT_AS_STRING;
 * </code>
 * Defaults:
 * jsonHandler - UNSERIALIZEOPTIONS = JSON_OBJECT_AS_ARRAY
 * XMLHandler  - UNSERIALIZEOPTIONS = null (=libxml parameters)
 *
 * All formats:
 * config[<content-type>][ContentTypeHandler::UNSERIALIZEOPTIONS] = <value>
 * config[<content-type>][ContentTypeHandler::SERIALIZEOPTIONS]   = <value>
 *
 * There are two shortcuts
 * All contentTypes suffixed by
 *   '+json' are using existing 'jsonHandler'
 *   '+xml' are using existing 'XMLHandler'
 *
 *
 *
 * Encoding util Handlers MUST implement
 *   Kigkonsult\RestServer\Handlers\EncodingHandlers\EncodingInterface
 *   i.e. they MUST have two methods:
 *   deCode( $data, $level=null, $options=null )
 *   enCode( $data, $level=null, $options=null )
 *
 * The handler may throw RuntimeExceptions.
 *
 * You can add your util handler (or replace existing) using
 * <code>
 * Kigkonsult\RestServer\Handlers\EncodingHandler::register( <encoding>,
 *                                                           <yourClassWithNamespace> )
 * </code>
 * There are also methods
 * Kigkonsult\RestServer\Handlers\EncodingHandler::unRegister( <encoding> )
 *   remove handler for <encoding> (if found, otherwise null)
 * Kigkonsult\RestServer\Handlers\EncodingHandler::getRegister( <encoding> )
 *   get handler directives for <encoding> (if found, otherwise null)
 *   <encoding>=null gives all directives
 *
 * You can alter or set deCode/enCode level/options in config
 * ex (for gzip)
 * <code>
 * $config['gzip'][EncodingHandler::ENCODELEVEL]   = -1;
 * $config['gzip'][EncodingHandler::ENCODEOPTIONS] = FORCE_GZIP;
 * </code>
 * Defaults:
 * DeflateHandler - ENCODEOPTIONS = ZLIB_ENCODING_DEFLATE
 * GzipHandler    - ENCODEOPTIONS = FORCE_GZIP
 *
 * All formats:
 * config[<encoding>][EncodingHandler::DECODELEVEL]   = <value>
 * config[<encoding>][EncodingHandler::DECODEOPTIONS] = <value>
 * config[<encoding>][EncodingHandler::ENCODELEVEL]   = <value>
 * config[<encoding>][EncodingHandler::ENCODEOPTIONS] = <value>
 */
