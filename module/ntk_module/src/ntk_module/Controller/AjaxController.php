<?php
/**
 * Ajax Controller Module
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace ntk_module\Controller;
use VuFind\Controller\AjaxController as AjaxControllerBase;

/**
 * This controller handles global AJAX functionality
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class AjaxController extends AjaxControllerBase
{
   /**
     * Get the contents of a lightbox; note that unlike most methods, this
     * one actually returns HTML rather than JSON.
     *
     * @return mixed
     */
    protected function getLightboxAjax()
    {
        // Turn layouts on for this action since we want to render the
        // page inside a lightbox:
        $this->layout()->setTemplate('layout/lightbox');

        // Call the requested action:
        return $this->forwardTo(
            $this->params()->fromQuery('submodule'),
            $this->params()->fromQuery('subaction')
        );
    }
 
    /**
     * Support method for getItemStatuses() -- when presented with multiple values,
     * pick which one(s) to send back via AJAX.
     *
     * @param array  $list Array of values to choose from.
     * @param string $mode config.ini setting -- first, all or msg
     * @param string $msg  Message to display if $mode == "msg"
     *
     * @return string
     */
    protected function pickValue($list, $mode, $msg)
    {
        // Make sure array contains only unique values:
        $list = array_unique($list);

        // If there is only one value in the list, or if we're in "first" mode,
        // send back the first list value:
        if ($mode == 'first' || count($list) == 1) {
            return $list[0];
        } else if (count($list) == 0) {
            // Empty list?  Return a blank string:
            return '';
        } else if ($mode == 'all') {
            // All values mode?  Return comma-separated values:
            return implode(', ', $list);
        } else {
            // Message mode?  Return the specified message, translated to the
            // appropriate language.
            $i = -1;
            foreach ($list as $key => $hold) {
                if($hold > 100 && $hold < 1000){
                    $i++;
                }
            }

            if ($i == $key){
                $msg = "UCT departments";
            }elseif(($i < $key) && ($i != -1)){
                $msg = "Multiple Locations";
            }else{

                $msg = $list[0];
            }
            return $msg;
        }
    }

    /**
     * Support method for getItemStatuses() -- process a single bibliographic record
     * for location settings other than "group".
     *
     * @param array  $record            Information on items linked to a single bib
     *                                  record
     * @param array  $messages          Custom status HTML
     *                                  (keys = available/unavailable)
     * @param string $locationSetting   The location mode setting used for
     *                                  pickValue()
     * @param string $callnumberSetting The callnumber mode setting used for
     *                                  pickValue()
     *
     * @return array                    Summarized availability information
     */
    protected function getItemStatus($record, $messages, $locationSetting,
        $callnumberSetting
    ) {
        // Summarize call number, location and availability info across all items:
        $callNumbers = $locations = array();
        $use_unknown_status = $available = false;
        foreach ($record as $info) {
            // Find an available copy
            if ($info['availability']) {
                $available = true;
            }
            // Check for a use_unknown_message flag
            if (isset($info['use_unknown_message'])
                && $info['use_unknown_message'] == true
            ) {
                $use_unknown_status = true;
            }
            // Store call number/location info:
            $callNumbers[] = $info['callnumber_second'];
            $locations[] = $info['location'];
        }

        // Determine call number string based on findings:
        $callNumber = $this->pickValue(
            $callNumbers, $callnumberSetting, 'Multiple Call Numbers'
        );

        // Determine location string based on findings:
        $collection_code = $this->pickValue(
            $locations, $locationSetting, '-'
        );

        if (preg_match("/(\d)([A-Z])(\d+)/", $collection_code, $matches)) {
            /* Regal. */
            $location = $this->translate("Shelf")." ".$collection_code;
        }
        elseif($collection_code == 200){
            /* Destnik, Kindle */
            $location = $this->translate("Central Desk, 2nd floor");
		}
		elseif($collection_code == 201){
			$location = $this->translate("Knowledge Navigation Corner, 2nd floor");
		}
        elseif(($collection_code > 100 && $collection_code < 1000) || ($collection_code == "UCT departments")){
            /* Pripad pro VSCHT ustavy, Aleph posila v location cisla v rozmezi 100 az 1000. */
            $location = $this->translate("UCT departments");
        }
        elseif($collection_code == "Multiple Locations"){
            $location = $this->translate("Multiple Locations");
        }
        elseif($collection_code === '01'){
            $location = $this->translate("Reading room of historical fund"); // badatelna HF
        }
        elseif($collection_code === '001'){
            $location = $this->translate('Volný výběr, nezařazeno');
        }
        elseif($collection_code === '011'){
            if($info['sub_lib_desc'] === "Fond UOCHB"){
                $location = $this->translate("UOCHB department"); //
            }else{
                $location = $this->translate("Depository"); // depozitar
            }
        }
        elseif($collection_code === '02'){
            $location = $this->translate("Safe of historical fund"); // trezor HF
        }
        elseif($collection_code === '002'){
            $location = $this->translate("Stack room"); // sklad
        }
        elseif($collection_code === '03'){
            $location = $this->translate("Stack room of historical fund"); // skald HF
        }
        elseif($collection_code === '004'){
            $location = $this->translate("Book news, 4th floor"); // novinky 4. NP
        }
        else{
	        $location = $this->translate("Unknown");
        }

        $availability_message = $use_unknown_status
            ? $messages['unknown']
            : $messages[$available ? 'available' : 'unavailable'];

        // Send back the collected details:
        return array(
            'id' => $record[0]['id'],
            'availability' => ($available ? 'true' : 'false'),
            'availability_message' => $availability_message,
            'location' => $location,
            'locationList' => false,
            'reserve' =>
                ($record[0]['reserve'] == 'Y' ? 'true' : 'false'),
            'reserve_message' => $record[0]['reserve'] == 'Y'
                ? $this->translate('on_reserve')
                : $this->translate('Not On Reserve'),
            'callnumber' => htmlentities($callNumber, ENT_COMPAT, 'UTF-8'),
            'status' => $this->translate($record[0]['duedate'])
        );
    }
}
