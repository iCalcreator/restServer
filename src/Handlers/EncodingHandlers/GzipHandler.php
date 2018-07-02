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

namespace Kigkonsult\RestServer\Handlers\EncodingHandlers;

use Kigkonsult\RestServer\Handlers\Exceptions\ZlibErrorException;

/**
 * GzipHandler manages 'gzip' decode/encode operations
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */
class GzipHandler implements EncodingInterface
{
    /**
     * Uncompress gzipped data
     *
     * @param string $data
     * @param int   $level
     * @param int   $options
     * @return string
     * @static
     * @throws ZlibErrorException
     */
    public static function deCode(
        $data,
        $level = null,
        $options = null
    ) {
        $uncompressed = @\gzdecode( $data );
        if ( false !== $uncompressed ) {
            return $uncompressed;
        }
        throw new ZlibErrorException( __METHOD__, 500 );
    }

    /**
     * Compress data as gzip
     *
     * @param mixed $data
     * @param int   $level
     * @param int   $options
     * @return string
     * @static
     * @throws ZlibErrorException
     */
    public static function enCode(
        $data,
        $level = null,
        $options = null
    ) {
        if ( empty( $level )) {
            $level = -1;
        }
        if ( empty( $options )) {
            $options = FORCE_GZIP;
        }
        $compressed = @\gzencode( $data, $level, $options );
        if ( false !== $compressed ) {
            return $compressed;
        }
        throw new ZlibErrorException( __METHOD__, 500 );
    }
}
