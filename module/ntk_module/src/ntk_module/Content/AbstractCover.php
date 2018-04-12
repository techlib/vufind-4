<?php
namespace ntk_module\Content;
use VuFind\Content\AbstractCover as AbstractCoverBase;

abstract class AbstractCover extends AbstractCoverBase
{
    /**
     * Does this plugin support UID numbers?
     *
     * @var bool
     */
    protected $supportsUid = true;

    /**
     * Does this plugin support the provided ID array?
     *
     * @param array $ids IDs that will later be sent to load() -- see below.
     *
     * @return bool
     */
    public function supports($ids)
    {
        return
            ($this->supportsIsbn && isset($ids['isbn']))
            || ($this->supportsIssn && isset($ids['issn']))
            || ($this->supportsOclc && isset($ids['oclc']))
            || ($this->supportsUpc && isset($ids['upc']))
            || ($this->supportsUid && isset($ids['recordid']));
    }
}
