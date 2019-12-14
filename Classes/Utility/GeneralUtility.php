<?php
declare(strict_types = 1);
namespace StefanFroemken\AccountAnalyzer\Utility;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use StefanFroemken\AccountAnalyzer\Service\OpcodeCacheService;

/**
 * The legendary "t3lib_div" class - Miscellaneous functions for general purpose.
 * Most of the functions do not relate specifically to TYPO3
 * However a section of functions requires certain TYPO3 features available
 * See comments in the source.
 * You are encouraged to use this library in your own scripts!
 *
 * USE:
 * The class is intended to be used without creating an instance of it.
 * So: Don't instantiate - call functions with "\TYPO3\CMS\Core\Utility\GeneralUtility::" prefixed the function name.
 * So use \TYPO3\CMS\Core\Utility\GeneralUtility::[method-name] to refer to the functions, eg. '\TYPO3\CMS\Core\Utility\GeneralUtility::milliseconds()'
 */
class GeneralUtility
{
    /**
     * A list of supported CGI server APIs
     * NOTICE: This is a duplicate of the SAME array in SystemEnvironmentBuilder
     * @var array
     */
    protected static $supportedCgiServerApis = [
        'fpm-fcgi',
        'cgi',
        'isapi',
        'cgi-fcgi',
        'srv', // HHVM with fastcgi
    ];

    /**
     * @var array
     */
    protected static $indpEnvCache = [];

    /*************************
     *
     * GET/POST Variables
     *
     * Background:
     * Input GET/POST variables in PHP may have their quotes escaped with "\" or not depending on configuration.
     * TYPO3 has always converted quotes to BE escaped if the configuration told that they would not be so.
     * But the clean solution is that quotes are never escaped and that is what the functions below offers.
     * Eventually TYPO3 should provide this in the global space as well.
     * In the transitional phase (or forever..?) we need to encourage EVERY to read and write GET/POST vars through the API functions below.
     * This functionality was previously needed to normalize between magic quotes logic, which was removed from PHP 5.4,
     * so these methods are still in use, but not tackle the slash problem anymore.
     *
     *************************/
    /**
     * Returns the 'GLOBAL' value of incoming data from POST or GET, with priority to POST (that is equalent to 'GP' order)
     * To enhance security in your scripts, please consider using GeneralUtility::_GET or GeneralUtility::_POST if you already
     * know by which method your data is arriving to the scripts!
     *
     * @param string $var GET/POST var to return
     * @return mixed POST var named $var and if not set, the GET var of the same name.
     */
    public static function _GP($var)
    {
        if (empty($var)) {
            return;
        }
        if (isset($_POST[$var])) {
            $value = $_POST[$var];
        } elseif (isset($_GET[$var])) {
            $value = $_GET[$var];
        } else {
            $value = null;
        }
        // This is there for backwards-compatibility, in order to avoid NULL
        if (isset($value) && !is_array($value)) {
            $value = (string)$value;
        }
        return $value;
    }

    /**
     * Returns the global arrays $_GET and $_POST merged with $_POST taking precedence.
     *
     * @param string $parameter Key (variable name) from GET or POST vars
     * @return array Returns the GET vars merged recursively onto the POST vars.
     */
    public static function _GPmerged($parameter)
    {
        $postParameter = isset($_POST[$parameter]) && is_array($_POST[$parameter]) ? $_POST[$parameter] : [];
        $getParameter = isset($_GET[$parameter]) && is_array($_GET[$parameter]) ? $_GET[$parameter] : [];
        $mergedParameters = $getParameter;
        ArrayUtility::mergeRecursiveWithOverrule($mergedParameters, $postParameter);
        return $mergedParameters;
    }

    /**
     * Returns the global $_GET array (or value from) normalized to contain un-escaped values.
     * ALWAYS use this API function to acquire the GET variables!
     * This function was previously used to normalize between magic quotes logic, which was removed from PHP 5.5
     *
     * @param string $var Optional pointer to value in GET array (basically name of GET var)
     * @return mixed If $var is set it returns the value of $_GET[$var]. If $var is NULL (default), returns $_GET itself. In any case *slashes are stipped from the output!*
     * @see _POST(), _GP(), _GETset()
     */
    public static function _GET($var = null)
    {
        $value = $var === null ? $_GET : (empty($var) ? null : $_GET[$var]);
        // This is there for backwards-compatibility, in order to avoid NULL
        if (isset($value) && !is_array($value)) {
            $value = (string)$value;
        }
        return $value;
    }

    /**
     * Returns the global $_POST array (or value from) normalized to contain un-escaped values.
     * ALWAYS use this API function to acquire the $_POST variables!
     *
     * @param string $var Optional pointer to value in POST array (basically name of POST var)
     * @return mixed If $var is set it returns the value of $_POST[$var]. If $var is NULL (default), returns $_POST itself. In any case *slashes are stipped from the output!*
     * @see _GET(), _GP()
     */
    public static function _POST($var = null)
    {
        $value = $var === null ? $_POST : (empty($var) ? null : $_POST[$var]);
        // This is there for backwards-compatibility, in order to avoid NULL
        if (isset($value) && !is_array($value)) {
            $value = (string)$value;
        }
        return $value;
    }

    /**
     * Writes input value to $_GET.
     *
     * @param mixed $inputGet
     * @param string $key
     */
    public static function _GETset($inputGet, $key = '')
    {
        if ($key != '') {
            if (strpos($key, '|') !== false) {
                $pieces = explode('|', $key);
                $newGet = [];
                $pointer = &$newGet;
                foreach ($pieces as $piece) {
                    $pointer = &$pointer[$piece];
                }
                $pointer = $inputGet;
                $mergedGet = $_GET;
                ArrayUtility::mergeRecursiveWithOverrule($mergedGet, $newGet);
                $_GET = $mergedGet;
                $GLOBALS['HTTP_GET_VARS'] = $mergedGet;
            } else {
                $_GET[$key] = $inputGet;
                $GLOBALS['HTTP_GET_VARS'][$key] = $inputGet;
            }
        } elseif (is_array($inputGet)) {
            $_GET = $inputGet;
            $GLOBALS['HTTP_GET_VARS'] = $inputGet;
        }
    }

    /*************************
     *
     * STRING FUNCTIONS
     *
     *************************/
    /**
     * Match IP number with list of numbers with wildcard
     * Dispatcher method for switching into specialised IPv4 and IPv6 methods.
     *
     * @param string $baseIP Is the current remote IP address for instance, typ. REMOTE_ADDR
     * @param string $list Is a comma-list of IP-addresses to match with. *-wildcard allowed instead of number, plus leaving out parts in the IP number is accepted as wildcard (eg. 192.168.*.* equals 192.168). If list is "*" no check is done and the function returns TRUE immediately. An empty list always returns FALSE.
     * @return bool TRUE if an IP-mask from $list matches $baseIP
     */
    public static function cmpIP($baseIP, $list)
    {
        $list = trim($list);
        if ($list === '') {
            return false;
        }
        if ($list === '*') {
            return true;
        }
        if (strpos($baseIP, ':') !== false && self::validIPv6($baseIP)) {
            return self::cmpIPv6($baseIP, $list);
        }
        return self::cmpIPv4($baseIP, $list);
    }

    /**
     * Match IPv4 number with list of numbers with wildcard
     *
     * @param string $baseIP Is the current remote IP address for instance, typ. REMOTE_ADDR
     * @param string $list Is a comma-list of IP-addresses to match with. *-wildcard allowed instead of number, plus leaving out parts in the IP number is accepted as wildcard (eg. 192.168.*.* equals 192.168), could also contain IPv6 addresses
     * @return bool TRUE if an IP-mask from $list matches $baseIP
     */
    public static function cmpIPv4($baseIP, $list)
    {
        $IPpartsReq = explode('.', $baseIP);
        if (count($IPpartsReq) === 4) {
            $values = self::trimExplode(',', $list, true);
            foreach ($values as $test) {
                $testList = explode('/', $test);
                if (count($testList) === 2) {
                    list($test, $mask) = $testList;
                } else {
                    $mask = false;
                }
                if ((int)$mask) {
                    // "192.168.3.0/24"
                    $lnet = ip2long($test);
                    $lip = ip2long($baseIP);
                    $binnet = str_pad(decbin($lnet), 32, '0', STR_PAD_LEFT);
                    $firstpart = substr($binnet, 0, $mask);
                    $binip = str_pad(decbin($lip), 32, '0', STR_PAD_LEFT);
                    $firstip = substr($binip, 0, $mask);
                    $yes = $firstpart === $firstip;
                } else {
                    // "192.168.*.*"
                    $IPparts = explode('.', $test);
                    $yes = 1;
                    foreach ($IPparts as $index => $val) {
                        $val = trim($val);
                        if ($val !== '*' && $IPpartsReq[$index] !== $val) {
                            $yes = 0;
                        }
                    }
                }
                if ($yes) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Match IPv6 address with a list of IPv6 prefixes
     *
     * @param string $baseIP Is the current remote IP address for instance
     * @param string $list Is a comma-list of IPv6 prefixes, could also contain IPv4 addresses
     * @return bool TRUE If an baseIP matches any prefix
     */
    public static function cmpIPv6($baseIP, $list)
    {
        // Policy default: Deny connection
        $success = false;
        $baseIP = self::normalizeIPv6($baseIP);
        $values = self::trimExplode(',', $list, true);
        foreach ($values as $test) {
            $testList = explode('/', $test);
            if (count($testList) === 2) {
                list($test, $mask) = $testList;
            } else {
                $mask = false;
            }
            if (self::validIPv6($test)) {
                $test = self::normalizeIPv6($test);
                $maskInt = (int)$mask ?: 128;
                // Special case; /0 is an allowed mask - equals a wildcard
                if ($mask === '0') {
                    $success = true;
                } elseif ($maskInt == 128) {
                    $success = $test === $baseIP;
                } else {
                    $testBin = self::IPv6Hex2Bin($test);
                    $baseIPBin = self::IPv6Hex2Bin($baseIP);
                    $success = true;
                    // Modulo is 0 if this is a 8-bit-boundary
                    $maskIntModulo = $maskInt % 8;
                    $numFullCharactersUntilBoundary = (int)($maskInt / 8);
                    if (substr($testBin, 0, $numFullCharactersUntilBoundary) !== substr($baseIPBin, 0, $numFullCharactersUntilBoundary)) {
                        $success = false;
                    } elseif ($maskIntModulo > 0) {
                        // If not an 8-bit-boundary, check bits of last character
                        $testLastBits = str_pad(decbin(ord(substr($testBin, $numFullCharactersUntilBoundary, 1))), 8, '0', STR_PAD_LEFT);
                        $baseIPLastBits = str_pad(decbin(ord(substr($baseIPBin, $numFullCharactersUntilBoundary, 1))), 8, '0', STR_PAD_LEFT);
                        if (strncmp($testLastBits, $baseIPLastBits, $maskIntModulo) != 0) {
                            $success = false;
                        }
                    }
                }
            }
            if ($success) {
                return true;
            }
        }
        return false;
    }

    /**
     * Transform a regular IPv6 address from hex-representation into binary
     *
     * @param string $hex IPv6 address in hex-presentation
     * @return string Binary representation (16 characters, 128 characters)
     * @see IPv6Bin2Hex()
     */
    public static function IPv6Hex2Bin($hex)
    {
        return inet_pton($hex);
    }

    /**
     * Transform an IPv6 address from binary to hex-representation
     *
     * @param string $bin IPv6 address in hex-presentation
     * @return string Binary representation (16 characters, 128 characters)
     * @see IPv6Hex2Bin()
     */
    public static function IPv6Bin2Hex($bin)
    {
        return inet_ntop($bin);
    }

    /**
     * Normalize an IPv6 address to full length
     *
     * @param string $address Given IPv6 address
     * @return string Normalized address
     * @see compressIPv6()
     */
    public static function normalizeIPv6($address)
    {
        $normalizedAddress = '';
        $stageOneAddress = '';
        // According to RFC lowercase-representation is recommended
        $address = strtolower($address);
        // Normalized representation has 39 characters (0000:0000:0000:0000:0000:0000:0000:0000)
        if (strlen($address) === 39) {
            // Already in full expanded form
            return $address;
        }
        // Count 2 if if address has hidden zero blocks
        $chunks = explode('::', $address);
        if (count($chunks) === 2) {
            $chunksLeft = explode(':', $chunks[0]);
            $chunksRight = explode(':', $chunks[1]);
            $left = count($chunksLeft);
            $right = count($chunksRight);
            // Special case: leading zero-only blocks count to 1, should be 0
            if ($left === 1 && strlen($chunksLeft[0]) === 0) {
                $left = 0;
            }
            $hiddenBlocks = 8 - ($left + $right);
            $hiddenPart = '';
            $h = 0;
            while ($h < $hiddenBlocks) {
                $hiddenPart .= '0000:';
                $h++;
            }
            if ($left === 0) {
                $stageOneAddress = $hiddenPart . $chunks[1];
            } else {
                $stageOneAddress = $chunks[0] . ':' . $hiddenPart . $chunks[1];
            }
        } else {
            $stageOneAddress = $address;
        }
        // Normalize the blocks:
        $blocks = explode(':', $stageOneAddress);
        $divCounter = 0;
        foreach ($blocks as $block) {
            $tmpBlock = '';
            $i = 0;
            $hiddenZeros = 4 - strlen($block);
            while ($i < $hiddenZeros) {
                $tmpBlock .= '0';
                $i++;
            }
            $normalizedAddress .= $tmpBlock . $block;
            if ($divCounter < 7) {
                $normalizedAddress .= ':';
                $divCounter++;
            }
        }
        return $normalizedAddress;
    }

    /**
     * Compress an IPv6 address to the shortest notation
     *
     * @param string $address Given IPv6 address
     * @return string Compressed address
     * @see normalizeIPv6()
     */
    public static function compressIPv6($address)
    {
        return inet_ntop(inet_pton($address));
    }

    /**
     * Validate a given IP address.
     *
     * Possible format are IPv4 and IPv6.
     *
     * @param string $ip IP address to be tested
     * @return bool TRUE if $ip is either of IPv4 or IPv6 format.
     */
    public static function validIP($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Validate a given IP address to the IPv4 address format.
     *
     * Example for possible format: 10.0.45.99
     *
     * @param string $ip IP address to be tested
     * @return bool TRUE if $ip is of IPv4 format.
     */
    public static function validIPv4($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * Validate a given IP address to the IPv6 address format.
     *
     * Example for possible format: 43FB::BB3F:A0A0:0 | ::1
     *
     * @param string $ip IP address to be tested
     * @return bool TRUE if $ip is of IPv6 format.
     */
    public static function validIPv6($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /**
     * Match fully qualified domain name with list of strings with wildcard
     *
     * @param string $baseHost A hostname or an IPv4/IPv6-address (will by reverse-resolved; typically REMOTE_ADDR)
     * @param string $list A comma-list of domain names to match with. *-wildcard allowed but cannot be part of a string, so it must match the full host name (eg. myhost.*.com => correct, myhost.*domain.com => wrong)
     * @return bool TRUE if a domain name mask from $list matches $baseIP
     */
    public static function cmpFQDN($baseHost, $list)
    {
        $baseHost = trim($baseHost);
        if (empty($baseHost)) {
            return false;
        }
        if (self::validIPv4($baseHost) || self::validIPv6($baseHost)) {
            // Resolve hostname
            // Note: this is reverse-lookup and can be randomly set as soon as somebody is able to set
            // the reverse-DNS for his IP (security when for example used with REMOTE_ADDR)
            $baseHostName = gethostbyaddr($baseHost);
            if ($baseHostName === $baseHost) {
                // Unable to resolve hostname
                return false;
            }
        } else {
            $baseHostName = $baseHost;
        }
        $baseHostNameParts = explode('.', $baseHostName);
        $values = self::trimExplode(',', $list, true);
        foreach ($values as $test) {
            $hostNameParts = explode('.', $test);
            // To match hostNameParts can only be shorter (in case of wildcards) or equal
            $hostNamePartsCount = count($hostNameParts);
            $baseHostNamePartsCount = count($baseHostNameParts);
            if ($hostNamePartsCount > $baseHostNamePartsCount) {
                continue;
            }
            $yes = true;
            foreach ($hostNameParts as $index => $val) {
                $val = trim($val);
                if ($val === '*') {
                    // Wildcard valid for one or more hostname-parts
                    $wildcardStart = $index + 1;
                    // Wildcard as last/only part always matches, otherwise perform recursive checks
                    if ($wildcardStart < $hostNamePartsCount) {
                        $wildcardMatched = false;
                        $tempHostName = implode('.', array_slice($hostNameParts, $index + 1));
                        while ($wildcardStart < $baseHostNamePartsCount && !$wildcardMatched) {
                            $tempBaseHostName = implode('.', array_slice($baseHostNameParts, $wildcardStart));
                            $wildcardMatched = self::cmpFQDN($tempBaseHostName, $tempHostName);
                            $wildcardStart++;
                        }
                        if ($wildcardMatched) {
                            // Match found by recursive compare
                            return true;
                        }
                        $yes = false;
                    }
                } elseif ($baseHostNameParts[$index] !== $val) {
                    // In case of no match
                    $yes = false;
                }
            }
            if ($yes) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if a given URL matches the host that currently handles this HTTP request.
     * Scheme, hostname and (optional) port of the given URL are compared.
     *
     * @param string $url URL to compare with the TYPO3 request host
     * @return bool Whether the URL matches the TYPO3 request host
     */
    public static function isOnCurrentHost($url)
    {
        return stripos($url . '/', self::getIndpEnv('TYPO3_REQUEST_HOST') . '/') === 0;
    }

    /**
     * Check for item in list
     * Check if an item exists in a comma-separated list of items.
     *
     * @param string $list Comma-separated list of items (string)
     * @param string $item Item to check for
     * @return bool TRUE if $item is in $list
     */
    public static function inList($list, $item)
    {
        return strpos(',' . $list . ',', ',' . $item . ',') !== false;
    }

    /**
     * Removes an item from a comma-separated list of items.
     *
     * If $element contains a comma, the behaviour of this method is undefined.
     * Empty elements in the list are preserved.
     *
     * @param string $element Element to remove
     * @param string $list Comma-separated list of items (string)
     * @return string New comma-separated list of items
     */
    public static function rmFromList($element, $list)
    {
        $items = explode(',', $list);
        foreach ($items as $k => $v) {
            if ($v == $element) {
                unset($items[$k]);
            }
        }
        return implode(',', $items);
    }

    /**
     * Expand a comma-separated list of integers with ranges (eg 1,3-5,7 becomes 1,3,4,5,7).
     * Ranges are limited to 1000 values per range.
     *
     * @param string $list Comma-separated list of integers with ranges (string)
     * @return string New comma-separated list of items
     */
    public static function expandList($list)
    {
        $items = explode(',', $list);
        $list = [];
        foreach ($items as $item) {
            $range = explode('-', $item);
            if (isset($range[1])) {
                $runAwayBrake = 1000;
                for ($n = $range[0]; $n <= $range[1]; $n++) {
                    $list[] = $n;
                    $runAwayBrake--;
                    if ($runAwayBrake <= 0) {
                        break;
                    }
                }
            } else {
                $list[] = $item;
            }
        }
        return implode(',', $list);
    }

    /**
     * Makes a positive integer hash out of the first 7 chars from the md5 hash of the input
     *
     * @param string $str String to md5-hash
     * @return int Returns 28bit integer-hash
     */
    public static function md5int($str)
    {
        return hexdec(substr(md5($str), 0, 7));
    }

    /**
     * Returns the first 10 positions of the MD5-hash		(changed from 6 to 10 recently)
     *
     * @param string $input Input string to be md5-hashed
     * @param int $len The string-length of the output
     * @return string Substring of the resulting md5-hash, being $len chars long (from beginning)
     */
    public static function shortMD5($input, $len = 10)
    {
        return substr(md5($input), 0, $len);
    }

    /**
     * Takes comma-separated lists and arrays and removes all duplicates
     * If a value in the list is trim(empty), the value is ignored.
     *
     * @param string $in_list Accept multiple parameters which can be comma-separated lists of values and arrays.
     * @param mixed $secondParameter Dummy field, which if set will show a warning!
     * @return string Returns the list without any duplicates of values, space around values are trimmed
     */
    public static function uniqueList($in_list, $secondParameter = null)
    {
        if (is_array($in_list)) {
            throw new \InvalidArgumentException('TYPO3 Fatal Error: TYPO3\\CMS\\Core\\Utility\\GeneralUtility::uniqueList() does NOT support array arguments anymore! Only string comma lists!', 1270853885);
        }
        if (isset($secondParameter)) {
            throw new \InvalidArgumentException('TYPO3 Fatal Error: TYPO3\\CMS\\Core\\Utility\\GeneralUtility::uniqueList() does NOT support more than a single argument value anymore. You have specified more than one!', 1270853886);
        }
        return implode(',', array_unique(self::trimExplode(',', $in_list, true)));
    }

    /**
     * Splits a reference to a file in 5 parts
     *
     * @param string $fileNameWithPath File name with path to be analysed (must exist if open_basedir is set)
     * @return array Contains keys [path], [file], [filebody], [fileext], [realFileext]
     */
    public static function split_fileref($fileNameWithPath)
    {
        $reg = [];
        if (preg_match('/(.*\\/)(.*)$/', $fileNameWithPath, $reg)) {
            $info['path'] = $reg[1];
            $info['file'] = $reg[2];
        } else {
            $info['path'] = '';
            $info['file'] = $fileNameWithPath;
        }
        $reg = '';
        // If open_basedir is set and the fileName was supplied without a path the is_dir check fails
        if (!is_dir($fileNameWithPath) && preg_match('/(.*)\\.([^\\.]*$)/', $info['file'], $reg)) {
            $info['filebody'] = $reg[1];
            $info['fileext'] = strtolower($reg[2]);
            $info['realFileext'] = $reg[2];
        } else {
            $info['filebody'] = $info['file'];
            $info['fileext'] = '';
        }
        reset($info);
        return $info;
    }

    /**
     * Returns the directory part of a path without trailing slash
     * If there is no dir-part, then an empty string is returned.
     * Behaviour:
     *
     * '/dir1/dir2/script.php' => '/dir1/dir2'
     * '/dir1/' => '/dir1'
     * 'dir1/script.php' => 'dir1'
     * 'd/script.php' => 'd'
     * '/script.php' => ''
     * '' => ''
     *
     * @param string $path Directory name / path
     * @return string Processed input value. See function description.
     */
    public static function dirname($path)
    {
        $p = self::revExplode('/', $path, 2);
        return count($p) === 2 ? $p[0] : '';
    }

    /**
     * Returns TRUE if the first part of $str matches the string $partStr
     *
     * @param string $str Full string to check
     * @param string $partStr Reference string which must be found as the "first part" of the full string
     * @return bool TRUE if $partStr was found to be equal to the first part of $str
     */
    public static function isFirstPartOfStr($str, $partStr)
    {
        return $partStr != '' && strpos((string)$str, (string)$partStr, 0) === 0;
    }

    /**
     * Formats the input integer $sizeInBytes as bytes/kilobytes/megabytes (-/K/M)
     *
     * @param int $sizeInBytes Number of bytes to format.
     * @param string $labels Binary unit name "iec", decimal unit name "si" or labels for bytes, kilo, mega, giga, and so on separated by vertical bar (|) and possibly encapsulated in "". Eg: " | K| M| G". Defaults to "iec".
     * @param int $base The unit base if not using a unit name. Defaults to 1024.
     * @return string Formatted representation of the byte number, for output.
     */
    public static function formatSize($sizeInBytes, $labels = '', $base = 0)
    {
        $defaultFormats = [
            'iec' => ['base' => 1024, 'labels' => [' ', ' Ki', ' Mi', ' Gi', ' Ti', ' Pi', ' Ei', ' Zi', ' Yi']],
            'si' => ['base' => 1000, 'labels' => [' ', ' k', ' M', ' G', ' T', ' P', ' E', ' Z', ' Y']],
        ];
        // Set labels and base:
        if (empty($labels)) {
            $labels = 'iec';
        }
        if (isset($defaultFormats[$labels])) {
            $base = $defaultFormats[$labels]['base'];
            $labelArr = $defaultFormats[$labels]['labels'];
        } else {
            $base = (int)$base;
            if ($base !== 1000 && $base !== 1024) {
                $base = 1024;
            }
            $labelArr = explode('|', str_replace('"', '', $labels));
        }
        // @todo find out which locale is used for current BE user to cover the BE case as well
        $oldLocale = setlocale(LC_NUMERIC, 0);
        $localeInfo = localeconv();
        $sizeInBytes = max($sizeInBytes, 0);
        $multiplier = floor(($sizeInBytes ? log($sizeInBytes) : 0) / log($base));
        $sizeInUnits = $sizeInBytes / pow($base, $multiplier);
        if ($sizeInUnits > ($base * .9)) {
            $multiplier++;
        }
        $multiplier = min($multiplier, count($labelArr) - 1);
        $sizeInUnits = $sizeInBytes / pow($base, $multiplier);
        return number_format($sizeInUnits, (($multiplier > 0) && ($sizeInUnits < 20)) ? 2 : 0, $localeInfo['decimal_point'], '') . $labelArr[$multiplier];
    }

    /**
     * This splits a string by the chars in $operators (typical /+-*) and returns an array with them in
     *
     * @param string $string Input string, eg "123 + 456 / 789 - 4
     * @param string $operators Operators to split by, typically "/+-*
     * @return array Array with operators and operands separated.
     */
    public static function splitCalc($string, $operators)
    {
        $res = [];
        $sign = '+';
        while ($string) {
            $valueLen = strcspn($string, $operators);
            $value = substr($string, 0, $valueLen);
            $res[] = [$sign, trim($value)];
            $sign = substr($string, $valueLen, 1);
            $string = substr($string, $valueLen + 1);
        }
        reset($res);
        return $res;
    }

    /**
     * Checking syntax of input email address
     *
     * http://tools.ietf.org/html/rfc3696
     * International characters are allowed in email. So the whole address needs
     * to be converted to punicode before passing it to filter_var(). We convert
     * the user- and domain part separately to increase the chance of hitting an
     * entry in self::$idnaStringCache.
     *
     * Also the @ sign may appear multiple times in an address. If not used as
     * a boundary marker between the user- and domain part, it must be escaped
     * with a backslash: \@. This mean we can not just explode on the @ sign and
     * expect to get just two parts. So we pop off the domain and then glue the
     * rest together again.
     *
     * @param string $email Input string to evaluate
     * @return bool Returns TRUE if the $email address (input string) is valid
     */
    public static function validEmail($email)
    {
        // Early return in case input is not a string
        if (!is_string($email)) {
            return false;
        }
        $atPosition = strrpos($email, '@');
        if (!$atPosition || $atPosition + 1 === strlen($email)) {
            // Return if no @ found or it is placed at the very beginning or end of the email
            return false;
        }
        $domain = substr($email, $atPosition + 1);
        $user = substr($email, 0, $atPosition);
        if (!preg_match('/^[a-z0-9.\\-]*$/i', $domain)) {
            try {
                $domain = self::idnaEncode($domain);
            } catch (\InvalidArgumentException $exception) {
                return false;
            }
        }
        return filter_var($user . '@' . $domain, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Returns a given string with underscores as UpperCamelCase.
     * Example: Converts blog_example to BlogExample
     *
     * @param string $string String to be converted to camel case
     * @return string UpperCamelCasedWord
     */
    public static function underscoredToUpperCamelCase($string)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($string))));
    }

    /**
     * Returns a given string with underscores as lowerCamelCase.
     * Example: Converts minimal_value to minimalValue
     *
     * @param string $string String to be converted to camel case
     * @return string lowerCamelCasedWord
     */
    public static function underscoredToLowerCamelCase($string)
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($string)))));
    }

    /**
     * Returns a given CamelCasedString as an lowercase string with underscores.
     * Example: Converts BlogExample to blog_example, and minimalValue to minimal_value
     *
     * @param string $string String to be converted to lowercase underscore
     * @return string lowercase_and_underscored_string
     */
    public static function camelCaseToLowerCaseUnderscored($string)
    {
        $value = preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $string);
        return mb_strtolower($value, 'utf-8');
    }

    /*************************
     *
     * ARRAY FUNCTIONS
     *
     *************************/

    /**
     * Explodes a $string delimited by $delimiter and casts each item in the array to (int).
     * Corresponds to \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(), but with conversion to integers for all values.
     *
     * @param string $delimiter Delimiter string to explode with
     * @param string $string The string to explode
     * @param bool $removeEmptyValues If set, all empty values (='') will NOT be set in output
     * @param int $limit If positive, the result will contain a maximum of limit elements,
     * @return array Exploded values, all converted to integers
     */
    public static function intExplode($delimiter, $string, $removeEmptyValues = false, $limit = 0)
    {
        $result = explode($delimiter, $string);
        foreach ($result as $key => &$value) {
            if ($removeEmptyValues && ($value === '' || trim($value) === '')) {
                unset($result[$key]);
            } else {
                $value = (int)$value;
            }
        }
        unset($value);
        if ($limit !== 0) {
            if ($limit < 0) {
                $result = array_slice($result, 0, $limit);
            } elseif (count($result) > $limit) {
                $lastElements = array_slice($result, $limit - 1);
                $result = array_slice($result, 0, $limit - 1);
                $result[] = implode($delimiter, $lastElements);
            }
        }
        return $result;
    }

    /**
     * Reverse explode which explodes the string counting from behind.
     *
     * Note: The delimiter has to given in the reverse order as
     *       it is occurring within the string.
     *
     * GeneralUtility::revExplode('[]', '[my][words][here]', 2)
     *   ==> array('[my][words', 'here]')
     *
     * @param string $delimiter Delimiter string to explode with
     * @param string $string The string to explode
     * @param int $count Number of array entries
     * @return array Exploded values
     */
    public static function revExplode($delimiter, $string, $count = 0)
    {
        // 2 is the (currently, as of 2014-02) most-used value for $count in the core, therefore we check it first
        if ($count === 2) {
            $position = strrpos($string, strrev($delimiter));
            if ($position !== false) {
                return [substr($string, 0, $position), substr($string, $position + strlen($delimiter))];
            }
            return [$string];
        }
        if ($count <= 1) {
            return [$string];
        }
        $explodedValues = explode($delimiter, strrev($string), $count);
        $explodedValues = array_map('strrev', $explodedValues);
        return array_reverse($explodedValues);
    }

    /**
     * Explodes a string and trims all values for whitespace in the end.
     * If $onlyNonEmptyValues is set, then all blank ('') values are removed.
     *
     * @param string $delim Delimiter string to explode with
     * @param string $string The string to explode
     * @param bool $removeEmptyValues If set, all empty values will be removed in output
     * @param int $limit If limit is set and positive, the returned array will contain a maximum of limit elements with
     *                   the last element containing the rest of string. If the limit parameter is negative, all components
     *                   except the last -limit are returned.
     * @return array Exploded values
     */
    public static function trimExplode($delim, $string, $removeEmptyValues = false, $limit = 0)
    {
        $result = explode($delim, $string);
        if ($removeEmptyValues) {
            $temp = [];
            foreach ($result as $value) {
                if (trim($value) !== '') {
                    $temp[] = $value;
                }
            }
            $result = $temp;
        }
        if ($limit > 0 && count($result) > $limit) {
            $lastElements = array_splice($result, $limit - 1);
            $result[] = implode($delim, $lastElements);
        } elseif ($limit < 0) {
            $result = array_slice($result, 0, $limit);
        }
        $result = array_map('trim', $result);
        return $result;
    }

    /**
     * Implodes a multidim-array into GET-parameters (eg. &param[key][key2]=value2&param[key][key3]=value3)
     *
     * @param string $name Name prefix for entries. Set to blank if you wish none.
     * @param array $theArray The (multidimensional) array to implode
     * @param string $str (keep blank)
     * @param bool $skipBlank If set, parameters which were blank strings would be removed.
     * @param bool $rawurlencodeParamName If set, the param name itself (for example "param[key][key2]") would be rawurlencoded as well.
     * @return string Imploded result, fx. &param[key][key2]=value2&param[key][key3]=value3
     * @see explodeUrl2Array()
     */
    public static function implodeArrayForUrl($name, array $theArray, $str = '', $skipBlank = false, $rawurlencodeParamName = false)
    {
        foreach ($theArray as $Akey => $AVal) {
            $thisKeyName = $name ? $name . '[' . $Akey . ']' : $Akey;
            if (is_array($AVal)) {
                $str = self::implodeArrayForUrl($thisKeyName, $AVal, $str, $skipBlank, $rawurlencodeParamName);
            } else {
                if (!$skipBlank || (string)$AVal !== '') {
                    $str .= '&' . ($rawurlencodeParamName ? rawurlencode($thisKeyName) : $thisKeyName) . '=' . rawurlencode($AVal);
                }
            }
        }
        return $str;
    }

    /**
     * Explodes a string with GETvars (eg. "&id=1&type=2&ext[mykey]=3") into an array
     *
     * @param string $string GETvars string
     * @param bool $multidim If set, the string will be parsed into a multidimensional array if square brackets are used in variable names (using PHP function parse_str())
     * @return array Array of values. All values AND keys are rawurldecoded() as they properly should be. But this means that any implosion of the array again must rawurlencode it!
     * @see implodeArrayForUrl()
     */
    public static function explodeUrl2Array($string, $multidim = false)
    {
        $output = [];
        if ($multidim) {
            parse_str($string, $output);
        } else {
            $p = explode('&', $string);
            foreach ($p as $v) {
                if ($v !== '') {
                    list($pK, $pV) = explode('=', $v, 2);
                    $output[rawurldecode($pK)] = rawurldecode($pV);
                }
            }
        }
        return $output;
    }

    /**
     * Returns an array with selected keys from incoming data.
     * (Better read source code if you want to find out...)
     *
     * @param string $varList List of variable/key names
     * @param array $getArray Array from where to get values based on the keys in $varList
     * @param bool $GPvarAlt If set, then \TYPO3\CMS\Core\Utility\GeneralUtility::_GP() is used to fetch the value if not found (isset) in the $getArray
     * @return array Output array with selected variables.
     */
    public static function compileSelectedGetVarsFromArray($varList, array $getArray, $GPvarAlt = true)
    {
        $keys = self::trimExplode(',', $varList, true);
        $outArr = [];
        foreach ($keys as $v) {
            if (isset($getArray[$v])) {
                $outArr[$v] = $getArray[$v];
            } elseif ($GPvarAlt) {
                $outArr[$v] = self::_GP($v);
            }
        }
        return $outArr;
    }

    /*************************
     *
     * HTML/XML PROCESSING
     *
     *************************/
    /**
     * Returns an array with all attributes of the input HTML tag as key/value pairs. Attributes are only lowercase a-z
     * $tag is either a whole tag (eg '<TAG OPTION ATTRIB=VALUE>') or the parameter list (ex ' OPTION ATTRIB=VALUE>')
     * If an attribute is empty, then the value for the key is empty. You can check if it existed with isset()
     *
     * @param string $tag HTML-tag string (or attributes only)
     * @return array Array with the attribute values.
     */
    public static function get_tag_attributes($tag)
    {
        $components = self::split_tag_attributes($tag);
        // Attribute name is stored here
        $name = '';
        $valuemode = false;
        $attributes = [];
        foreach ($components as $key => $val) {
            // Only if $name is set (if there is an attribute, that waits for a value), that valuemode is enabled. This ensures that the attribute is assigned it's value
            if ($val !== '=') {
                if ($valuemode) {
                    if ($name) {
                        $attributes[$name] = $val;
                        $name = '';
                    }
                } else {
                    if ($key = strtolower(preg_replace('/[^[:alnum:]_\\:\\-]/', '', $val))) {
                        $attributes[$key] = '';
                        $name = $key;
                    }
                }
                $valuemode = false;
            } else {
                $valuemode = true;
            }
        }
        return $attributes;
    }

    /**
     * Returns an array with the 'components' from an attribute list from an HTML tag. The result is normally analyzed by get_tag_attributes
     * Removes tag-name if found
     *
     * @param string $tag HTML-tag string (or attributes only)
     * @return array Array with the attribute values.
     */
    public static function split_tag_attributes($tag)
    {
        $tag_tmp = trim(preg_replace('/^<[^[:space:]]*/', '', trim($tag)));
        // Removes any > in the end of the string
        $tag_tmp = trim(rtrim($tag_tmp, '>'));
        $value = [];
        // Compared with empty string instead , 030102
        while ($tag_tmp !== '') {
            $firstChar = $tag_tmp[0];
            if ($firstChar === '"' || $firstChar === '\'') {
                $reg = explode($firstChar, $tag_tmp, 3);
                $value[] = $reg[1];
                $tag_tmp = trim($reg[2]);
            } elseif ($firstChar === '=') {
                $value[] = '=';
                // Removes = chars.
                $tag_tmp = trim(substr($tag_tmp, 1));
            } else {
                // There are '' around the value. We look for the next ' ' or '>'
                $reg = preg_split('/[[:space:]=]/', $tag_tmp, 2);
                $value[] = trim($reg[0]);
                $tag_tmp = trim(substr($tag_tmp, strlen($reg[0]), 1) . $reg[1]);
            }
        }
        reset($value);
        return $value;
    }

    /**
     * Implodes attributes in the array $arr for an attribute list in eg. and HTML tag (with quotes)
     *
     * @param array $arr Array with attribute key/value pairs, eg. "bgcolor"=>"red", "border"=>0
     * @param bool $xhtmlSafe If set the resulting attribute list will have a) all attributes in lowercase (and duplicates weeded out, first entry taking precedence) and b) all values htmlspecialchar()'ed. It is recommended to use this switch!
     * @param bool $dontOmitBlankAttribs If TRUE, don't check if values are blank. Default is to omit attributes with blank values.
     * @return string Imploded attributes, eg. 'bgcolor="red" border="0"'
     */
    public static function implodeAttributes(array $arr, $xhtmlSafe = false, $dontOmitBlankAttribs = false)
    {
        if ($xhtmlSafe) {
            $newArr = [];
            foreach ($arr as $p => $v) {
                if (!isset($newArr[strtolower($p)])) {
                    $newArr[strtolower($p)] = htmlspecialchars($v);
                }
            }
            $arr = $newArr;
        }
        $list = [];
        foreach ($arr as $p => $v) {
            if ((string)$v !== '' || $dontOmitBlankAttribs) {
                $list[] = $p . '="' . $v . '"';
            }
        }
        return implode(' ', $list);
    }

    /**
     * Parses XML input into a PHP array with associative keys
     *
     * @param string $string XML data input
     * @param int $depth Number of element levels to resolve the XML into an array. Any further structure will be set as XML.
     * @param array $parserOptions Options that will be passed to PHP's xml_parser_set_option()
     * @return mixed The array with the parsed structure unless the XML parser returns with an error in which case the error message string is returned.
     */
    public static function xml2tree($string, $depth = 999, $parserOptions = [])
    {
        // Disables the functionality to allow external entities to be loaded when parsing the XML, must be kept
        $previousValueOfEntityLoader = libxml_disable_entity_loader(true);
        $parser = xml_parser_create();
        $vals = [];
        $index = [];
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 0);
        foreach ($parserOptions as $option => $value) {
            xml_parser_set_option($parser, $option, $value);
        }
        xml_parse_into_struct($parser, $string, $vals, $index);
        libxml_disable_entity_loader($previousValueOfEntityLoader);
        if (xml_get_error_code($parser)) {
            return 'Line ' . xml_get_current_line_number($parser) . ': ' . xml_error_string(xml_get_error_code($parser));
        }
        xml_parser_free($parser);
        $stack = [[]];
        $stacktop = 0;
        $startPoint = 0;
        $tagi = [];
        foreach ($vals as $key => $val) {
            $type = $val['type'];
            // open tag:
            if ($type === 'open' || $type === 'complete') {
                $stack[$stacktop++] = $tagi;
                if ($depth == $stacktop) {
                    $startPoint = $key;
                }
                $tagi = ['tag' => $val['tag']];
                if (isset($val['attributes'])) {
                    $tagi['attrs'] = $val['attributes'];
                }
                if (isset($val['value'])) {
                    $tagi['values'][] = $val['value'];
                }
            }
            // finish tag:
            if ($type === 'complete' || $type === 'close') {
                $oldtagi = $tagi;
                $tagi = $stack[--$stacktop];
                $oldtag = $oldtagi['tag'];
                unset($oldtagi['tag']);
                if ($depth == $stacktop + 1) {
                    if ($key - $startPoint > 0) {
                        $partArray = array_slice($vals, $startPoint + 1, $key - $startPoint - 1);
                        $oldtagi['XMLvalue'] = self::xmlRecompileFromStructValArray($partArray);
                    } else {
                        $oldtagi['XMLvalue'] = $oldtagi['values'][0];
                    }
                }
                $tagi['ch'][$oldtag][] = $oldtagi;
                unset($oldtagi);
            }
            // cdata
            if ($type === 'cdata') {
                $tagi['values'][] = $val['value'];
            }
        }
        return $tagi['ch'];
    }

    /**
     * Converts a PHP array into an XML string.
     * The XML output is optimized for readability since associative keys are used as tag names.
     * This also means that only alphanumeric characters are allowed in the tag names AND only keys NOT starting with numbers (so watch your usage of keys!). However there are options you can set to avoid this problem.
     * Numeric keys are stored with the default tag name "numIndex" but can be overridden to other formats)
     * The function handles input values from the PHP array in a binary-safe way; All characters below 32 (except 9,10,13) will trigger the content to be converted to a base64-string
     * The PHP variable type of the data IS preserved as long as the types are strings, arrays, integers and booleans. Strings are the default type unless the "type" attribute is set.
     * The output XML has been tested with the PHP XML-parser and parses OK under all tested circumstances with 4.x versions. However, with PHP5 there seems to be the need to add an XML prologue a la <?xml version="1.0" encoding="[charset]" standalone="yes" ?> - otherwise UTF-8 is assumed! Unfortunately, many times the output from this function is used without adding that prologue meaning that non-ASCII characters will break the parsing!! This suchs of course! Effectively it means that the prologue should always be prepended setting the right characterset, alternatively the system should always run as utf-8!
     * However using MSIE to read the XML output didn't always go well: One reason could be that the character encoding is not observed in the PHP data. The other reason may be if the tag-names are invalid in the eyes of MSIE. Also using the namespace feature will make MSIE break parsing. There might be more reasons...
     *
     * @param array $array The input PHP array with any kind of data; text, binary, integers. Not objects though.
     * @param string $NSprefix tag-prefix, eg. a namespace prefix like "T3:"
     * @param int $level Current recursion level. Don't change, stay at zero!
     * @param string $docTag Alternative document tag. Default is "phparray".
     * @param int $spaceInd If greater than zero, then the number of spaces corresponding to this number is used for indenting, if less than zero - no indentation, if zero - a single TAB is used
     * @param array $options Options for the compilation. Key "useNindex" => 0/1 (boolean: whether to use "n0, n1, n2" for num. indexes); Key "useIndexTagForNum" => "[tag for numerical indexes]"; Key "useIndexTagForAssoc" => "[tag for associative indexes"; Key "parentTagMap" => array('parentTag' => 'thisLevelTag')
     * @param array $stackData Stack data. Don't touch.
     * @return string An XML string made from the input content in the array.
     * @see xml2array()
     */
    public static function array2xml(array $array, $NSprefix = '', $level = 0, $docTag = 'phparray', $spaceInd = 0, array $options = [], array $stackData = [])
    {
        // The list of byte values which will trigger binary-safe storage. If any value has one of these char values in it, it will be encoded in base64
        $binaryChars = chr(0) . chr(1) . chr(2) . chr(3) . chr(4) . chr(5) . chr(6) . chr(7) . chr(8) . chr(11) . chr(12) . chr(14) . chr(15) . chr(16) . chr(17) . chr(18) . chr(19) . chr(20) . chr(21) . chr(22) . chr(23) . chr(24) . chr(25) . chr(26) . chr(27) . chr(28) . chr(29) . chr(30) . chr(31);
        // Set indenting mode:
        $indentChar = $spaceInd ? ' ' : TAB;
        $indentN = $spaceInd > 0 ? $spaceInd : 1;
        $nl = $spaceInd >= 0 ? LF : '';
        // Init output variable:
        $output = '';
        // Traverse the input array
        foreach ($array as $k => $v) {
            $attr = '';
            $tagName = $k;
            // Construct the tag name.
            // Use tag based on grand-parent + parent tag name
            if (isset($options['grandParentTagMap'][$stackData['grandParentTagName'] . '/' . $stackData['parentTagName']])) {
                $attr .= ' index="' . htmlspecialchars($tagName) . '"';
                $tagName = (string)$options['grandParentTagMap'][$stackData['grandParentTagName'] . '/' . $stackData['parentTagName']];
            } elseif (isset($options['parentTagMap'][$stackData['parentTagName'] . ':_IS_NUM']) && MathUtility::canBeInterpretedAsInteger($tagName)) {
                // Use tag based on parent tag name + if current tag is numeric
                $attr .= ' index="' . htmlspecialchars($tagName) . '"';
                $tagName = (string)$options['parentTagMap'][$stackData['parentTagName'] . ':_IS_NUM'];
            } elseif (isset($options['parentTagMap'][$stackData['parentTagName'] . ':' . $tagName])) {
                // Use tag based on parent tag name + current tag
                $attr .= ' index="' . htmlspecialchars($tagName) . '"';
                $tagName = (string)$options['parentTagMap'][$stackData['parentTagName'] . ':' . $tagName];
            } elseif (isset($options['parentTagMap'][$stackData['parentTagName']])) {
                // Use tag based on parent tag name:
                $attr .= ' index="' . htmlspecialchars($tagName) . '"';
                $tagName = (string)$options['parentTagMap'][$stackData['parentTagName']];
            } elseif (MathUtility::canBeInterpretedAsInteger($tagName)) {
                // If integer...;
                if ($options['useNindex']) {
                    // If numeric key, prefix "n"
                    $tagName = 'n' . $tagName;
                } else {
                    // Use special tag for num. keys:
                    $attr .= ' index="' . $tagName . '"';
                    $tagName = $options['useIndexTagForNum'] ?: 'numIndex';
                }
            } elseif ($options['useIndexTagForAssoc']) {
                // Use tag for all associative keys:
                $attr .= ' index="' . htmlspecialchars($tagName) . '"';
                $tagName = $options['useIndexTagForAssoc'];
            }
            // The tag name is cleaned up so only alphanumeric chars (plus - and _) are in there and not longer than 100 chars either.
            $tagName = substr(preg_replace('/[^[:alnum:]_-]/', '', $tagName), 0, 100);
            // If the value is an array then we will call this function recursively:
            if (is_array($v)) {
                // Sub elements:
                if ($options['alt_options'][$stackData['path'] . '/' . $tagName]) {
                    $subOptions = $options['alt_options'][$stackData['path'] . '/' . $tagName];
                    $clearStackPath = $subOptions['clearStackPath'];
                } else {
                    $subOptions = $options;
                    $clearStackPath = false;
                }
                if (empty($v)) {
                    $content = '';
                } else {
                    $content = $nl . self::array2xml($v, $NSprefix, ($level + 1), '', $spaceInd, $subOptions, [
                            'parentTagName' => $tagName,
                            'grandParentTagName' => $stackData['parentTagName'],
                            'path' => ($clearStackPath ? '' : $stackData['path'] . '/' . $tagName)
                        ]) . ($spaceInd >= 0 ? str_pad('', ($level + 1) * $indentN, $indentChar) : '');
                }
                // Do not set "type = array". Makes prettier XML but means that empty arrays are not restored with xml2array
                if ((int)$options['disableTypeAttrib'] != 2) {
                    $attr .= ' type="array"';
                }
            } else {
                // Just a value:
                // Look for binary chars:
                $vLen = strlen($v);
                // Go for base64 encoding if the initial segment NOT matching any binary char has the same length as the whole string!
                if ($vLen && strcspn($v, $binaryChars) != $vLen) {
                    // If the value contained binary chars then we base64-encode it an set an attribute to notify this situation:
                    $content = $nl . chunk_split(base64_encode($v));
                    $attr .= ' base64="1"';
                } else {
                    // Otherwise, just htmlspecialchar the stuff:
                    $content = htmlspecialchars($v);
                    $dType = gettype($v);
                    if ($dType === 'string') {
                        if ($options['useCDATA'] && $content != $v) {
                            $content = '<![CDATA[' . $v . ']]>';
                        }
                    } elseif (!$options['disableTypeAttrib']) {
                        $attr .= ' type="' . $dType . '"';
                    }
                }
            }
            if ((string)$tagName !== '') {
                // Add the element to the output string:
                $output .= ($spaceInd >= 0 ? str_pad('', ($level + 1) * $indentN, $indentChar) : '')
                    . '<' . $NSprefix . $tagName . $attr . '>' . $content . '</' . $NSprefix . $tagName . '>' . $nl;
            }
        }
        // If we are at the outer-most level, then we finally wrap it all in the document tags and return that as the value:
        if (!$level) {
            $output = '<' . $docTag . '>' . $nl . $output . '</' . $docTag . '>';
        }
        return $output;
    }

    /**
     * Converts an XML string to a PHP array.
     * This is the reverse function of array2xml()
     * This is a wrapper for xml2arrayProcess that adds a two-level cache
     *
     * @param string $string XML content to convert into an array
     * @param string $NSprefix The tag-prefix resolve, eg. a namespace like "T3:"
     * @param bool $reportDocTag If set, the document tag will be set in the key "_DOCUMENT_TAG" of the output array
     * @return mixed If the parsing had errors, a string with the error message is returned. Otherwise an array with the content.
     * @see array2xml(),xml2arrayProcess()
     */
    public static function xml2array($string, $NSprefix = '', $reportDocTag = false)
    {
        static $firstLevelCache = [];
        $identifier = md5($string . $NSprefix . ($reportDocTag ? '1' : '0'));
        // Look up in first level cache
        if (!empty($firstLevelCache[$identifier])) {
            $array = $firstLevelCache[$identifier];
        } else {
            $array = self::xml2arrayProcess(trim($string), $NSprefix, $reportDocTag);
            // Store content in first level cache
            $firstLevelCache[$identifier] = $array;
        }
        return $array;
    }

    /**
     * Converts an XML string to a PHP array.
     * This is the reverse function of array2xml()
     *
     * @param string $string XML content to convert into an array
     * @param string $NSprefix The tag-prefix resolve, eg. a namespace like "T3:"
     * @param bool $reportDocTag If set, the document tag will be set in the key "_DOCUMENT_TAG" of the output array
     * @return mixed If the parsing had errors, a string with the error message is returned. Otherwise an array with the content.
     * @see array2xml()
     */
    protected static function xml2arrayProcess($string, $NSprefix = '', $reportDocTag = false)
    {
        // Disables the functionality to allow external entities to be loaded when parsing the XML, must be kept
        $previousValueOfEntityLoader = libxml_disable_entity_loader(true);
        // Create parser:
        $parser = xml_parser_create();
        $vals = [];
        $index = [];
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 0);
        // Default output charset is UTF-8, only ASCII, ISO-8859-1 and UTF-8 are supported!!!
        $match = [];
        preg_match('/^[[:space:]]*<\\?xml[^>]*encoding[[:space:]]*=[[:space:]]*"([^"]*)"/', substr($string, 0, 200), $match);
        $theCharset = $match[1] ?: 'utf-8';
        // us-ascii / utf-8 / iso-8859-1
        xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, $theCharset);
        // Parse content:
        xml_parse_into_struct($parser, $string, $vals, $index);
        libxml_disable_entity_loader($previousValueOfEntityLoader);
        // If error, return error message:
        if (xml_get_error_code($parser)) {
            return 'Line ' . xml_get_current_line_number($parser) . ': ' . xml_error_string(xml_get_error_code($parser));
        }
        xml_parser_free($parser);
        // Init vars:
        $stack = [[]];
        $stacktop = 0;
        $current = [];
        $tagName = '';
        $documentTag = '';
        // Traverse the parsed XML structure:
        foreach ($vals as $key => $val) {
            // First, process the tag-name (which is used in both cases, whether "complete" or "close")
            $tagName = $val['tag'];
            if (!$documentTag) {
                $documentTag = $tagName;
            }
            // Test for name space:
            $tagName = $NSprefix && substr($tagName, 0, strlen($NSprefix)) == $NSprefix ? substr($tagName, strlen($NSprefix)) : $tagName;
            // Test for numeric tag, encoded on the form "nXXX":
            $testNtag = substr($tagName, 1);
            // Closing tag.
            $tagName = $tagName[0] === 'n' && MathUtility::canBeInterpretedAsInteger($testNtag) ? (int)$testNtag : $tagName;
            // Test for alternative index value:
            if ((string)($val['attributes']['index'] ?? '') !== '') {
                $tagName = $val['attributes']['index'];
            }
            // Setting tag-values, manage stack:
            switch ($val['type']) {
                case 'open':
                    // If open tag it means there is an array stored in sub-elements. Therefore increase the stackpointer and reset the accumulation array:
                    // Setting blank place holder
                    $current[$tagName] = [];
                    $stack[$stacktop++] = $current;
                    $current = [];
                    break;
                case 'close':
                    // If the tag is "close" then it is an array which is closing and we decrease the stack pointer.
                    $oldCurrent = $current;
                    $current = $stack[--$stacktop];
                    // Going to the end of array to get placeholder key, key($current), and fill in array next:
                    end($current);
                    $current[key($current)] = $oldCurrent;
                    unset($oldCurrent);
                    break;
                case 'complete':
                    // If "complete", then it's a value. If the attribute "base64" is set, then decode the value, otherwise just set it.
                    if ($val['attributes']['base64']) {
                        $current[$tagName] = base64_decode($val['value']);
                    } else {
                        // Had to cast it as a string - otherwise it would be evaluate FALSE if tested with isset()!!
                        $current[$tagName] = (string)$val['value'];
                        // Cast type:
                        switch ((string)$val['attributes']['type']) {
                            case 'integer':
                                $current[$tagName] = (int)$current[$tagName];
                                break;
                            case 'double':
                                $current[$tagName] = (double)$current[$tagName];
                                break;
                            case 'boolean':
                                $current[$tagName] = (bool)$current[$tagName];
                                break;
                            case 'NULL':
                                $current[$tagName] = null;
                                break;
                            case 'array':
                                // MUST be an empty array since it is processed as a value; Empty arrays would end up here because they would have no tags inside...
                                $current[$tagName] = [];
                                break;
                        }
                    }
                    break;
            }
        }
        if ($reportDocTag) {
            $current[$tagName]['_DOCUMENT_TAG'] = $documentTag;
        }
        // Finally return the content of the document tag.
        return $current[$tagName];
    }

    /**
     * This implodes an array of XML parts (made with xml_parse_into_struct()) into XML again.
     *
     * @param array $vals An array of XML parts, see xml2tree
     * @return string Re-compiled XML data.
     */
    public static function xmlRecompileFromStructValArray(array $vals)
    {
        $XMLcontent = '';
        foreach ($vals as $val) {
            $type = $val['type'];
            // Open tag:
            if ($type === 'open' || $type === 'complete') {
                $XMLcontent .= '<' . $val['tag'];
                if (isset($val['attributes'])) {
                    foreach ($val['attributes'] as $k => $v) {
                        $XMLcontent .= ' ' . $k . '="' . htmlspecialchars($v) . '"';
                    }
                }
                if ($type === 'complete') {
                    if (isset($val['value'])) {
                        $XMLcontent .= '>' . htmlspecialchars($val['value']) . '</' . $val['tag'] . '>';
                    } else {
                        $XMLcontent .= '/>';
                    }
                } else {
                    $XMLcontent .= '>';
                }
                if ($type === 'open' && isset($val['value'])) {
                    $XMLcontent .= htmlspecialchars($val['value']);
                }
            }
            // Finish tag:
            if ($type === 'close') {
                $XMLcontent .= '</' . $val['tag'] . '>';
            }
            // Cdata
            if ($type === 'cdata') {
                $XMLcontent .= htmlspecialchars($val['value']);
            }
        }
        return $XMLcontent;
    }

    /*************************
     *
     * FILES FUNCTIONS
     *
     *************************/
    /**
     * Writes $content to the file $file
     *
     * @param string $file Filepath to write to
     * @param string $content Content to write
     * @return bool TRUE if the file was successfully opened and written to.
     */
    public static function writeFile($file, $content)
    {
        if (!@is_file($file)) {
            $changePermissions = true;
        }
        if ($fd = fopen($file, 'wb')) {
            $res = fwrite($fd, $content);
            fclose($fd);
            if ($res === false) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Wrapper function for mkdir.
     *
     * @param string $newFolder Absolute path to folder, see PHP mkdir() function. Removes trailing slash internally.
     * @return bool TRUE if @mkdir went well!
     */
    public static function mkdir($newFolder)
    {
        return @mkdir($newFolder);
    }

    /**
     * Creates a directory - including parent directories if necessary and
     * sets permissions on newly created directories.
     *
     * @param string $directory Target directory to create. Must a have trailing slash
     * @param string $deepDirectory Directory to create. This second parameter
     * @throws \InvalidArgumentException If $directory or $deepDirectory are not strings
     * @throws \RuntimeException If directory could not be created
     */
    public static function mkdir_deep($directory, $deepDirectory = '')
    {
        if (!is_string($directory)) {
            throw new \InvalidArgumentException('The specified directory is of type "' . gettype($directory) . '" but a string is expected.', 1303662955);
        }
        if (!is_string($deepDirectory)) {
            throw new \InvalidArgumentException('The specified directory is of type "' . gettype($deepDirectory) . '" but a string is expected.', 1303662956);
        }
        // Ensure there is only one slash
        $fullPath = rtrim($directory, '/') . '/' . ltrim($deepDirectory, '/');
        if ($fullPath !== '/' && !is_dir($fullPath)) {
            $firstCreatedPath = static::createDirectoryPath($fullPath);
        }
    }

    /**
     * Creates directories for the specified paths if they do not exist. This
     * functions sets proper permission mask but does not set proper user and
     * group.
     *
     * @static
     * @param string $fullDirectoryPath
     * @return string Path to the the first created directory in the hierarchy
     * @throws \RuntimeException If directory could not be created
     */
    protected static function createDirectoryPath($fullDirectoryPath)
    {
        $currentPath = $fullDirectoryPath;
        $firstCreatedPath = '';
        if (!@is_dir($currentPath)) {
            do {
                $firstCreatedPath = $currentPath;
                $separatorPosition = strrpos($currentPath, DIRECTORY_SEPARATOR);
                $currentPath = substr($currentPath, 0, $separatorPosition);
            } while (!is_dir($currentPath) && $separatorPosition !== false);
            $result = @mkdir($fullDirectoryPath, 0640, true);
            // Check existence of directory again to avoid race condition. Directory could have get created by another process between previous is_dir() and mkdir()
            if (!$result && !@is_dir($fullDirectoryPath)) {
                throw new \RuntimeException('Could not create directory "' . $fullDirectoryPath . '"!', 1170251401);
            }
        }
        return $firstCreatedPath;
    }

    /**
     * Wrapper function for rmdir, allowing recursive deletion of folders and files
     *
     * @param string $path Absolute path to folder, see PHP rmdir() function. Removes trailing slash internally.
     * @param bool $removeNonEmpty Allow deletion of non-empty directories
     * @return bool TRUE if @rmdir went well!
     */
    public static function rmdir($path, $removeNonEmpty = false)
    {
        $OK = false;
        // Remove trailing slash
        $path = preg_replace('|/$|', '', $path);
        if (file_exists($path)) {
            $OK = true;
            if (!is_link($path) && is_dir($path)) {
                if ($removeNonEmpty == true && ($handle = @opendir($path))) {
                    while ($OK && false !== ($file = readdir($handle))) {
                        if ($file === '.' || $file === '..') {
                            continue;
                        }
                        $OK = static::rmdir($path . '/' . $file, $removeNonEmpty);
                    }
                    closedir($handle);
                }
                if ($OK) {
                    $OK = @rmdir($path);
                }
            } elseif (is_link($path) && is_dir($path) && TYPO3_OS === 'WIN') {
                $OK = @rmdir($path);
            } else {
                // If $path is a file, simply remove it
                $OK = @unlink($path);
            }
            clearstatcache();
        } elseif (is_link($path)) {
            $OK = @unlink($path);
            if (!$OK && TYPO3_OS === 'WIN') {
                // Try to delete dead folder links on Windows systems
                $OK = @rmdir($path);
            }
            clearstatcache();
        }
        return $OK;
    }

    /**
     * Flushes a directory by first moving to a temporary resource, and then
     * triggering the remove process. This way directories can be flushed faster
     * to prevent race conditions on concurrent processes accessing the same directory.
     *
     * @param string $directory The directory to be renamed and flushed
     * @param bool $keepOriginalDirectory Whether to only empty the directory and not remove it
     * @param bool $flushOpcodeCache Also flush the opcode cache right after renaming the directory.
     * @return bool Whether the action was successful
     */
    public static function flushDirectory($directory, $keepOriginalDirectory = false, $flushOpcodeCache = false)
    {
        $result = false;

        if (is_link($directory)) {
            // Avoid attempting to rename the symlink see #87367
            $directory = realpath($directory);
        }

        if (is_dir($directory)) {
            $temporaryDirectory = rtrim($directory, '/') . '.' . StringUtility::getUniqueId('remove');
            if (rename($directory, $temporaryDirectory)) {
                if ($flushOpcodeCache) {
                    $opcodeCacheService = new OpcodeCacheService();
                    $opcodeCacheService->clearAllActive($directory);
                }
                if ($keepOriginalDirectory) {
                    static::mkdir($directory);
                }
                clearstatcache();
                $result = static::rmdir($temporaryDirectory, true);
            }
        }

        return $result;
    }

    /**
     * Returns an array with the names of folders in a specific path
     * Will return 'error' (string) if there were an error with reading directory content.
     * Will return null if provided path is false.
     *
     * @param string $path Path to list directories from
     * @return array|string|null Returns an array with the directory entries as values. If no path is provided, the return value will be null.
     */
    public static function get_dirs($path)
    {
        $dirs = null;
        if ($path) {
            if (is_dir($path)) {
                $dir = scandir($path);
                $dirs = [];
                foreach ($dir as $entry) {
                    if (is_dir($path . '/' . $entry) && $entry !== '..' && $entry !== '.') {
                        $dirs[] = $entry;
                    }
                }
            } else {
                $dirs = 'error';
            }
        }
        return $dirs;
    }

    /**
     * Finds all files in a given path and returns them as an array. Each
     * array key is a md5 hash of the full path to the file. This is done because
     * 'some' extensions like the import/export extension depend on this.
     *
     * @param string $path The path to retrieve the files from.
     * @param string $extensionList A comma-separated list of file extensions. Only files of the specified types will be retrieved. When left blank, files of any type will be retrieved.
     * @param bool $prependPath If TRUE, the full path to the file is returned. If FALSE only the file name is returned.
     * @param string $order The sorting order. The default sorting order is alphabetical. Setting $order to 'mtime' will sort the files by modification time.
     * @param string $excludePattern A regular expression pattern of file names to exclude. For example: 'clear.gif' or '(clear.gif|.htaccess)'. The pattern will be wrapped with: '/^' and '$/'.
     * @return array|string Array of the files found, or an error message in case the path could not be opened.
     */
    public static function getFilesInDir($path, $extensionList = '', $prependPath = false, $order = '', $excludePattern = '')
    {
        $excludePattern = (string)$excludePattern;
        $path = rtrim($path, '/');
        if (!@is_dir($path)) {
            return [];
        }

        $rawFileList = scandir($path);
        if ($rawFileList === false) {
            return 'error opening path: "' . $path . '"';
        }

        $pathPrefix = $path . '/';
        $allowedFileExtensionArray = self::trimExplode(',', $extensionList);
        $extensionList = ',' . str_replace(' ', '', $extensionList) . ',';
        $files = [];
        foreach ($rawFileList as $entry) {
            $completePathToEntry = $pathPrefix . $entry;
            if (!@is_file($completePathToEntry)) {
                continue;
            }

            foreach ($allowedFileExtensionArray as $allowedFileExtension) {
                if (
                    ($extensionList === ',,' || stripos($extensionList, ',' . substr($entry, strlen($allowedFileExtension)*-1, strlen($allowedFileExtension)) . ',') !== false)
                    && ($excludePattern === '' || !preg_match(('/^' . $excludePattern . '$/'), $entry))
                ) {
                    if ($order !== 'mtime') {
                        $files[] = $entry;
                    } else {
                        // Store the value in the key so we can do a fast asort later.
                        $files[$entry] = filemtime($completePathToEntry);
                    }
                }
            }
        }

        $valueName = 'value';
        if ($order === 'mtime') {
            asort($files);
            $valueName = 'key';
        }

        $valuePathPrefix = $prependPath ? $pathPrefix : '';
        $foundFiles = [];
        foreach ($files as $key => $value) {
            // Don't change this ever - extensions may depend on the fact that the hash is an md5 of the path! (import/export extension)
            $foundFiles[md5($pathPrefix . ${$valueName})] = $valuePathPrefix . ${$valueName};
        }

        return $foundFiles;
    }

    /**
     * Recursively gather all files and folders of a path.
     *
     * @param array $fileArr Empty input array (will have files added to it)
     * @param string $path The path to read recursively from (absolute) (include trailing slash!)
     * @param string $extList Comma list of file extensions: Only files with extensions in this list (if applicable) will be selected.
     * @param bool $regDirs If set, directories are also included in output.
     * @param int $recursivityLevels The number of levels to dig down...
     * @param string $excludePattern regex pattern of files/directories to exclude
     * @return array An array with the found files/directories.
     */
    public static function getAllFilesAndFoldersInPath(array $fileArr, $path, $extList = '', $regDirs = false, $recursivityLevels = 99, $excludePattern = '')
    {
        if ($regDirs) {
            $fileArr[md5($path)] = $path;
        }
        $fileArr = array_merge($fileArr, self::getFilesInDir($path, $extList, 1, 1, $excludePattern));
        $dirs = self::get_dirs($path);
        if ($recursivityLevels > 0 && is_array($dirs)) {
            foreach ($dirs as $subdirs) {
                if ((string)$subdirs !== '' && ($excludePattern === '' || !preg_match(('/^' . $excludePattern . '$/'), $subdirs))) {
                    $fileArr = self::getAllFilesAndFoldersInPath($fileArr, $path . $subdirs . '/', $extList, $regDirs, $recursivityLevels - 1, $excludePattern);
                }
            }
        }
        return $fileArr;
    }

    /**
     * Removes the absolute part of all files/folders in fileArr
     *
     * @param array $fileArr The file array to remove the prefix from
     * @param string $prefixToRemove The prefix path to remove (if found as first part of string!)
     * @return array|string The input $fileArr processed.
     */
    public static function removePrefixPathFromList(array $fileArr, $prefixToRemove)
    {
        foreach ($fileArr as $k => &$absFileRef) {
            if (self::isFirstPartOfStr($absFileRef, $prefixToRemove)) {
                $absFileRef = substr($absFileRef, strlen($prefixToRemove));
            } else {
                return 'ERROR: One or more of the files was NOT prefixed with the prefix-path!';
            }
        }
        unset($absFileRef);
        return $fileArr;
    }

    /**
     * Fixes a path for windows-backslashes and reduces double-slashes to single slashes
     *
     * @param string $theFile File path to process
     * @return string
     */
    public static function fixWindowsFilePath($theFile)
    {
        return str_replace(['\\', '//'], '/', $theFile);
    }

    /**
     * Resolves "../" sections in the input path string.
     * For example "fileadmin/directory/../other_directory/" will be resolved to "fileadmin/other_directory/"
     *
     * @param string $pathStr File path in which "/../" is resolved
     * @return string
     */
    public static function resolveBackPath($pathStr)
    {
        if (strpos($pathStr, '..') === false) {
            return $pathStr;
        }
        $parts = explode('/', $pathStr);
        $output = [];
        $c = 0;
        foreach ($parts as $part) {
            if ($part === '..') {
                if ($c) {
                    array_pop($output);
                    --$c;
                } else {
                    $output[] = $part;
                }
            } else {
                ++$c;
                $output[] = $part;
            }
        }
        return implode('/', $output);
    }

    /**
     * Prefixes a URL used with 'header-location' with 'http://...' depending on whether it has it already.
     * - If already having a scheme, nothing is prepended
     * - If having REQUEST_URI slash '/', then prefixing 'http://[host]' (relative to host)
     * - Otherwise prefixed with TYPO3_REQUEST_DIR (relative to current dir / TYPO3_REQUEST_DIR)
     *
     * @param string $path URL / path to prepend full URL addressing to.
     * @return string
     */
    public static function locationHeaderUrl($path)
    {
        if (strpos($path, '//') === 0) {
            return $path;
        }

        // relative to HOST
        if (strpos($path, '/') === 0) {
            return self::getIndpEnv('TYPO3_REQUEST_HOST') . $path;
        }

        $urlComponents = parse_url($path);
        if (!($urlComponents['scheme'] ?? false)) {
            // No scheme either
            return self::getIndpEnv('TYPO3_REQUEST_DIR') . $path;
        }

        return $path;
    }

    /**
     * Returns the maximum upload size for a file that is allowed. Measured in KB.
     * This might be handy to find out the real upload limit that is possible for this
     * TYPO3 installation.
     *
     * @return int The maximum size of uploads that are allowed (measured in kilobytes)
     */
    public static function getMaxUploadFileSize()
    {
        // Check for PHP restrictions of the maximum size of one of the $_FILES
        $phpUploadLimit = self::getBytesFromSizeMeasurement(ini_get('upload_max_filesize'));
        // Check for PHP restrictions of the maximum $_POST size
        $phpPostLimit = self::getBytesFromSizeMeasurement(ini_get('post_max_size'));
        // If the total amount of post data is smaller (!) than the upload_max_filesize directive,
        // then this is the real limit in PHP
        $phpUploadLimit = $phpPostLimit > 0 && $phpPostLimit < $phpUploadLimit ? $phpPostLimit : $phpUploadLimit;
        return floor(($phpUploadLimit)) / 1024;
    }

    /**
     * Gets the bytes value from a measurement string like "100k".
     *
     * @param string $measurement The measurement (e.g. "100k")
     * @return int The bytes value (e.g. 102400)
     */
    public static function getBytesFromSizeMeasurement($measurement)
    {
        $bytes = (float)$measurement;
        if (stripos($measurement, 'G')) {
            $bytes *= 1024 * 1024 * 1024;
        } elseif (stripos($measurement, 'M')) {
            $bytes *= 1024 * 1024;
        } elseif (stripos($measurement, 'K')) {
            $bytes *= 1024;
        }
        return $bytes;
    }

    /*************************
     *
     * SYSTEM INFORMATION
     *
     *************************/
    /**
     * Returns the link-url to the current script.
     * In $getParams you can set associative keys corresponding to the GET-vars you wish to add to the URL. If you set them empty, they will remove existing GET-vars from the current URL.
     * REMEMBER to always use htmlspecialchars() for content in href-properties to get ampersands converted to entities (XHTML requirement and XSS precaution)
     *
     * @param array $getParams Array of GET parameters to include
     * @return string
     */
    public static function linkThisScript(array $getParams = [])
    {
        $parts = self::getIndpEnv('SCRIPT_NAME');
        $params = self::_GET();
        foreach ($getParams as $key => $value) {
            if ($value !== '') {
                $params[$key] = $value;
            } else {
                unset($params[$key]);
            }
        }
        $pString = self::implodeArrayForUrl('', $params);
        return $pString ? $parts . '?' . ltrim($pString, '&') : $parts;
    }

    /**
     * Takes a full URL, $url, possibly with a querystring and overlays the $getParams arrays values onto the quirystring, packs it all together and returns the URL again.
     * So basically it adds the parameters in $getParams to an existing URL, $url
     *
     * @param string $url URL string
     * @param array $getParams Array of key/value pairs for get parameters to add/overrule with. Can be multidimensional.
     * @return string Output URL with added getParams.
     */
    public static function linkThisUrl($url, array $getParams = [])
    {
        $parts = parse_url($url);
        $getP = [];
        if ($parts['query']) {
            parse_str($parts['query'], $getP);
        }
        ArrayUtility::mergeRecursiveWithOverrule($getP, $getParams);
        $uP = explode('?', $url);
        $params = self::implodeArrayForUrl('', $getP);
        $outurl = $uP[0] . ($params ? '?' . substr($params, 1) : '');
        return $outurl;
    }

    /**
     * Abstraction method which returns System Environment Variables regardless of server OS, CGI/MODULE version etc. Basically this is SERVER variables for most of them.
     * This should be used instead of getEnv() and $_SERVER/ENV_VARS to get reliable values for all situations.
     *
     * @param string $getEnvName Name of the "environment variable"/"server variable" you wish to use. Valid values are SCRIPT_NAME, SCRIPT_FILENAME, REQUEST_URI, PATH_INFO, REMOTE_ADDR, REMOTE_HOST, HTTP_REFERER, HTTP_HOST, HTTP_USER_AGENT, HTTP_ACCEPT_LANGUAGE, QUERY_STRING, TYPO3_DOCUMENT_ROOT, TYPO3_HOST_ONLY, TYPO3_HOST_ONLY, TYPO3_REQUEST_HOST, TYPO3_REQUEST_URL, TYPO3_REQUEST_SCRIPT, TYPO3_REQUEST_DIR, TYPO3_SITE_URL, _ARRAY
     * @return string Value based on the input key, independent of server/os environment.
     * @throws \UnexpectedValueException
     */
    public static function getIndpEnv($getEnvName)
    {
        if (array_key_exists($getEnvName, self::$indpEnvCache)) {
            return self::$indpEnvCache[$getEnvName];
        }

        /*
        Conventions:
        output from parse_url():
        URL:	http://username:password@192.168.1.4:8080/typo3/32/temp/phpcheck/index.php/arg1/arg2/arg3/?arg1,arg2,arg3&p1=parameter1&p2[key]=value#link1
        [scheme] => 'http'
        [user] => 'username'
        [pass] => 'password'
        [host] => '192.168.1.4'
        [port] => '8080'
        [path] => '/typo3/32/temp/phpcheck/index.php/arg1/arg2/arg3/'
        [query] => 'arg1,arg2,arg3&p1=parameter1&p2[key]=value'
        [fragment] => 'link1'Further definition: [path_script] = '/typo3/32/temp/phpcheck/index.php'
        [path_dir] = '/typo3/32/temp/phpcheck/'
        [path_info] = '/arg1/arg2/arg3/'
        [path] = [path_script/path_dir][path_info]Keys supported:URI______:
        REQUEST_URI		=	[path]?[query]		= /typo3/32/temp/phpcheck/index.php/arg1/arg2/arg3/?arg1,arg2,arg3&p1=parameter1&p2[key]=value
        HTTP_HOST		=	[host][:[port]]		= 192.168.1.4:8080
        SCRIPT_NAME		=	[path_script]++		= /typo3/32/temp/phpcheck/index.php		// NOTICE THAT SCRIPT_NAME will return the php-script name ALSO. [path_script] may not do that (eg. '/somedir/' may result in SCRIPT_NAME '/somedir/index.php')!
        PATH_INFO		=	[path_info]			= /arg1/arg2/arg3/
        QUERY_STRING	=	[query]				= arg1,arg2,arg3&p1=parameter1&p2[key]=value
        HTTP_REFERER	=	[scheme]://[host][:[port]][path]	= http://192.168.1.4:8080/typo3/32/temp/phpcheck/index.php/arg1/arg2/arg3/?arg1,arg2,arg3&p1=parameter1&p2[key]=value
        (Notice: NO username/password + NO fragment)CLIENT____:
        REMOTE_ADDR		=	(client IP)
        REMOTE_HOST		=	(client host)
        HTTP_USER_AGENT	=	(client user agent)
        HTTP_ACCEPT_LANGUAGE	= (client accept language)SERVER____:
        SCRIPT_FILENAME	=	Absolute filename of script		(Differs between windows/unix). On windows 'C:\\blabla\\blabl\\' will be converted to 'C:/blabla/blabl/'Special extras:
        TYPO3_HOST_ONLY =		[host] = 192.168.1.4
        TYPO3_PORT =			[port] = 8080 (blank if 80, taken from host value)
        TYPO3_REQUEST_HOST = 		[scheme]://[host][:[port]]
        TYPO3_REQUEST_URL =		[scheme]://[host][:[port]][path]?[query] (scheme will by default be "http" until we can detect something different)
        TYPO3_REQUEST_SCRIPT =  	[scheme]://[host][:[port]][path_script]
        TYPO3_REQUEST_DIR =		[scheme]://[host][:[port]][path_dir]
        TYPO3_SITE_URL = 		[scheme]://[host][:[port]][path_dir] of the TYPO3 website frontend
        TYPO3_SITE_PATH = 		[path_dir] of the TYPO3 website frontend
        TYPO3_SITE_SCRIPT = 		[script / Speaking URL] of the TYPO3 website
        TYPO3_DOCUMENT_ROOT =		Absolute path of root of documents: TYPO3_DOCUMENT_ROOT.SCRIPT_NAME = SCRIPT_FILENAME (typically)
        TYPO3_SSL = 			Returns TRUE if this session uses SSL/TLS (https)
        TYPO3_PROXY = 			Returns TRUE if this session runs over a well known proxyNotice: [fragment] is apparently NEVER available to the script!Testing suggestions:
        - Output all the values.
        - In the script, make a link to the script it self, maybe add some parameters and click the link a few times so HTTP_REFERER is seen
        - ALSO TRY the script from the ROOT of a site (like 'http://www.mytest.com/' and not 'http://www.mytest.com/test/' !!)
         */
        $retVal = '';
        switch ((string)$getEnvName) {
            case 'SCRIPT_NAME':
                $retVal = self::isRunningOnCgiServerApi()
                && (($_SERVER['ORIG_PATH_INFO'] ?? false) ?: ($_SERVER['PATH_INFO'] ?? false))
                    ? (($_SERVER['ORIG_PATH_INFO'] ?? '') ?: ($_SERVER['PATH_INFO'] ?? ''))
                    : (($_SERVER['ORIG_SCRIPT_NAME'] ?? '') ?: ($_SERVER['SCRIPT_NAME'] ?? ''));
                break;
            case 'SCRIPT_FILENAME':
                $retVal = self::getPathThisScriptNonCli();
                break;
            case 'REQUEST_URI':
                if (!$_SERVER['REQUEST_URI']) {
                    // This is for ISS/CGI which does not have the REQUEST_URI available.
                    $retVal = '/' . ltrim(self::getIndpEnv('SCRIPT_NAME'), '/') . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');
                } else {
                    $retVal = '/' . ltrim($_SERVER['REQUEST_URI'], '/');
                }
                break;
            case 'PATH_INFO':
                // $_SERVER['PATH_INFO'] != $_SERVER['SCRIPT_NAME'] is necessary because some servers (Windows/CGI)
                // are seen to set PATH_INFO equal to script_name
                // Further, there must be at least one '/' in the path - else the PATH_INFO value does not make sense.
                // IF 'PATH_INFO' never works for our purpose in TYPO3 with CGI-servers,
                // then 'PHP_SAPI=='cgi'' might be a better check.
                // Right now strcmp($_SERVER['PATH_INFO'], GeneralUtility::getIndpEnv('SCRIPT_NAME')) will always
                // return FALSE for CGI-versions, but that is only as long as SCRIPT_NAME is set equal to PATH_INFO
                // because of PHP_SAPI=='cgi' (see above)
                if (!self::isRunningOnCgiServerApi()) {
                    $retVal = $_SERVER['PATH_INFO'];
                }
                break;
            case 'REMOTE_ADDR':
                $retVal = $_SERVER['REMOTE_ADDR'];
                break;
            case 'HTTP_HOST':
                // if it is not set we're most likely on the cli
                $retVal = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;
                break;
            case 'HTTP_REFERER':

            case 'HTTP_USER_AGENT':

            case 'HTTP_ACCEPT_ENCODING':

            case 'HTTP_ACCEPT_LANGUAGE':

            case 'REMOTE_HOST':

            case 'QUERY_STRING':
                if (isset($_SERVER[$getEnvName])) {
                    $retVal = $_SERVER[$getEnvName];
                }
                break;
            case 'TYPO3_DOCUMENT_ROOT':
                // Get the web root (it is not the root of the TYPO3 installation)
                // The absolute path of the script can be calculated with TYPO3_DOCUMENT_ROOT + SCRIPT_FILENAME
                // Some CGI-versions (LA13CGI) and mod-rewrite rules on MODULE versions will deliver a 'wrong' DOCUMENT_ROOT (according to our description). Further various aliases/mod_rewrite rules can disturb this as well.
                // Therefore the DOCUMENT_ROOT is now always calculated as the SCRIPT_FILENAME minus the end part shared with SCRIPT_NAME.
                $SFN = self::getIndpEnv('SCRIPT_FILENAME');
                $SN_A = explode('/', strrev(self::getIndpEnv('SCRIPT_NAME')));
                $SFN_A = explode('/', strrev($SFN));
                $acc = [];
                foreach ($SN_A as $kk => $vv) {
                    if ((string)$SFN_A[$kk] === (string)$vv) {
                        $acc[] = $vv;
                    } else {
                        break;
                    }
                }
                $commonEnd = strrev(implode('/', $acc));
                if ((string)$commonEnd !== '') {
                    $retVal = substr($SFN, 0, -(strlen($commonEnd) + 1));
                }
                break;
            case 'TYPO3_HOST_ONLY':
                $httpHost = self::getIndpEnv('HTTP_HOST');
                $httpHostBracketPosition = strpos($httpHost, ']');
                $httpHostParts = explode(':', $httpHost);
                $retVal = $httpHostBracketPosition !== false ? substr($httpHost, 0, $httpHostBracketPosition + 1) : array_shift($httpHostParts);
                break;
            case 'TYPO3_PORT':
                $httpHost = self::getIndpEnv('HTTP_HOST');
                $httpHostOnly = self::getIndpEnv('TYPO3_HOST_ONLY');
                $retVal = strlen($httpHost) > strlen($httpHostOnly) ? substr($httpHost, strlen($httpHostOnly) + 1) : '';
                break;
            case 'TYPO3_REQUEST_HOST':
                $retVal = (self::getIndpEnv('TYPO3_SSL') ? 'https://' : 'http://') . self::getIndpEnv('HTTP_HOST');
                break;
            case 'TYPO3_REQUEST_URL':
                $retVal = self::getIndpEnv('TYPO3_REQUEST_HOST') . self::getIndpEnv('REQUEST_URI');
                break;
            case 'TYPO3_REQUEST_SCRIPT':
                $retVal = self::getIndpEnv('TYPO3_REQUEST_HOST') . self::getIndpEnv('SCRIPT_NAME');
                break;
            case 'TYPO3_REQUEST_DIR':
                $retVal = self::getIndpEnv('TYPO3_REQUEST_HOST') . self::dirname(self::getIndpEnv('SCRIPT_NAME')) . '/';
                break;
            case 'TYPO3_SITE_URL':
                $url = self::getIndpEnv('TYPO3_REQUEST_DIR');
                // This can only be set by external entry scripts
                if (defined('TYPO3_PATH_WEB')) {
                    $retVal = $url;
                } elseif (self::getPathThisScriptNonCli() && defined('PATH_site')) {
                    $lPath = PathUtility::stripPathSitePrefix(dirname(self::getPathThisScriptNonCli())) . '/';
                    $siteUrl = substr($url, 0, -strlen($lPath));
                    if (substr($siteUrl, -1) !== '/') {
                        $siteUrl .= '/';
                    }
                    $retVal = $siteUrl;
                }
                break;
            case 'TYPO3_SITE_PATH':
                $retVal = substr(self::getIndpEnv('TYPO3_SITE_URL'), strlen(self::getIndpEnv('TYPO3_REQUEST_HOST')));
                break;
            case 'TYPO3_SITE_SCRIPT':
                $retVal = substr(self::getIndpEnv('TYPO3_REQUEST_URL'), strlen(self::getIndpEnv('TYPO3_SITE_URL')));
                break;
            case '_ARRAY':
                $out = [];
                // Here, list ALL possible keys to this function for debug display.
                $envTestVars = [
                    'HTTP_HOST',
                    'TYPO3_HOST_ONLY',
                    'TYPO3_PORT',
                    'PATH_INFO',
                    'QUERY_STRING',
                    'REQUEST_URI',
                    'HTTP_REFERER',
                    'TYPO3_REQUEST_HOST',
                    'TYPO3_REQUEST_URL',
                    'TYPO3_REQUEST_SCRIPT',
                    'TYPO3_REQUEST_DIR',
                    'TYPO3_SITE_URL',
                    'TYPO3_SITE_SCRIPT',
                    'SCRIPT_NAME',
                    'TYPO3_DOCUMENT_ROOT',
                    'SCRIPT_FILENAME',
                    'REMOTE_ADDR',
                    'REMOTE_HOST',
                    'HTTP_USER_AGENT',
                    'HTTP_ACCEPT_LANGUAGE'
                ];
                foreach ($envTestVars as $v) {
                    $out[$v] = self::getIndpEnv($v);
                }
                reset($out);
                $retVal = $out;
                break;
        }
        self::$indpEnvCache[$getEnvName] = $retVal;
        return $retVal;
    }

    /**
     * Gets the unixtime as milliseconds.
     *
     * @return int The unixtime as milliseconds
     */
    public static function milliseconds()
    {
        return round(microtime(true) * 1000);
    }

    /**
     * Client Browser Information
     *
     * @param string $useragent Alternative User Agent string (if empty, \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('HTTP_USER_AGENT') is used)
     * @return array Parsed information about the HTTP_USER_AGENT in categories BROWSER, VERSION, SYSTEM
     */
    public static function clientInfo($useragent = '')
    {
        if (!$useragent) {
            $useragent = self::getIndpEnv('HTTP_USER_AGENT');
        }
        $bInfo = [];
        // Which browser?
        if (strpos($useragent, 'Konqueror') !== false) {
            $bInfo['BROWSER'] = 'konqu';
        } elseif (strpos($useragent, 'Opera') !== false) {
            $bInfo['BROWSER'] = 'opera';
        } elseif (strpos($useragent, 'MSIE') !== false) {
            $bInfo['BROWSER'] = 'msie';
        } elseif (strpos($useragent, 'Mozilla') !== false) {
            $bInfo['BROWSER'] = 'net';
        } elseif (strpos($useragent, 'Flash') !== false) {
            $bInfo['BROWSER'] = 'flash';
        }
        if (isset($bInfo['BROWSER'])) {
            // Browser version
            switch ($bInfo['BROWSER']) {
                case 'net':
                    $bInfo['VERSION'] = (float)substr($useragent, 8);
                    if (strpos($useragent, 'Netscape6/') !== false) {
                        $bInfo['VERSION'] = (float)substr(strstr($useragent, 'Netscape6/'), 10);
                    }
                    // Will we ever know if this was a typo or intention...?! :-(
                    if (strpos($useragent, 'Netscape/6') !== false) {
                        $bInfo['VERSION'] = (float)substr(strstr($useragent, 'Netscape/6'), 10);
                    }
                    if (strpos($useragent, 'Netscape/7') !== false) {
                        $bInfo['VERSION'] = (float)substr(strstr($useragent, 'Netscape/7'), 9);
                    }
                    break;
                case 'msie':
                    $tmp = strstr($useragent, 'MSIE');
                    $bInfo['VERSION'] = (float)preg_replace('/^[^0-9]*/', '', substr($tmp, 4));
                    break;
                case 'opera':
                    $tmp = strstr($useragent, 'Opera');
                    $bInfo['VERSION'] = (float)preg_replace('/^[^0-9]*/', '', substr($tmp, 5));
                    break;
                case 'konqu':
                    $tmp = strstr($useragent, 'Konqueror/');
                    $bInfo['VERSION'] = (float)substr($tmp, 10);
                    break;
            }
            // Client system
            if (strpos($useragent, 'Win') !== false) {
                $bInfo['SYSTEM'] = 'win';
            } elseif (strpos($useragent, 'Mac') !== false) {
                $bInfo['SYSTEM'] = 'mac';
            } elseif (strpos($useragent, 'Linux') !== false || strpos($useragent, 'X11') !== false || strpos($useragent, 'SGI') !== false || strpos($useragent, ' SunOS ') !== false || strpos($useragent, ' HP-UX ') !== false) {
                $bInfo['SYSTEM'] = 'unix';
            }
        }
        return $bInfo;
    }

    /**
     * Get the fully-qualified domain name of the host.
     *
     * @param bool $requestHost Use request host (when not in CLI mode).
     * @return string The fully-qualified host name.
     */
    public static function getHostname($requestHost = true)
    {
        $host = '';
        // If not called from the command-line, resolve on getIndpEnv()
        if ($requestHost && !(TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_CLI)) {
            $host = self::getIndpEnv('HTTP_HOST');
        }
        if (!$host) {
            // will fail for PHP 4.1 and 4.2
            $host = @php_uname('n');
            // 'n' is ignored in broken installations
            if (strpos($host, ' ')) {
                $host = '';
            }
        }
        // We have not found a FQDN yet
        if ($host && strpos($host, '.') === false) {
            $ip = gethostbyname($host);
            // We got an IP address
            if ($ip != $host) {
                $fqdn = gethostbyaddr($ip);
                if ($ip != $fqdn) {
                    $host = $fqdn;
                }
            }
        }
        if (!$host) {
            $host = 'localhost.localdomain';
        }
        return $host;
    }

    /*************************
     *
     * TYPO3 SPECIFIC FUNCTIONS
     *
     *************************/
    /**
     * Checks for malicious file paths.
     *
     * Returns TRUE if no '//', '..', '\' or control characters are found in the $theFile.
     * This should make sure that the path is not pointing 'backwards' and further doesn't contain double/back slashes.
     * So it's compatible with the UNIX style path strings valid for TYPO3 internally.
     *
     * @param string $theFile File path to evaluate
     * @return bool TRUE, $theFile is allowed path string, FALSE otherwise
     * @see http://php.net/manual/en/security.filesystem.nullbytes.php
     */
    public static function validPathStr($theFile)
    {
        return strpos($theFile, '//') === false && strpos($theFile, '\\') === false
            && preg_match('#(?:^\\.\\.|/\\.\\./|[[:cntrl:]])#u', $theFile) === 0;
    }

    /**
     * Checks if the $path is absolute or relative (detecting either '/' or 'x:/' as first part of string) and returns TRUE if so.
     *
     * @param string $path File path to evaluate
     * @return bool
     */
    public static function isAbsPath($path)
    {
        return $path[0] === '/' || TYPO3_OS === 'WIN' && (strpos($path, ':/') === 1 || strpos($path, ':\\') === 1);
    }

    /**
     * Standard authentication code (used in Direct Mail, checkJumpUrl and setfixed links computations)
     *
     * @param mixed $uid_or_record Uid (int) or record (array)
     * @param string $fields List of fields from the record if that is given.
     * @param int $codeLength Length of returned authentication code.
     * @return string MD5 hash of 8 chars.
     */
    public static function stdAuthCode($uid_or_record, $fields = '', $codeLength = 8)
    {
        if (is_array($uid_or_record)) {
            $recCopy_temp = [];
            if ($fields) {
                $fieldArr = self::trimExplode(',', $fields, true);
                foreach ($fieldArr as $k => $v) {
                    $recCopy_temp[$k] = $uid_or_record[$v];
                }
            } else {
                $recCopy_temp = $uid_or_record;
            }
            $preKey = implode('|', $recCopy_temp);
        } else {
            $preKey = $uid_or_record;
        }
        $authCode = $preKey . '||4523617872349236483hffhg643178fg4318';
        $authCode = substr(md5($authCode), 0, $codeLength);
        return $authCode;
    }

    /**
     * Quotes a string for usage as JS parameter.
     *
     * @param string $value the string to encode, may be empty
     * @return string the encoded value already quoted (with single quotes),
     */
    public static function quoteJSvalue($value)
    {
        return strtr(
            json_encode((string)$value, JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG),
            [
                '"' => '\'',
                '\\\\' => '\\u005C',
                ' ' => '\\u0020',
                '!' => '\\u0021',
                '\\t' => '\\u0009',
                '\\n' => '\\u000A',
                '\\r' => '\\u000D'
            ]
        );
    }

    /**
     * Calculate path to entry script if not in cli mode.
     *
     * Depending on the environment, the script path is found in different $_SERVER variables.
     *
     * @return string Absolute path to entry script
     */
    protected static function getPathThisScriptNonCli()
    {
        $cgiPath = '';
        if (isset($_SERVER['ORIG_PATH_TRANSLATED'])) {
            $cgiPath = $_SERVER['ORIG_PATH_TRANSLATED'];
        } elseif (isset($_SERVER['PATH_TRANSLATED'])) {
            $cgiPath = $_SERVER['PATH_TRANSLATED'];
        }
        if ($cgiPath && in_array(PHP_SAPI, self::$supportedCgiServerApis, true)) {
            $scriptPath = $cgiPath;
        } else {
            if (isset($_SERVER['ORIG_SCRIPT_FILENAME'])) {
                $scriptPath = $_SERVER['ORIG_SCRIPT_FILENAME'];
            } else {
                $scriptPath = $_SERVER['SCRIPT_FILENAME'];
            }
        }
        return $scriptPath;
    }

    /**
     * Check if the current request is running on a CGI server API
     * @return bool
     */
    public static function isRunningOnCgiServerApi()
    {
        return in_array(PHP_SAPI, self::$supportedCgiServerApis, true);
    }
}