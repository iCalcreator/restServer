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

namespace Kigkonsult\RestServer\Handlers\ContentTypeHandlers;

use SimpleXMLElement;
use Kigkonsult\RestServer\Handlers\Exceptions\SimplexmlLoadErrorException;
use Kigkonsult\RestServer\Handlers\Exceptions\LIBXMLFatalErrorException;
use Kigkonsult\RestServer\Handlers\Exceptions\JsonErrorException;
use RuntimeException;
use Exception;

/**
 * Class XMLHandler manages XML unserialization/serialization
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */
class XMLHandler implements ContentTypeInterface
{
    /**
     * class constants & statics
     */
    private static $EXPR = '/&(?!;{6})/';

    private static $REPL = '-|-|-amp;-|-|-';

    private static $AMP  = '&';

    /**
     * Return XML as array
     *
     * @param string $xmlString
     * @param int    $libxmlParameters
     * @return array
     * @static
     * @throws LIBXMLFatalErrorException
     * @throws simplexmlLoadErrorException
     * @throws jsonErrorException
     * @throws RuntimeException
     */
    public static function unSerialize(
        $xmlString,
        $libxmlParameters = null
    ) {
        static $SIMPLEXMLELEMENT = 'SimpleXMLElement';
        static $ERRFMT           = '%s unSerialize error: %s';
        $xml                     = \preg_replace( self::$EXPR, self::$REPL, $xmlString );
        $saved1                  = \libxml_disable_entity_loader( true );
        $saved2                  = \libxml_use_internal_errors( true );  // enable user error handling
        $SimpleXMLElement        = @\simplexml_load_string( $xml, $SIMPLEXMLELEMENT, $libxmlParameters );
        $libXMLErrs              = \libxml_get_errors();
        \libxml_disable_entity_loader( $saved1 );
        \libxml_use_internal_errors( $saved2 );                   // disable user error handling
        \libxml_clear_errors();
        try {
            self::checkLIBXMLFatalError( $libXMLErrs, $xml );
        }
        catch( LIBXMLFatalErrorException $e ) {
            throw $e;
        }
        if ( false === $SimpleXMLElement ) {
            throw new SimplexmlLoadErrorException( $xmlString );
        }
        $root = $SimpleXMLElement->getName();
        try {
            $json  = JsonHandler::serialize( $SimpleXMLElement );
            $array = JsonHandler::unSerialize( $json, JSON_OBJECT_AS_ARRAY  );
        } catch ( JsonErrorException $e ) {
            throw $e;
        } catch ( Exception $e ) {
            throw new RuntimeException(
                \sprintf(
                    $ERRFMT,
                    \get_class( $e ),
                    $e->getMessage()
                ),
                500,
                $e
            );
        }
        $array2 = \unserialize( \serialize( self::deCodeAmpChars( $array )));
        return ( isset( $array2[$root] )) ? $array2 : [$root => $array2];
    }

    /**
     * Return xml-string as is, assoc array as XML
     *
     * @param string|string[] $data
     * @param mixed           $options
     * @return string
     * @throws RuntimeException
     * @throws SimplexmlLoadErrorException
     * @throws LIBXMLFatalErrorException
     * @static
     */
    public static function serialize(
        $data,
        $options = null
    ) {
        static $FMT1 = '<?xml version="1.0"?>';
        static $TXT2 = 'xml data is no xml';
        static $TXT3 = 'xml data is no array';
/*      static $FMT4 = '<?xml version="1.0"?><%1$s></%1$s>'; */
        static $FMT4 = '<?xml version="1.0" encoding="UTF-8"?><%1$s></%1$s>';
        if ( \is_string( $data )) {
            if ( \substr( $data, 0, 5 ) == \substr( $FMT1, 0, 5 )) {
                return $data;
            } // is xml-string
            throw new RuntimeException( $TXT2, 500 );
        } elseif ( ! \is_array( $data )) {
            throw new RuntimeException( $TXT3, 500 );
        }
        $array2     = self::enCodeAmpChars( $data );
        if ( empty( $root )) {
            $root   = \key( \array_slice( $array2, 0, 1, true ));
        }
        $saved1     = \libxml_disable_entity_loader( true );
        $saved2     = \libxml_use_internal_errors( true );  // enable user error handling
        $xml        = new SimpleXMLElement( \sprintf( $FMT4, $root ));
        $libXMLErrs = \libxml_get_errors();
        \libxml_disable_entity_loader( $saved1 );
        \libxml_use_internal_errors( $saved2 );              // disable user error handling
        \libxml_clear_errors();
        try {
            self::checkLIBXMLFatalError( $libXMLErrs, $xml );
        }
        catch( LIBXMLFatalErrorException $e ) {
            throw $e;
        }
        if ( false === $xml ) {
            throw new SimplexmlLoadErrorException( \var_export( $data, true ));
        }
        self::array2xml( $array2, $xml, $root );
        return \str_replace( self::$REPL, self::$AMP, $xml->asXML());
    }

    /*
     * Throw Exception on LIBXML Fatal Error only
     *
     * @param array  $errors     array of libxml error object
     * @param string $xmlString  xml content
     * @return bool true on NO error found
     * @see http://php.net/manual/en/function.libxml-get-errors.php
     * @throw LIBXMLFatalErrorException
     * @access private
     * @static
     */
    private static function checkLIBXMLFatalError(
        $errors,
        $xmlString
    ) {
        static $FMT1                   = '#%d errCode %s : %s';
        static $FMT2                   = ' line: %d col: %d';
        static $FMT3                   = '%s%s%s%s^%s';
        static $D                      = '-';
//      static $LIBXMLwarning          = 'LIBXML Warning';
//      static $LIBXMLrecoverableError = 'LIBXML (recoverable) Error';
        static $LIBXMLfatalError       = 'LIBXML Fatal Error';
        $result                        = [];
        if ( empty( $errors )) {
            return true;
        }
        //  $errors          = array_reverse( $errors );
        $str3 = null;
        foreach ( $errors as $ex => $error ) {
            switch ( $error->level ) {
                case LIBXML_ERR_WARNING:    // 1
                    continue 2;
//                  $str3      = $LIBXMLWarning;
//                  break;
                case LIBXML_ERR_ERROR:      // 2
                    continue 2;
//                  $str3      = $LIBXMLrecoverableError;
//                  break;
                case LIBXML_ERR_FATAL:      // 3
                    $str3 = $LIBXMLfatalError;
                    break;
            } // end switch
            $str1 = \sprintf(
                $FMT1,
                ( $ex + 1 ),
                $error->code,
                \trim( $error->message )
            );
            $str2   = \sprintf( $FMT2, $error->line, $error->column );
            $lineNo = ( 0 < $error->line ) ? ( $error->line - 1 ) : 0;
            $str2 .= \sprintf(
                $FMT3,
                PHP_EOL,
                $xmlString[$lineNo],
                PHP_EOL,
                \str_repeat( $D, $error->column ),
                PHP_EOL
            );
            $result[] = $str3 . $str1;
            $result[] = $str3 . $str2;
        }  // end foreach
        if ( ! empty( $result )) {
            throw new LIBXMLFatalErrorException( \implode( PHP_EOL, $result ));
        }
        return true;
    }

    /**
     * Return array where amp-chars are ok
     *
     * @param array $array
     * @return array
     * @access private
     * @static
     */
    private static function deCodeAmpChars(
        $array
    ) {
        $output = [];
        foreach ( $array as $k => $v ) {
            if ( self::hasDeCodedAmpValue( $k )) {
                $k = \str_replace( self::$REPL, self::$AMP, $k );
            }
            if ( \is_array( $v )) {
                $output[$k] = self::deCodeAmpChars( $v );
            } else {
                if ( self::hasDeCodedAmpValue( $v )) {
                    $v = \str_replace( self::$REPL, self::$AMP, $v );
                }
                $output[$k] = $v;
            }
        } // end foreach
        return $output;
    }

    /**
     * Return bool true if value has decoded amp-value
     *
     * @param mixed $value
     * @return bool
     * @access private
     * @static
     */
    private static function hasDeCodedAmpValue(
        $value
    ) {
        return ( ! \is_numeric( $value ) && ( false !== \strpos( $value, self::$REPL )));
    }

    /**
     * Return array where amp-chars are encoded
     *
     * @param array $arr
     * @return array
     * @access private
     * @static
     */
    private static function enCodeAmpChars(
        array $arr
    ) {
        $output = [];
        foreach ( $arr as $k => $v ) {
            if ( self::hasAmpValue( $k )) {
                $k = \str_replace( self::$AMP, self::$REPL, $k );
            }
            if ( \is_array( $v )) {
                $output[$k] = self::enCodeAmpChars( $v );
            } else {
                if ( self::hasAmpValue( $v )) {
                    $v = \str_replace( self::$AMP, self::$REPL, $v );
                }
                $output[$k] = $v;
            }
        } // end foreach
        return $output;
    }

    /**
     * Return bool true if value contains &
     *
     * @param mixed $value
     * @return bool
     * @access private
     * @static
     */
    private static function hasAmpValue(
        $value
    ) {
        return ( ! \is_numeric( $value ) && ( false !== \strpos( $value, self::$AMP )));
    }

    /**
     * Return array converted to XML using SimpleXMLElement
     *
     * @param array            $array
     * @param SimpleXMLElement $xml
     * @param string           $root
     * @access private
     * @static
     */
    private static function array2xml(
        array              $array,
        SimpleXMLElement & $xml,
                           $root = null
    ) {
        $first = true;
        foreach ( $array as $key => $value ) {
            if ( $first && ( $root == $key )) {
                if ( \is_array( $value )) {
                    self::array2xml( $value, $xml );
                } else {
                    $xml[0] = $value;
                }
                $root = null;
                continue;
            } // end if
            $first = false;
            switch ( true ) {
                case ( ! \is_array( $value )):
                    $xml->addChild((string) $key, (string) $value );
                    break;
                case ( \is_numeric( $key )):
                    self::array2xml( $value, $xml );
                    break;
                case ( \is_numeric( \key( \array_slice( $value, 0, 1, true )))):
                    foreach ( $value as $value2 ) {
                        if ( \is_array( $value2 )) {
                            $subnode = $xml->addChild((string) $key );
                            self::array2xml( $value2, $subnode );
                        } else {
                            $xml->addChild((string) $key, (string) $value2 );
                        }
                    } // end foreach
                    break;
                default:
                    $subnode = $xml->addChild((string) $key );
                    self::array2xml( $value, $subnode );
                    break;
            } // end switch
        } // end foreach
    }
}
