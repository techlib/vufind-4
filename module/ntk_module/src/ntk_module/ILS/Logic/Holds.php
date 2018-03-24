<?php
/**
 * Hold Logic Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  ILS_Logic
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace ntk_module\ILS\Logic;
use VuFind\ILS\Connection as ILSConnection, VuFind\ILS\Logic\Holds as HoldsBase;


/**
 * Hold Logic Class
 *
 * @category VuFind2
 * @package  ILS_Logic
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Holds extends HoldsBase
{
    /**
     * Constructor
     *
     * @param \VuFind\Auth\ILSAuthenticator $ilsAuth ILS authenticator
     * @param ILSConnection                 $ils     A catalog connection
     * @param \VuFind\Crypt\HMAC            $hmac    HMAC generator
     * @param \Zend\Config\Config           $config  VuFind configuration
     */
    public function __construct(\VuFind\Auth\ILSAuthenticator $ilsAuth, ILSConnection $ils,
        \VuFind\Crypt\HMAC $hmac, \Zend\Config\Config $config
    ) {
        $this->ilsAuth = $ilsAuth;
        $this->hmac = $hmac;
        $this->config = $config;

        if (isset($this->config->Record->hide_holdings)) {
            foreach ($this->config->Record->hide_holdings as $current) {
                $this->hideHoldings[] = $current;
            }
        }

        $this->catalog = $ils;
    }

    /**
     * Public method for getting item holdings from the catalog and selecting which
     * holding method to call
     *
     * @param string $id A Bib ID
     * @param array  $ids A list of Source Records (if catalog is for a consortium)
     *
     * @return array A sorted results set
     */

    public function getHoldings($id, $filters=array(), $format, $ids = null)
    {
        $holdings = [];

        // Get Holdings Data

        if ($this->catalog) {

            // Retrieve stored patron credentials; it is the responsibility of the
            // controller and view to inform the user that these credentials are
            // needed for hold data.
            try {
                $patron = $this->ilsAuth->storedCatalogLogin();
                // Does this ILS Driver handle consortial holdings?
                $config = $this->catalog->checkFunction(
                    'Holds', compact('id', 'patron')
                );
            } catch (ILSException $e) {
                $patron = false;
                $config = [];
            }

            if (isset($config['consortium']) && $config['consortium'] == true) {
                $result = $this->catalog->getConsortialHoldings(
                    $id, $patron ? $patron : null, $ids
                );
            } else {
                $result = $this->catalog->getHolding($id, $patron ? $patron : null, $filters);
            }
            
            $mode = $this->catalog->getHoldsMode();

            if ($mode == "disabled") {
                 $holdings = $this->standardHoldings($result);
            } else if ($mode == "driver") {
                $holdings = $this->driverHoldings($result, $id);
            } else {
                $holdings = $this->generateHoldings($result, $mode, $format);
            }

            $holdings = $this->processStorageRetrievalRequests($holdings, $id, $patron);
            $holdings = $this->processILLRequests($holdings, $id, $patron);
        }
        return $this->formatHoldings($holdings);
    }

    /**
     * Protected method for vufind (i.e. User) defined holdings
     *
     * @param array  $result A result set returned from a driver
     * @param string $type   The holds mode to be applied from:
     * (all, holds, recalls, availability)
     *
     * @return array A sorted results set
     */
    protected function generateHoldings($result, $type, $format)
    {
        $holdings = array();
        $any_available = false;

        $holds_override = isset($this->config->Catalog->allow_holds_override)
            ? $this->config->Catalog->allow_holds_override : false;

        if (count($result)) {
            foreach ($result as $copy) {
                $show = !in_array($copy['location'], $this->hideHoldings);
                if ($show) {
                    /* Vsechny jednotky jsou v jednom poli.
                     * zruseno rozdeleni podle umisteni.
                     * Priprava na razeni vsech jednotek dohromady. */
                    $holdings[''][] = $copy;

                    // Are any copies available?
                    if ($copy['availability'] == true) {
                        $any_available = true;
                    }
                }
            }

            // razeni knih
            if ($format[0] == 'Book'){

                // Razeni jednotek:
                uasort($holdings[''], function($a, $b){

                    $poradi = array(
                        "Volný výběr, k vypůjčení" => 1,
                        "Sklad, k vypůjčení" => 2,
                        "Absenčně - 7 dní" => 3,
                        "Absenčně - 14 dní" => 4,
                        "Volný výběr, nepůjčuje se" => 5,
                        "Sklad, nepůjčuje se" => 6,
                        "Jen pro VŠCHT, nepůjčuje se" => 7,
                        "Prezenčně - starý tisk" => 8,
                        "Prezenčně - vzácný tisk" =>9,
                        "Ve zpracování" => 10,
                        "V opravě" => 11,
                        "Soudní vymáhání" => 12,
                        "Trvale vypůjčeno" => 13,
                        "Vyřazeno" => 14,
                        "Pravděp. ztráta" => 15,
                        "Ztráta" => 16,

                    );
                    $vysledek = $poradi[$a['status']] - $poradi[$b['status']];

                    // Kdyz jsou stejne statusy, tak rad podle "pujceno do".
                    if ($vysledek == 0){
                        $date_one = strtotime($a['duedate']);
                        $date_two = strtotime($b['duedate']);

                        // 1 cekajici ve fronte = prodlouzeni data o 1 mesic
                        if (!empty($a['queue'][0])){
                            if (!preg_match_all('/\d/', $a['duedate'])){
                                $a['duedate'] = date('d.m.Y');
                            }
                            $date = new \DateTime($a['duedate']);
                            $date->add(new \DateInterval('P'.$a['queue'][0].'M'));
                            $newdate = $date->format('d-m-Y');
                            $date_one = strtotime($newdate);
                        }
                        if (!empty($b['queue'][0])){
                            if (!preg_match_all('/\d/', $b['duedate'])){
                                $b['duedate'] = date('d.m.Y');
                            }
                            $date = new \DateTime($b['duedate']);
                            $date->add(new \DateInterval('P'.$b['queue'][0].'M'));
                            $newdate = $date->format('d-m-Y');
                            $date_two = strtotime($newdate);
                        }

                        $vysledek = $date_one - $date_two;
                    }

                    return $vysledek;
                });

            }else{
            // razeni vseho ostaniho (casopisy?), podle popisu

                // Razeni jednotek:
                uasort($holdings[''], function($a, $b){

                    $datum1 = preg_match('/([0-9]{4})/',$a['description'], $matches1);
                    $datum2 = preg_match('/([0-9]{4})/',$b['description'], $matches2);
                    $vysledek = (isset($matches2[0])?$matches2[0]:0) - (isset($matches1[0])?$matches1[0]:0);

                    return $vysledek;
                });
            }

            // Are holds allowed?
            $checkHolds = $this->catalog->checkFunction("Holds");

            if ($checkHolds && is_array($holdings)) {
                // Generate Links
                // Loop through each holding
                foreach ($holdings as $location_key => $location) {
                    foreach ($location as $copy_key => $copy) {
                        // Override the default hold behavior with a value from
                        // the ILS driver if allowed and applicable:
                        $currentType
                            = ($holds_override && isset($copy['holdOverride']))
                            ? $copy['holdOverride'] : $type;

                        switch($currentType) {
                        case "all":
                            $addlink = true; // always provide link
                            break;
                        case "holds":
                            $addlink = $copy['availability'];
                            break;
                        case "recalls":
                            $addlink = !$copy['availability'];
                            break;
                        case "availability":
                            $addlink = !$copy['availability']
                                && ($any_available == false);
                            break;
                        default:
                            $addlink = false;
                            break;
                        }
                        // If a valid holdable status has been set, use it to
                        // determine if a hold link is created
                        $addlink = isset($copy['is_holdable'])
                            ? ($addlink && $copy['is_holdable']) : $addlink;

                        if ($addlink) {
                            if ($checkHolds['function'] == "getHoldLink") {
                                /* Build opac link */
                                $holdings[$location_key][$copy_key]['link']
                                    = $this->catalog->getHoldLink(
                                        $copy['id'], $copy
                                    );
                            } else {
                                /* Build non-opac link */
                                $holdings[$location_key][$copy_key]['link']
                                    = $this->getRequestDetails(
                                        $copy, $checkHolds['HMACKeys'], 'Hold'
                                    );
                            }
                        }
                    }
                }
            }
        }
        return $holdings;
    }
}
