<?php

namespace ntk_module\Date;
use DateTime, DateTimeZone, VuFind\Exception\Date as DateException;
use VuFind\Date\Converter as ConverterBase;

/**
 * Date/time conversion functionality.
 *
 * @category VuFind
 * @package  Date
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Converter extends ConverterBase
{
    /**
     * Convert a date string to admin-defined format.
     *
     * @param string $createFormat The format of the date string to be changed
     * @param string $dateString   The date string
     *
     * @throws DateException
     * @return string               A re-formatted date string
     */
    public function convertToDisplayDate($createFormat, $dateString)
    {
        $outputFormat = "d.m.Y";
        return $this->convert($createFormat, $outputFormat, $dateString);
    }
}
