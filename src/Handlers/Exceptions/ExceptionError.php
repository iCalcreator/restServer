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

namespace Kigkonsult\RestServer\Handlers\Exceptions;

use Exception as master;

/**
 * Class ExceptionError
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 * @link https://php.net/manual/en/class.errorexception.php#89132
 */
class ExceptionError extends master
{
    /**
     * @var int
     */
    protected $severity;

    /**
     * @var array
     */
    private static $errorTexts = [
        E_ERROR             => 'ErrorException',
        E_WARNING           => 'Warning',
        E_PARSE             => 'Parse',
        E_NOTICE            => 'Notice',
        E_CORE_ERROR        => 'CoreError',
        E_CORE_WARNING      => 'CoreWarning',
        E_COMPILE_ERROR     => 'CompileError',
        E_COMPILE_WARNING   => 'CoreWarning',
        E_USER_ERROR        => 'UserError',
        E_USER_WARNING      => 'UserWarning',
        E_USER_NOTICE       => 'UserNotice',
        E_STRICT            => 'Strict',
        E_RECOVERABLE_ERROR => 'RecoverableError',
        E_DEPRECATED        => 'Deprecated',
        E_USER_DEPRECATED   => 'UserDeprecated',
    ];
    /**
     * Class constructor
     *
     * @param string $message
     * @param int    $code
     * @param int    $severity
     * @param string $filename
     * @param int    $lineno
     */
    public function __construct(
        $message,
        $code,
        $severity,
        $filename,
        $lineno
    ) {
        $this->message  = $message;
        $this->code     = $code;
        $this->severity = $severity;
        $this->file     = $filename;
        $this->line     = $lineno;
    }

    /**
     * Return severity
     *
     * @return int
     */
    public function getSeverity() {
        return $this->severity;
    }

    /**
     * Return severity text
     *
     * @param int $errorNo
     * @return string
     * @static
     */
    public static function getSeverityText(
        $errorNo
    ) {
        static $unknown = 'Unknown error';
        return ( isset( self::$errorTexts[$errorNo] )) ? self::$errorTexts[$errorNo] : $unknown;
    }
}