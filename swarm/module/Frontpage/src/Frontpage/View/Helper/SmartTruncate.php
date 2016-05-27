<?php
/**
 * Perforce Swarm
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Frontpage\View\Helper;

use Zend\View\Helper\AbstractHelper;

class SmartTruncate extends AbstractHelper
{
    /**
     * Returns truncated text, in a more intelligent fashion.
     *
     * @param $string                   The string to truncate.
     * @param $length                   The length to truncate the string to.
     * @param string $breakCharacter    The character to break at; defaults to '.' to break on sentence endings.
     * @param string $padText           The text to append at the end of the truncated section.
     * @return string                   The truncated string, with the $padText appended.
     */
    public function __invoke($string, $length, $breakCharacter = '.', $padText = '...')
    {
        //if string is less than limit return it
        if (strlen($string) < $length) {
            return $string;
        }

        //is the break character between the limit and the end of the string?
        $breakpoint = strpos($string, $breakCharacter, $length);
        if (false !== $breakpoint) {
            // if so, is the breakpoint within 1 char of the end of the string?
            // (this ensures the text breaks after the string length specified)
            if ($breakpoint < strlen($string) - strlen($breakCharacter)) {
                $string = substr($string, 0, $breakpoint) . $padText;
            }
        }

        return $string;
    }
}
