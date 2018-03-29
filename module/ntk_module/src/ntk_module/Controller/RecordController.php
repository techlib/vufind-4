<?php

namespace ntk_module\Controller;
use VuFind\Controller\RecordController as RecordControllerBase;

/**
 * Record Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class RecordController extends RecordControllerBase
{
    use HoldsTrait;
}
