<?php
/**
 * Aleph ILS driver
 *
 * PHP version 5
 *
 * Copyright (C) UB/FU Berlin
 *
 * last update: 7.11.2007
 * tested with X-Server Aleph 18.1.
 *
 * TODO: login, course information, getNewItems, duedate in holdings,
 * https connection to x-server, ...
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
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Christoph Krempe <vufind-tech@lists.sourceforge.net>
 * @author   Alan Rykhus <vufind-tech@lists.sourceforge.net>
 * @author   Jason L. Cooper <vufind-tech@lists.sourceforge.net>
 * @author   Kun Lin <vufind-tech@lists.sourceforge.net>
 * @author   Vaclav Rosecky <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace ntk_module\ILS\Driver;
use VuFind\ILS\Driver\Aleph as AlephBase, VuFind\Exception\ILS as ILSException;

require_once '/var/www/vufind/aleph_tab/AlephTables.php';

/**
 * Aleph Translator Class
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Vaclav Rosecky <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class AlephTranslator
{
    /**
     * Constructor
     *
     * @param array $configArray Aleph configuration
     */
    public function __construct($configArray)
    {

        $this->charset = $configArray['util']['charset'];

        /* Preklad regalu probiha v oddelenem souboru AlephTables.php prizpusobenemu NTK. */
    }

    /**
     * Get a tab40 collection description
     *
     * @param string $collection Collection
     * @param string $sublib     Sub-library
     *
     * @return string
     */
    public function tab40Translate($collection, $sublib)
    {
        //$findme = $collection . "|" . $sublib;
        //$desc = $this->table40[$findme];

        // DM - preklad umisteni probiha v oddelenem souboru AlephTables.php    
        $desc = tab40_translate($collection, $sublib);

        //if ($desc == null) {
        //        $findme = $collection . "|";
        //      $desc = $this->table40[$findme];
        //}
        return $desc;
    }

    /**
     * Apply standard regular expression cleanup to a string.
     *
     * @param string $string String to clean up.
     *
     * @return string
     */
    public static function regexp($string)
    {
        $string = preg_replace("/\\-/", ")\\s(", $string);
        $string = preg_replace("/!/", ".", $string);
        $string = preg_replace("/[<>]/", "", $string);
        $string = "/(" . $string . ")/";
        return $string;
    }
}

/**
 * Aleph ILS driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Christoph Krempe <vufind-tech@lists.sourceforge.net>
 * @author   Alan Rykhus <vufind-tech@lists.sourceforge.net>
 * @author   Jason L. Cooper <vufind-tech@lists.sourceforge.net>
 * @author   Kun Lin <vufind-tech@lists.sourceforge.net>
 * @author   Vaclav Rosecky <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class Aleph extends AlephBase
{
    /* Priznak z pole 998a (je EIZ?).
     * Nastavi se podle jedne (prvni) jednotky, dalsi jednotky se
     * neproveruji.
     */
    protected $eiz = false;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter Date converter
     * @param \VuFind\Cache\Manager  $cacheManager  Cache manager (optional)
     */
    public function __construct(\VuFind\Date\Converter $dateConverter,
        \VuFind\Cache\Manager $cacheManager = null
    ) {
        $this->dateConverter = $dateConverter;
        $this->cacheManager = $cacheManager;
    }

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init()
    {
        // Validate config
        $required = array(
            'host', 'bib', 'useradm', 'admlib', 'dlfport', 'available_statuses'
        );
        foreach ($required as $current) {
            if (!isset($this->config['Catalog'][$current])) {
                throw new ILSException("Missing Catalog/{$current} config setting.");
            }
        }
        if (!isset($this->config['sublibadm'])) {
            throw new ILSException('Missing sublibadm config setting.');
        }

        // Process config
        $this->host = $this->config['Catalog']['host'];
        $this->bib = explode(',', $this->config['Catalog']['bib']);
        $this->useradm = $this->config['Catalog']['useradm'];
        $this->admlib = $this->config['Catalog']['admlib'];
        if (isset($this->config['Catalog']['wwwuser'])
            && isset($this->config['Catalog']['wwwpasswd'])
        ) {
            $this->wwwuser = $this->config['Catalog']['wwwuser'];
            $this->wwwpasswd = $this->config['Catalog']['wwwpasswd'];
            $this->xserver_enabled = true;
        } else {
            $this->xserver_enabled = false;
        }
        $this->dlfport = $this->config['Catalog']['dlfport'];
        $this->sublibadm = $this->config['sublibadm'];
        if (isset($this->config['duedates'])) {
            $this->duedates = $this->config['duedates'];
        }
        $this->available_statuses
            = explode(',', $this->config['Catalog']['available_statuses']);
        $this->quick_availability
            = isset($this->config['Catalog']['quick_availability'])
            ? $this->config['Catalog']['quick_availability'] : false;
        $this->debug_enabled = isset($this->config['Catalog']['debug'])
            ? $this->config['Catalog']['debug'] : false;
        if (isset($this->config['util']['tab40'])
            && isset($this->config['util']['tab15'])
            && isset($this->config['util']['tab_sub_library'])
        ) {
            if (isset($this->config['Cache']['type'])
                && null !== $this->cacheManager
            ) {
                $cache = $this->cacheManager
                    ->getCache($this->config['Cache']['type']);
                $this->translator = $cache->getItem('alephTranslator');
            }
            if ($this->translator == false) {
                $this->translator = new AlephTranslator($this->config);
                if (isset($cache)) {
                    $cache->setItem('alephTranslator', $this->translator);
                }
            }
        }
        if (isset($this->config['Catalog']['preferred_pick_up_locations'])) {
            $this->preferredPickUpLocations = explode(
                ',', $this->config['Catalog']['preferred_pick_up_locations']
            );
        }
        if (isset($this->config['Catalog']['default_patron_id'])) {
            $this->defaultPatronId = $this->config['Catalog']['default_patron_id'];
        }
    }

    /**
     * Perform an XServer request.
     *
     * @param string $op     Operation
     * @param array  $params Parameters
     * @param bool   $auth   Include authentication?
     *
     * @return SimpleXMLElement
     */
    protected function doXRequest($op, $params, $auth=false)
    {
        // DM - chceme XServer presto, ze je disabled - viz. config
        /*if (!$this->xserver_enabled) {
            throw new \Exception(
                'Call to doXRequest without X-Server configuration in Aleph.ini'
            );
        }*/
        $url = "http://$this->host/X?op=$op";
        $url = $this->appendQueryString($url, $params);
        if ($auth) {
            $url = $this->appendQueryString(
                $url, array(
                    'user_name' => $this->wwwuser,
                    'user_password' => $this->wwwpasswd
                )
            );
        }
        $result = $this->doHTTPRequest($url);
        if ($result->error) {
            if ($this->debug_enabled) {
                $this->debug(
                    "XServer error, URL is $url, error message: $result->error."
                );
            }
            throw new ILSException("XServer error: $result->error.");
        }
        return $result;
    }

    /**
     * Perform an HTTP request.
     *
     * @param string $url    URL of request
     * @param string $method HTTP method
     * @param string $body   HTTP body (null for none)
     *
     * @return SimpleXMLElement
     */
    protected function doHTTPRequest($url, $method='GET', $body = null)
    {
        if ($this->debug_enabled) {
            $this->debug("URL: '$url'");
        }

        $result = null;
        try { // DM - pridan parametr timeout=300
            $client = $this->httpService->createClient($url, $method, '300');
//            $client->setMethod($method);
            if ($body != null) {
                $client->setRawBody($body);
            }
            $result = $client->send();
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        if (!$result->isSuccess()) {
            throw new ILSException('HTTP error');
        }
        $answer = $result->getBody();
        if ($this->debug_enabled) {
            $this->debug("url: $url response: $answer");
        }
        $answer = str_replace('xmlns=', 'ns=', $answer);
        $result = simplexml_load_string($answer);
        if (!$result) {
            if ($this->debug_enabled) {
                $this->debug("XML is not valid, URL: $url");
            }
            throw new ILSException(
                "XML is not valid, URL: $url method: $method answer: $answer."
            );
        }
        return $result;
    }

    /**
     * Get Holding - updated by Daniel Marecek - NTK - holdings filters
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null, $filters = array())
    {
        $holding = array();
        list($bib, $sys_no) = $this->parseId($id);
	    $resource = $bib . $sys_no;
        $params = array();
        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                if ($key == 'hide_loans' && $value='true') {
                    $params['loaned'] = 'NO';
                } else {
                    $params[$key] = $value;
                }
            }
	    }
	    $params['view'] = 'full';
        if (!empty($patron['id'])) {
            $params['patron'] = $patron['id'];
        } else if (isset($this->defaultPatronId)) {
            $params['patron'] = $this->defaultPatronId;
        }
        $xml = $this->doRestDLFRequest(array('record', $resource, 'items'), $params);
        if (isset($xml->{'items'})) {
            foreach ($xml->{'items'}->{'item'} as $item) {
                $item_status = (string)$item->{'z30-item-status-code'}; // $isc
                // $ipsc:
                $item_process_status = (string)$item->{'z30-item-process-status-code'};
                $sub_library_code = (string)$item->{'z30-sub-library-code'}; // $slc
                $z30 = $item->z30;
                //
                // DM - status jednotky + dilci knihovna
                //
                if ($this->translator) {
                    // DM - preklad ze souboru AlephTables.php funkci tab15_translate - upraveno podle NTK
                    $item_status = tab15_translate($sub_library_code, $item_status, $item_process_status);
                } else {
                    $item_status = array(
                        'opac' => 'Y',
                        'request' => 'C',
                        'desc' => (string)$z30->{'z30-item-status'},
                        'sub_lib_desc' => (string)$z30->{'z30-sub-library'}
                    );
                }
                if ($item_status['opac'] != 'Y') {
                    continue;
                }
                $availability = false;
                $reserve = ($item_status['request'] == 'C') ? 'N' : 'Y';
                //
                // DM - umisteni jednotky
                //
                // preklad ze souboru AlephTables.php funkci tab40_translate - upraveno podle NTK
                //
                $collection = (string)$z30->{'z30-collection'}; // umisteni jednotky anglicky
                $collection_desc = array('desc' => $collection); // umisteni jednotky cesky
                $collection_code = (string)$item->{'z30-collection-code'};
                if ($this->translator) {
                    $collection_desc = $this->translator->tab40Translate(
                        $collection_code, $sub_library_code
                    );
                }
                //
                // DM - preklad umisteni titulu - ve vysledcich vyhledavani
                // - presunuto do AjaxController.php
                // - posila se tam odsud collection_code
                $requested = false;
                $duedate = '';
                $addLink = false;
                $status = (string)$item->{'status'};

                if ((in_array($status, $this->available_statuses)) && !($z30->{'z30-item-status'} == 'Permanently loaned')) {
                    $availability = true;
                }

                if ($item_status['request'] == 'Y' && $availability == false) {
                    $addLink = true;
                }
                if (!empty($patron)) {
                    $hold_request = $item->xpath('info[@type="HoldRequest"]/@allowed');
                    $addLink = ($hold_request[0] == 'Y');
                }
                // DM - zakomentovano podle mzk $reserve = 'N';
                if ($item_status['request'] == 'Y' && $availability == false) {
                    $reserve = 'N'; // DM - zmeneno z Y na N podle mzk
                }

                $matches = array();

                /*DM - zmena regu.vyrazu v prvnich dvouch vetvich - pro parsovani data pujeceno do*/
                /*DM - dalsi zmena - jeli On Shelf, tak neni treba zobrazovat - hodno by bylo zjistit vsechny mozny stavy z alephu*/
                if (preg_match("/([0-9]*\/[0-9]*\/[0-9]*);([a-zA-Z ]*)/", $status, $matches)) {
                    $duedate = $this->parseDate($matches[1]);
                    $requested = (trim($matches[2]) == "Requested");
                } else if (preg_match("/([0-9]*\/[0-9]*\/[0-9]*)/", $status, $matches)) {
                    $duedate = $this->parseDate($matches[1]);
                } else if (preg_match("/^(\d+\/?){3}$/", $status, $matches)) {
                    $duedate = $this->parseDate($status);
                } else if ($status == "On Shelf") {
                    $duedate = null;
                } else {
                    $duedate = $status; /*DM - zmena z null na $status - ztracela se informace*/
                }

                // process duedate
                if ($availability) {
                    if ($this->duedates) {
                        foreach ($this->duedates as $key => $value) {
                            if (preg_match($value, $item_status['desc'])) {
                                $duedate = $key;
                                break;
                            }
                        }
                    } else {
                        $duedate = $item_status['desc'];
                    }
                } else {
                    if ($status == "On Hold" || $status == "Requested") {
                        $duedate = "requested";
                    }
                }
                $item_id = $item->attributes()->href;
                $item_id = substr($item_id, strrpos($item_id, '/') + 1);
                $note = (string)$z30->{'z30-note-opac'};

                // DM - prohledavani pole 998a pres XServer se deje pro prvni jednotku - jde o bib zaznam, ktery je pro vsechny jednotky stejny
                if ($this->eiz == null) {
                    // DM - nacteni pole 998a
                    $parametrs = array('base' => 'STK01', 'doc_num' => $id);
                    $xml_eiz = $this->doXRequest('find-doc', $parametrs);
                    $is_eiz = $xml_eiz->xpath("//varfield[@id='998']/subfield[@label='a']");
                    $is_eiz_string = empty($is_eiz[0]) ? "" : (string)$is_eiz[0];
                    if ($is_eiz_string == 'eiz') {
                        $this->eiz = 1;
                    } else {
                        $this->eiz = 2;
                    }
                }
                $queue = (string)$item->{'queue'};
                $holding[] = array(
                    'id' => $id,
                    'item_id' => $item_id,
                    'availability' => $availability,
                    'status' => (string)$item_status['desc'], // status jednotky
                    'location' => $collection_code,//$location, // umisteni titulu - ve vysledcich vyhledavani
                    //'reserve'           => 'N',
                    'callnumber' => (string)$z30->{'z30-call-no'},
                    'duedate' => (string)$duedate,
                    'number' => (string)$z30->{'z30-inventory-number'},
                    'barcode' => (string)$z30->{'z30-barcode'},
                    'description' => (string)$z30->{'z30-description'},
                    'notes' => ($note == null) ? null : array($note),
                    'is_holdable' => ($reserve == 'N') ? true : false,
                    'addLink' => $addLink,
                    'holdtype' => 'hold',
                    /* below are optional attributes*/
                    'collection' => (string)$collection, // umisteni jednotky anglicky
                    'collection_desc' => isset($collection_desc['desc']) ? (string)$collection_desc['desc'] : (string)$collection, // umisteni jednoty cesky
                    'callnumber_second' => (string)$z30->{'z30-call-no-2'},
                    'sub_lib_desc' => (string)$item_status['sub_lib_desc'], // dilci kihovna
                    'requested' => (string)$requested,
                    'queue' => (string)$queue,
                    'tooltip' => isset($item_status['tooltip']) ? (string)$item_status['tooltip'] : null,
                    'tooltip-vscht' => isset($collection_desc['tooltip']) ? (string)$collection_desc['tooltip'] : null,
                    'eiz' => $this->eiz,
                );
            }
        }
        return $holding;
    }

    /**
     * Filter holdings
     *
     * Daniel Mareček
     * NTK - 10/2015
     *
     * inspired by MZK
     *
     */
    public function getHoldingFilters($bibId)
    {
        list($bib, $sys_no) = $this->parseId($bibId);
            $resource = $bib . $sys_no;
            $years = array();
        $volumes = array();
        try {
            $xml = $this->doRestDLFRequest(array('record', $resource, 'filters')); // Aleph API provides filters
        } catch (Exception $ex) {
            return array();
        }
        if (isset($xml->{'record-filters'})) {
            if (isset($xml->{'record-filters'}->{'years'})) {
                foreach ($xml->{'record-filters'}->{'years'}->{'year'} as $year) {
                    $years[] = $year;
                }
            }
            if (isset($xml->{'record-filters'}->{'volumes'})) {
                foreach ($xml->{'record-filters'}->{'volumes'}->{'volume'} as $volume) {
                    $volumes[] = $volume;
                }
            }
        }
        return array('year' => $years, 'volume' => $volumes, 'hide_loans' => array(true, false));
    }

    /**
     * Get Patron Transaction History
     *
     * @param array $user The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array      Array of the patron's transactions on success.
     */
    public function getMyHistory($user, $limit = 0)
    {
        return $this->getMyTransactions($user, true, $limit);
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $user    The patron array from patronLogin
     * @param bool  $history Include history of transactions (true) or just get
     * current ones (false).
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($user, $history=false, $limit = 0)
    {
        $userId = $user['id'];
        $transList = array();
        $params = array("view" => "full");
        if ($history) {
            $params["type"] = "history";
            if ($limit > 0) {
                $params["no_loans"] = $limit;
            }
        }
        $xml = $this->doRestDLFRequest(
            array('patron', $userId, 'circulationActions', 'loans'), $params
        );
        foreach ($xml->xpath('//loan') as $item) {
            $z36 = $item->z36;
            $z13 = $item->z13;
            $z30 = $item->z30;
            $group = $item->xpath('@href');
            $group = substr(strrchr($group[0], "/"), 1);
            $renew = $item->xpath('@renew'); // DM -
            $renew_info = $item->xpath('renew-info');//echo($renew_loan[0]);
            //$docno = (string) $z36->{'z36-doc-number'};
            //$itemseq = (string) $z36->{'z36-item-sequence'};
            //$seq = (string) $z36->{'z36-sequence'};
            $location = (string) $z36->{'z36_pickup_location'};
            $reqnum = (string) $z36->{'z36-doc-number'}
                . (string) $z36->{'z36-item-sequence'}
                . (string) $z36->{'z36-sequence'};
            $due = $returned = null;
            if ($history) {
                $due = $item->z36h->{'z36h-due-date'};
                $returned = $item->z36h->{'z36h-returned-date'};
            } else {
                $due = (string) $z36->{'z36-due-date'};
            }
            //$loaned = (string) $z36->{'z36-loan-date'};
            $title = (string) $z13->{'z13-title'};
            $author = (string) $z13->{'z13-author'};
            $isbn = (string) $z13->{'z13-isbn-issn'};
            $barcode = (string) $z30->{'z30-barcode'};
            $no_renewal = $z36->{'z36-no-renewal'}; // DM - pocet provedenych prodlouzeni vypujcky (X/2)
            $nothing_renew = null; // DM - priznak pro vcas nevracenou jednotku
            date_default_timezone_set('UTC'); // DM - nastaveni casove zony
            $today_date = date("Ymd"); // DM - aktualni dnesni datum
            if ($due < $today_date){
                $nothing_renew = 'yes';
            }
            $loan_date = $z36->{'z36-loan-date'}; // DM - datum vypujcky
            $last_renew_date = $z36->{'z36-last-renew-date'}; // DM - datum posledniho prodlouzeni vypujcky
            // DM - je-li vypujcka nebo prodlouzeni provedeno dnes - nastav priznak a nasledne vypis status - dnes jiz nejde prodlouzit
            if (($loan_date == $today_date) || ($last_renew_date == $today_date)){
                $dueStatus = 'due_today';
            }else{
                $dueStatus='';
            }
            if (!$history) {
                $result_for_requested = $this->getHolding($this->barcodeToID($barcode)); // DM - pocet cekajicich ve fronte
                $callnumber = $z30->{'z30-call-no'}; // DM - signatura pro identifikaci presne jednotky, u ktere zjistujeme pocet cekajicich ve fronte
                if (empty($result_for_requested)) {
                    $no_requested = 'Jednotka není zaindexována ve VuFindu.';
                } else {
                    $i = 0;
                    while ($result_for_requested[$i]['callnumber'] != $callnumber && $i < sizeof($result_for_requested)) {
                        $i++;
                    }
                    // DM - pocet cekajicich ve fronte
                    preg_match('/\d+/',$result_for_requested[$i]['queue'],$matches);
                    $no_requested = isset($matches[0])?$matches[0]:null;
                }
            }
            $transList[] = array(
                //'type' => $type,
                'id' => $this->barcodeToID($barcode),
                'item_id' => $group,
                'location' => $location,
                'title' => $title,
                'author' => $author,
                'isbn' => array($isbn),
                'reqnum' => $reqnum,
                'barcode' => $barcode,
                'duedate' => $this->parseDate($due),
                'returned' => $this->parseDate($returned),
                //'holddate' => $holddate,
                //'delete' => $delete,
                'renewable' => true,
                //'create' => $this->parseDate($create)
                'no_requested' => isset($no_requested) ? $no_requested : null, // DM - pocet cekajicich ve fronte
                'nothing_renew' => $nothing_renew, // DM - priznak pro vcas nevracenou jednotku
                'dueStatus' => $dueStatus, // DM - priznak pro vypujcku provedenou dnes
                'no_renewal' => $no_renewal, // DM - predavani poctu moznych prodlouzeni - <0,2>
                'renew' => $renew, // DM - lze vypujcku prodluzovat (obecne)? Y
                'last_renew_date' => $this->parseDate($last_renew_date), // DM - datum posledniho prodlouzeni
            );
        }
        return $transList;
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array      Array of the patron's holds on success.
     */
    public function getMyHolds($user)
    {

        $userId = $user['id'];
        $holdList = array();
        $xml = $this->doRestDLFRequest(
            array('patron', $userId, 'circulationActions', 'requests', 'holds'),
            array('view' => 'full')
        );
        foreach ($xml->xpath('//hold-request') as $item) {
            $order = null;
            $z37 = $item->z37;          
            switch ($z37->{'z37-status'}){
                case 'Waiting in queue':
                    $order = preg_match('/\d+/',$item->status,$matches);
                    $order = $matches[0];
                    break;
                case 'In process':
                    $status= 'vyřizuje se';       
                    break;
                default:
                    $status= 'k vyzvednutí do';
                    $endholddate= $this->parseDate($z37->{'z37-end-hold-date'});
            }

            $z13 = $item->z13;
            $z30 = $item->z30;
            $delete = $item->xpath('@delete');
            $href = $item->xpath('@href');
            $item_id = substr($href[0], strrpos($href[0], '/') + 1);
            if ((string) $z37->{'z37-request-type'} == "Hold Request" || true) {
                $type = "hold";
                //$docno = (string) $z37->{'z37-doc-number'};
                //$itemseq = (string) $z37->{'z37-item-sequence'};
                $seq = (string) $z37->{'z37-sequence'};
                $location = (string) $z37->{'z37-pickup-location'};
                $reqnum = (string) $z37->{'z37-doc-number'}
                    . (string) $z37->{'z37-item-sequence'}
                    . (string) $z37->{'z37-sequence'};
                $expire = (string) $z37->{'z37-end-request-date'};
                $create = (string) $z37->{'z37-open-date'};
                $holddate = (string) $z37->{'z37-hold-date'};
                $shelfnumber = substr($z37->{'z37-request-number'},-3);
                $title = (string) $z13->{'z13-title'};
                $author = (string) $z13->{'z13-author'};
                $isbn = (string) $z13->{'z13-isbn-issn'};
                $barcode = (string) $z30->{'z30-barcode'};
                if ($holddate == "00000000") {
                    $holddate = null;
                } else {
                    $holddate = $this->parseDate($holddate);
                }
                $delete = ($delete[0] == "Y");
                $holdList[] = array(
                    'type' => $type,
                    'item_id' => $item_id,
                    'location' => $location,
                    'title' => $title,
                    'author' => $author,
                    'isbn' => array($isbn),
                    'reqnum' => $reqnum,
                    'barcode' => $barcode,
                    //'id' => (string) $z13->{'z13-doc-number'}, // DM - id se bere primo z xml od Alephu //$this->barcodeToID($barcode),
                    'id' => $this->barcodeToID($barcode),
                    'expire' => $this->parseDate($expire),
                    'holddate' => $holddate,
                    'delete' => $delete,
                    'create' => $this->parseDate($create),
                    'status' => isset($status)?$status:null,
                    'position' => $order,
                    'endholddate' => isset($endholddate)?$endholddate:null,
                    'shelfnumber' => $shelfnumber,
                );
            }
        }
        return $holdList;
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return mixed      Array of the patron's fines on success.
     */
    public function getMyFines($user)
    {
        $finesList = array();
        $finesListSort = array();

        $xml = $this->doRestDLFRequest(
            array('patron', $user['id'], 'circulationActions', 'cash'),
            array("view" => "full")
        );

        foreach ($xml->xpath('//cash') as $item) {
            $z31 = $item->z31;
            $z13 = $item->z13;
            $z30 = $item->z30;
            $delete = $item->xpath('@delete');
            $title = (string) $z13->{'z13-title'};
            $transactiondate = date('d-m-Y', strtotime((string) $z31->{'z31-date'}));
            $transactiontype = (string) $z31->{'z31-credit-debit'};
            $id = (string) $z13->{'z13-doc-number'};
            $barcode = (string) $z30->{'z30-barcode'};
            $checkout = (string) $z31->{'z31-date'};
            $duedate = (string) $z31->{'z31-key'};
            $duedate = substr($duedate, 58, 8);
            $duedate = !empty($duedate) ? $this->parseDate($duedate) : null;
            // DM - zakomentovano, delalo neplechu pri testovaci pokute za nic
            // $id = $this->barcodeToID($barcode);
            if ($transactiontype=="Debit") {
                $mult=-100;
            } elseif ($transactiontype=="Credit") {
                $mult=100;
            }
            $amount
                = (float)(preg_replace("/[\(\)]/", "", (string) $z31->{'z31-sum'}))
                * $mult;
            $cashref = (string) $z31->{'z31-sequence'};
            $cashdate = date('d-m-Y', strtotime((string) $z31->{'z31-date'}));
            $balance = 0;

            $finesListSort["$cashref"]  = array(
                    "title"   => $title,
                    "barcode" => $barcode,
                    "amount" => $amount,
                    "transactiondate" => $transactiondate,
                    "transactiontype" => $transactiontype,
                    "checkout" => $this->parseDate($checkout),
                    "id"  => $id,
                    "duedate" => $duedate
            );
        }
        ksort($finesListSort);
        foreach ($finesListSort as $key => $value) {
            $title = $finesListSort[$key]["title"];
            $barcode = $finesListSort[$key]["barcode"];
            $amount = $finesListSort[$key]["amount"];
            $checkout = $finesListSort[$key]["checkout"];
            $transactiondate = $finesListSort[$key]["transactiondate"];
            $transactiontype = $finesListSort[$key]["transactiontype"];
            $id = $finesListSort[$key]["id"];
            $duedate = $finesListSort[$key]["duedate"];
            $finesList[] = array(
                "title"   => $title,
                "barcode"  => $barcode,
                "amount"   => $amount,
                "transactiondate" => $transactiondate,
                "transactiontype" => $transactiontype,
                "checkout" => $checkout,
                "id"  => $id,
                "duedate"  => $duedate,
                "printLink" => "test",
            );
        }
        return $finesList;
    }

    /**
     * Get profile information using DLF service.
     *
     * @param array $user The patron array
     *
     * @throws ILSException
     * @return array      Array of the patron's profile data on success.
     */
    public function getMyProfileDLF($user)
    {
        $xml = $this->doRestDLFRequest(
            array('patron', $user['id'], 'patronInformation', 'address')
        );
        $address = $xml->xpath('//address-information');
        $address = $address[0];
        $address1 = (string)$address->{'z304-address-1'}; // DM - Prijmeni Jmeno
        $address2 = (string)$address->{'z304-address-2'}; // DM - Ulice c.p
        $address3 = (string)$address->{'z304-address-3'}; // DM - PSC Mesto
        $address4 = (string)$address->{'z304-address-4'}; // DM - Zeme
        $address5 = (string)$address->{'z304-address-5'}; // DM - Ulice c.p
        $zip = (string)$address->{'z304-zip'};
        $phone = (string)$address->{'z304-telephone-1'};
        $email = (string)$address->{'z304-email-address'};
        $dateFrom = (string)$address->{'z304-date-from'};
        $dateTo = (string)$address->{'z304-date-to'};
        if (strpos($address1, ",") === false) {
        // $recordList['lastname'] = $address1;
        // $recordList['firstname'] = $address1;
            list($recordList['lastname'], $recordList['firstname'])
                    = explode(" ", $address1);
        } else {
            list($recordList['lastname'], $recordList['firstname'])
                = explode(",", $address2);
        }
        $recordList['address1'] = $address2;
        $recordList['address2'] = $address3;
        $recordList['barcode'] = $address1;
        $recordList['zip'] = $zip;
        $recordList['phone'] = $phone;
        $recordList['email'] = $email;
        $recordList['dateFrom'] = $dateFrom;
        $recordList['dateTo'] = $this->parseDate($dateTo);
        $recordList['id'] = $user['id'];
        $xml = $this->doRestDLFRequest(
            array('patron', $user['id'], 'patronStatus', 'registration')
        );
        $status = $xml->xpath("//institution/z305-bor-status");
        $expiry = $xml->xpath("//institution/z305-expiry-date");
        $recordList['expire'] = $this->parseDate($expiry[0]);
        $recordList['group'] = $status[0];//print_r($status[0]);
        return $recordList;
    }

    /**
     * Support method for placeHold -- get holding info for an item.
     *
     * @param string $patronId Patron ID
     * @param string $id       Bib ID
     * @param string $group    Item ID
     *
     * @return array
     */
    public function getHoldingInfoForItem($patronId, $id, $group)
    {
        list($bib, $sys_no) = $this->parseId($id);
        $resource = $bib . $sys_no;
        $xml = $this->doRestDLFRequest(
            array('patron', $patronId, 'record', $resource, 'items', $group)
        );
        $locations = array();
        $part = $xml->xpath('//pickup-locations');// DM - misto vypujceni print_r($part);
        if ($part) {
            foreach ($part[0]->children() as $node) {
                $arr = $node->attributes();
                $code = (string) $arr['code'];
                $loc_name = (string) $node;
                $locations[$code] = $loc_name;
            }
        } else {
            //throw new ILSException('No pickup locations');
            // DM - kdyz neni pickup location - nelze rezervovat - hlaska (nutno zobrazit v sablone)
            $fault = $xml->xpath('//note');
            $fault_info = (string) $fault[0];
            return array(
                'fault_info' => $fault_info
            );
        }
        $requests = 0;
        $str = $xml->xpath('//item/queue/text()');
        if ($str != null) {
            list($requests, $other) = explode(' ', trim($str[0]));
        }
        $date = $xml->xpath('//last-interest-date/text()');
        $date = $date[0];
        // DM - prevod data do ceskeho formatu dd.mm.yyyy
        $date = "" . substr($date, 6, 2) . "." . substr($date, 4, 2) . "."
            . substr($date, 0, 4);
        return array(
            'pickup-locations' => $locations, 'last-interest-date' => $date,
            'order' => $requests + 1
        );
    }

    /**
     * Get Default "Hold Required By" Date (as Unix timestamp) or null if unsupported
     *
     * @param array $patron   Patron information returned by the patronLogin method.
     * @param array $holdInfo Contains most of the same values passed to
     * placeHold, minus the patron data.
     *
     * @return int
     */
    public function getHoldDefaultRequiredDate($patron, $holdInfo)
    {
        if ($holdInfo != null) {
            $details = $this->getHoldingInfoForItem(
                $patron['id'], $holdInfo['id'], $holdInfo['item_id']
            );
        }
        if (isset($details['last-interest-date'])) {
            try {
                // DM - netreba konvertovat, uz je ve spravnem formatu
//                return $this->dateConverter
//                    ->convert('d.m.Y', 'U', $details['last-interest-date']);
                return $details['last-interest-date'];
            } catch (DateException $e) {
                // If we couldn't convert the date, fail gracefully.
                $this->debug(
                    'Could not convert date: ' . $details['last-interest-date']
                );
            }
        }
        return null;
    }

    /**
     * Convert a barcode to an item ID.
     *
     * @param string $bar Barcode
     *
     * @return string
     */
    public function barcodeToID($bar)
    {
        // DM - chceme XServer presto, ze je disabled - viz. config
        /*if (!$this->xserver_enabled) {
            return null;
        }*/
        foreach ($this->bib as $base) {
            try {
                $xml = $this->doXRequest(
                    "find", array("base" => $base, "request" => "BAR=$bar"), false
                );
                $docs = (int) $xml->{"no_records"};
                if ($docs == 1) {
                    $set = (string) $xml->{"set_number"};
                    $result = $this->doXRequest(
                        "present", array("set_number" => $set, "set_entry" => "1"),
                        false
                    );
                    $id = $result->xpath('//doc_number/text()');
                    if (count($this->bib)==1) {
                        return $id[0];
                    } else {
                        return $base . "-" . $id[0];
                    }
                }
            } catch (\Exception $ex) {
            }
        }
        return 'jednotka neni v katalogu';
    }

    /**
     * DM - funkce z puvodniho VuFindu 1.X
     *
     * upravena na podminky NTK - Aleph posila ojedinely format datumu
     */
    function parseDate($date) {
       if (preg_match("/^[0-9]{8}$/", $date) === 1) {
           return substr($date, 6, 2) . "." .substr($date, 4, 2) . "." . substr($date, 0, 4);
        } else {
           if (isset($date)){
               list($day, $month, $year) = explode("/", $date);
               if (!is_numeric($month)) {
                   $translate_month = array('jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
                       'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12);
                   $month = isset($month) ? $translate_month[strtolower($month)] : null;
               }
               $day = ltrim($day, "0");
               $month = ltrim($month, "0");

               //MJ. aleph nekde pouziva jen 2 znaky pro oznaceni roku.. hack bude zrejme fungovat do roku 2100, pokud se vyznamne neprodlouzi vypujcni lhuty
               if ($year < 100) {
                   $year = "20" . $year;
               }
               return $day . "." . $month . "." . $year;
           }else{
               return false;
           }
        }
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible for gettting a list of valid library locations for
     * holds / recall retrieval
     *
     * @param array $patron   Patron information returned by the patronLogin method.
     * @param array $holdInfo Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.  The driver must not add new options to the return array
     * based on this data or other areas of VuFind may behave incorrectly.
     *
     * @throws ILSException
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     */
    public function getPickUpLocations($patron, $holdInfo=null)
    {
        if ($holdInfo != null) {
            $details = $this->getHoldingInfoForItem(
                $patron['id'], $holdInfo['id'], $holdInfo['item_id']
            );
            $pickupLocations = array();
            // DM - pickup-locations vracene v $details jsou prazdne - poslano info
            if (!empty($details['fault_info'])){
                return $details['fault_info'];
            }else {
                foreach ($details['pickup-locations'] as $key => $value) {
                    $pickupLocations[] = array(
                        "locationID" => $key, "locationDisplay" => $value
                    );
                }
            }
            return $pickupLocations;
        } else {
            $default = $this->getDefaultPickUpLocation($patron);
            return empty($default) ? array() : array($default);
        }
    }
}
