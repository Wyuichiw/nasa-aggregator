<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;

class ApodMediaUrlExtractor
{
    public static function extract(string $html): ?string
    {
        if (trim($html) === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument();
            $document->loadHTML($html, LIBXML_NONET);

            $xpath = new DOMXPath($document);

            $nodes = $xpath->query(
                '//video/source[@src] | //video[@src] | //iframe[@src]'
            );

            foreach ($nodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }

                $url = trim($node->getAttribute('src'));

                if (str_starts_with($url, '//')) {
                    $url = 'https:' . $url;
                }

                if (filter_var($url, FILTER_VALIDATE_URL)
                    && parse_url($url, PHP_URL_SCHEME) === 'https') {
                    return $url;
                }
            }

            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
