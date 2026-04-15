<?php

declare(strict_types=1);

namespace Mht2Html;

use PhpMimeMailParser\Parser;

class Converter
{
    /**
     * Convert an MHT/MHTML string to self-contained HTML.
     *
     * All inline images are replaced with base64 data URIs so the returned
     * HTML string has no external dependencies.
     *
     * @param string $mht Raw MHT/MHTML content.
     * @return string Self-contained HTML.
     */
    public function convertString(string $mht): string
    {
        $parser = new Parser();
        $parser->setText($mht);

        return $this->process($parser);
    }

    /**
     * Convert an MHT/MHTML file to self-contained HTML.
     *
     * @param string $path Path to the .mht / .mhtml file.
     * @return string Self-contained HTML.
     * @throws \InvalidArgumentException When the file cannot be read.
     */
    public function convertFile(string $path): string
    {
        if (!is_readable($path)) {
            throw new \InvalidArgumentException("Cannot read file: $path");
        }

        $parser = new Parser();
        $parser->setPath($path);

        return $this->process($parser);
    }

    /**
     * Run the conversion pipeline on an already-configured Parser instance.
     */
    private function process(Parser $parser): string
    {
        $html = (string) $parser->getMessageBody('html');

        $imageMap = $this->buildImageMap($parser);

        return $this->embedImages($html, $imageMap);
    }

    /**
     * Build a map of content-location (and basename) → base64 data URI
     * for every image attachment found in the parsed message.
     *
     * @return array<string, string>
     */
    private function buildImageMap(Parser $parser): array
    {
        $imageMap = [];

        foreach ($parser->getAttachments(true) as $attachment) {
            $mimeType = $attachment->getContentType();
            if (strpos($mimeType, 'image/') === false) {
                continue;
            }

            $headers = $attachment->getHeaders();
            $contentLocation = isset($headers['content-location'])
                ? trim($headers['content-location'])
                : '';

            if ($contentLocation === '') {
                continue;
            }

            $dataUri = "data:$mimeType;base64," . base64_encode($attachment->getContent());

            $imageMap[$contentLocation]          = $dataUri;
            $imageMap[basename($contentLocation)] = $dataUri;
        }

        return $imageMap;
    }

    /**
     * Replace every non-data-URI src value in $html with the matching
     * base64 data URI from $imageMap.
     *
     * @param array<string, string> $imageMap
     */
    private function embedImages(string $html, array $imageMap): string
    {
        if (empty($imageMap)) {
            return $html;
        }

        // Collect replacements from the *original* HTML to avoid running
        // regex over the (potentially enormous) base64 content.
        $searchReplace = [];
        preg_match_all('/src="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $srcValue) {
            if (strpos($srcValue, 'data:') === 0) {
                continue;
            }

            if (isset($imageMap[$srcValue])) {
                $searchReplace[$srcValue] = $imageMap[$srcValue];
            } elseif (isset($imageMap[basename($srcValue)])) {
                $searchReplace[$srcValue] = $imageMap[basename($srcValue)];
            }
        }

        if ($searchReplace) {
            $html = str_replace(array_keys($searchReplace), array_values($searchReplace), $html);
        }

        return $html;
    }
}
