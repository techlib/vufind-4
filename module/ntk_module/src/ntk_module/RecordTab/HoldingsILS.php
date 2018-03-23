<?php

namespace ntk_module\RecordTab;
use VuFind\RecordTab\HoldingsILS as HoldingsILSBase;

class HoldingsILS extends HoldingsILSBase
{
    /*
     * Support functions for holding filters
     * Daniel Marecek, NTK
     *
     */
    public function getFilters()
    {
	    if (!isset($this->filters)) {
		    $this->filters = $this->driver->getHoldingFilters();
	    }
	    return $this->filters;
    }

    public function getSelectedFilters()
    {
	    $filters = array();
	    foreach ($this->getAvailableFilters() as $name => $type) {
		    $filterValue = $this->getRequest()->getQuery($name);
		    if ($filterValue != null || !empty($filterValue)) {
			    $filters[$name] = $filterValue;
		    }
	    }
	    return $filters;
    }

    public function getAvailableFilters()
    {
	    if (!isset($this->availableFilters)) {
		    $this->availableFilters = $this->driver->getAvailableHoldingFilters();
	    }
	    return $this->availableFilters;
    }

    public function getRealTimeHoldings()
    {
	    return $this->driver->getRealTimeHoldings($this->getSelectedFilters());
    }

    public function asHiddenFields($field)
    {
	    $allFilters = $this->getAvailableFilters();
	    $filtersToKeep = isset($allFilters[$field]['keep']) ? $allFilters[$field]['keep'] : array();
	    $selectedFilters = $this->getSelectedFilters();
	    $result = '';
	    foreach ($filtersToKeep as $filterToKeep) {
		    if (isset($selectedFilters[$filterToKeep])) {
			    $value = $selectedFilters[$filterToKeep];
			    $result .= '<input type="hidden" name="' .
				    htmlspecialchars($filterToKeep) . '" value="' .
				    htmlspecialchars($value) . '" />';
		    }
	    }
	    return $result;
    }
}
