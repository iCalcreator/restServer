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

namespace Kigkonsult\RestServer\Handlers\ContentTypeHandlers;

use Kigkonsult\RestServer\Handlers\Exceptions\JsonErrorException;

/**
 * Class XMLHandler manager json unserialization/serialization
 */
class JsonHandler implements ContentTypeInterface
{
    /**
     * class constants & statics
     * produces RFC4627-compliant JSON, capable of embedding into HTML.
     */

    /**
     * Return json as array
     *
     * @param string $jsonString
     * @param int    $decodeOptions
     * @return string|array
     * @throws JsonErrorException on json decode error
     * @static
     */
    public static function unserialize(
        $jsonString,
        $decodeOptions = null
    ) {
        \json_decode( null );
        if (( null === $decodeOptions ) ||
           ( $decodeOptions == JSON_OBJECT_AS_ARRAY )) {
            $output = \json_decode( $jsonString, true );
        } else {
            $output = \json_decode( $jsonString, false, 512, $decodeOptions );
        }
        if ( JSON_ERROR_NONE !== \json_last_error()) {
            throw new JsonErrorException( \json_last_error_msg());
        }
        return $output;
    }

    /**
     * Return array (or SimpleXMLElement) instance as json string
     *
     * @param string|array|object $data
     * @param int                 $encodeOptions
     * @return string
     * @static
     * @throws JsonErrorException on json encode error
     */
    public static function serialize(
        $data,
        $encodeOptions = null
    ) {
        \json_encode( null );
        if ( null === $encodeOptions ) {
            $jsonString = \json_encode( $data );
        } else {
            $jsonString = \json_encode( $data, $encodeOptions );
        }
        if ( JSON_ERROR_NONE !== \json_last_error()) {
            throw new JsonErrorException( \json_last_error_msg());
        }
        return $jsonString;
    }
}
