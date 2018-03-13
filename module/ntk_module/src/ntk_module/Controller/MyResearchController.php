<?php

namespace ntk_module\Controller;

class MyResearchController extends \VuFind\Controller\MyResearchController
{
    /** 
     * History of checked out items Action
     *
     * @return mixed
     */
    public function checkedOutHistoryAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
                return $patron;
        }

        $currentLimit = $this->params()->fromQuery('limit');
        if (!isset($currentLimit)) {
            $currentLimit = 0;
        }

        // Connect to the ILS:
        $catalog = $this->getILS();

        // Get history:
        $result = $catalog->getMyHistory($patron, $currentLimit);

        $transactions = array();
        foreach ($result as $current) {
                // Add renewal details if appropriate:
                $current = $this->renewals()->addRenewDetails(
                        $catalog, $current, isset($renewStatus) ? $renewStatus : null
                );
                // Build record driver:
                $transactions[] = $this->getDriverForILSRecord($current);
        }

        return $this->createViewModel(
                array('transactions' => $transactions)
        );
    }
}

