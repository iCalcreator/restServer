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

use Kigkonsult\RestServer\Handlers\ContentTypeHandler;
use Kigkonsult\RestServer\Handlers\EncodingHandler;

/**
 * Configuration for the builtin ContentTypeHandler and EncodingHandler
 *
 * This config is included in the RestServer config using append
 *
 * ex
 * $config += include 'cfg/cfg.56.cte.php';
 * Most config keys here have only default values set,
 *   include ONLY on after update!!
 */
$cteCfg = [];

/** *********************************************************************
* Content-types config
*
* For more info, see docs/TemplateHandler.php
*/
/**
* If request 'Accept' header is missing (response message content-type),
*   you can alter the fallback content-type
* default value : 'application/json', set here if other !!
* Make sure the value is supported
*
* value type : string
*
* If you want NO response serializing as fallback, set (bool) false
*/
$cteCfg[ContentTypeHandler::ACCEPT][ContentTypeHandler::FALLBACK] = 'application/json';

/** *********************************************************************
* Content-Type options for specific handler
* Here as example only (set defaults), comment or remove them !!
*
* A serializing handler unSerialize-method MAY utilize
* ContentTypeHandler::UNSERIALIZEOPTIONS
* A serializing handler serialize-method MAY utilize
* ContentTypeHandler::SERIALIZEOPTIONS
********************************************************************* */
/**
* json_decode options (i.e. JsonHandler)
*
* value type : int
*
* default value : JSON_OBJECT_AS_ARRAY
* Make sure the value is supported
*/
$cteCfg['application/json'][ContentTypeHandler::UNSERIALIZEOPTIONS] = JSON_OBJECT_AS_ARRAY;
/**
* json_encode options (i.e. JsonHandler)
*
* value type : int
*
* default value : none, set here if other !!
* Make sure the value is supported
*/
$cteCfg['application/json'][ContentTypeHandler::SERIALIZEOPTIONS] = null;

/**
* XML unserialize Libxml parameters (i.e. XMLHandler)
*
* value type : int
*
* default value : none, set here if other !!
* Make sure the value is supported
*/
$cteCfg['application/xml'][ContentTypeHandler::UNSERIALIZEOPTIONS] = null;

/**
* NO XML serialize option
*/

/** *********************************************************************
* Encoding config
*
* For more info, see docs/TemplateHandler.php
*/
/**
* If request 'Accept-Encoding' header is missing (response message encoding),
*   you can alter the fallback
* default value : 'gzip', set here if other !!
* Make sure the value is supported
*
* value type : string
*
* If you want NO response encoding as fallback, set (bool) false
*/
$cteCfg[EncodingHandler::ACCEPTENCODING][EncodingHandler::FALLBACK] = 'gzip';

/** *********************************************************************
* Encoding options for specific handler
* Here as example only (set defaults), comment or remove them !!
*
* A encoding handler deCode-method MAY utilize
* EncodingHandler::DECODELEVEL
* EncodingHandler::DECODEOPTIONS
* A encoding handler enCode-method MAY utilize
* EncodingHandler::ENCODELEVEL
* EncodingHandler::ENCODEOPTIONS
********************************************************************* */
/**
* Gzip encoding level
*
* value type : int
*
* default value : -1 (none)
* Make sure the value is supported
*/
$cteCfg['gzip'][EncodingHandler::ENCODELEVEL] = -1;

/**
* Gzip encoding_mode
*
* value type : int
*
* fallback value : FORCE_GZIP,
* Make sure the value is supported
*/
$cteCfg['gzip'][EncodingHandler::ENCODEOPTIONS] = FORCE_GZIP;

/**
* Any 'gzip'  decoding parameters are ignored
*/


return $cteCfg;
