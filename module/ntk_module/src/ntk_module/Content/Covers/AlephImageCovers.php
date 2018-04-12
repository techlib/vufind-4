<?php

namespace ntk_module\Content\Covers;

class AlephImageCovers extends \ntk_module\Content\AbstractCover
{

    /**
     * Constructor
     */
    public function __construct()
    {
           $this->supportsIsbn = $this->cacheAllowed = true;
    }

    /**
     * Get image URL for a particular API key and set of IDs (or false if invalid).
     *
     * @param string $key  API key
     * @param string $size Size of image to load (small/medium/large)
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object and 'issn' pointing to a string)
     *
     * @return string|bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getUrl($key, $size, $ids)
    {
        if (!isset($ids['recordid'])) {
            return false;
        }
        $url = 'http://aleph.techlib.cz/cgi-bin/image.pl?size=big&sn='. $ids['recordid'];

        return $url;
    }
}

