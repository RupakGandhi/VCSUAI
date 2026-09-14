<?php

namespace App\Console\Commands\Concerns;

/**
 * Shared by any command that needs to pull a `var|const NAME = { ... };`
 * object literal out of the raw index.html source and decode it as JSON.
 * Originally written for VcsuImportHtml; extracted here so
 * VcsuBackfillSimulationFields can reuse the exact same parsing logic
 * without duplicating the brace-counting code.
 */
trait ExtractsJsDataObjects
{
    /**
     * Find `var|const NAME = { ... };` in the raw HTML source and decode the
     * object literal as JSON. Uses a string-aware brace counter (mirrors the
     * approach the file's own cmsExportFile() JS function already uses to
     * find/replace these same objects) rather than a fixed-size regex, since
     * the objects are too large and deeply nested for a simple pattern.
     */
    private function extractJsObject(string $html, string $varName): array
    {
        // There can be multiple textual matches for "var X = {" / "const X = {"
        // in this file: the real declaration, AND references to that same
        // string *inside* the export function's own JS source (which builds
        // and searches for these declarations as text). So: try every
        // candidate start position and keep whichever one actually decodes
        // as valid JSON and is the largest (the real data object is always
        // far bigger than any embedded reference string).
        $starts = [];
        foreach (["var {$varName} = {", "const {$varName} = {"] as $needle) {
            $offset = 0;
            while (($pos = strpos($html, $needle, $offset)) !== false) {
                $starts[] = $pos;
                $offset = $pos + 1;
            }
        }

        if (empty($starts)) {
            throw new \RuntimeException("Could not find {$varName} in source file");
        }

        $best = null;
        $bestLen = 0;

        foreach ($starts as $start) {
            $braceStart = strpos($html, '{', $start);
            $jsonStr = $this->extractBalancedJson($html, $braceStart);
            if ($jsonStr === null) {
                continue;
            }

            $decoded = json_decode($jsonStr, true);
            if ($decoded !== null && strlen($jsonStr) > $bestLen) {
                $best = $decoded;
                $bestLen = strlen($jsonStr);
            }
        }

        if ($best === null) {
            throw new \RuntimeException("Could not extract valid JSON for {$varName}");
        }

        return $best;
    }

    /** String-aware brace counter: returns the substring from $braceStart to its matching closing brace, or null if unbalanced. */
    private function extractBalancedJson(string $html, int $braceStart): ?string
    {
        $depth = 0;
        $inString = false;
        $escaped = false;
        $len = strlen($html);

        for ($i = $braceStart; $i < $len; $i++) {
            $c = $html[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($c === '\\') {
                    $escaped = true;
                } elseif ($c === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($c === '"') {
                $inString = true;
            } elseif ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($html, $braceStart, $i - $braceStart + 1);
                }
            }
        }

        return null;
    }
}
