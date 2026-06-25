<?php
/**
 * Lightweight XLSX reader used by Saeed Keyword Research feature.
 * Based on the structure of SimpleXLSX (MIT License by Sergey Shuchkin).
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
if (!class_exists('SimpleXLSX')) {
    class SimpleXLSX {
        /**
         * @var array<int, array<int, string>>
         */
        private $rows = array();

        /**
         * @var string|null
         */
        private $error = null;

        /**
         * Parse file and return instance or false on failure.
         *
         * @param string $filename
         * @return self|false
         */
        public static function parse($filename) {
            $xlsx = new self();
            return $xlsx->parseFile($filename) ? $xlsx : false;
        }

        /**
         * Return parsed rows.
         *
         * @return array<int, array<int, string>>
         */
        public function rows() {
            return $this->rows;
        }

        /**
         * Return last error message.
         *
         * @return string|null
         */
        public function error() {
            return $this->error;
        }

        /**
         * Parse XLSX contents.
         *
         * @param string $filename
         * @return bool
         */
        private function parseFile($filename) {
            if (!class_exists('ZipArchive')) {
                $this->error = 'ZipArchive not available';
                return false;
            }

            $zip = new ZipArchive();
            if (true !== $zip->open($filename)) {
                $this->error = 'Unable to open XLSX file';
                return false;
            }

            $sharedStrings = $this->readSharedStrings($zip);
            $sheetPath     = $this->resolveFirstSheetPath($zip);

            if (!$sheetPath) {
                $this->error = 'Worksheet not found in XLSX file';
                $zip->close();
                return false;
            }

            $sheetXml = $zip->getFromName($sheetPath);
            $zip->close();

            if ($sheetXml === false) {
                $this->error = 'Unable to read worksheet data';
                return false;
            }

            $sheet = @simplexml_load_string($sheetXml);
            if (!$sheet) {
                $this->error = 'Invalid worksheet XML';
                return false;
            }

            $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

            foreach ($sheet->xpath('//s:sheetData/s:row') as $row) {
                $cells      = array();
                $maxIndex   = -1;

                foreach ($row->c as $cell) {
                    $ref    = (string) $cell['r'];
                    $colIdx = $this->columnIndexFromCellRef($ref);
                    if ($colIdx > $maxIndex) {
                        $maxIndex = $colIdx;
                    }

                    $cells[$colIdx] = $this->parseCellValue($cell, $sharedStrings);
                }

                if ($maxIndex < 0) {
                    $this->rows[] = array();
                    continue;
                }

                $rowValues = array();
                for ($i = 0; $i <= $maxIndex; $i++) {
                    $rowValues[] = isset($cells[$i]) ? $cells[$i] : '';
                }

                $this->rows[] = $rowValues;
            }

            return true;
        }
        /**
         * Convert cell reference (e.g. A1) to zero-based column index.
         *
         * @param string $cellRef
         * @return int
         */
        private function columnIndexFromCellRef($cellRef) {
            $letters = preg_replace('/[0-9]/', '', $cellRef);
            if ($letters === '') {
                return 0;
            }
            return $this->lettersToIndex($letters);
        }

        /**
         * Convert column letters to zero-based index.
         *
         * @param string $letters
         * @return int
         */
        private function lettersToIndex($letters) {
            $letters = strtoupper($letters);
            $len     = strlen($letters);
            $index   = 0;
            for ($i = 0; $i < $len; $i++) {
                $index = $index * 26 + (ord($letters[$i]) - 64);
            }
            return $index - 1;
        }

        /**
         * Parse cell value with shared strings and inline strings support.
         *
         * @param SimpleXMLElement $cell
         * @param array<int, string> $sharedStrings
         * @return string
         */
        private function parseCellValue($cell, $sharedStrings) {
            $type = (string) $cell['t'];

            if ($type === 's') {
                $index = isset($cell->v) ? (int) $cell->v : 0;
                return isset($sharedStrings[$index]) ? $sharedStrings[$index] : '';
            }

            if ($type === 'inlineStr') {
                return $this->collectInlineString($cell->is);
            }

            if ($type === 'b') {
                return isset($cell->v) && ((string) $cell->v === '1') ? 'TRUE' : 'FALSE';
            }

            if (!isset($cell->v)) {
                return '';
            }

            return (string) $cell->v;
        }

        /**
         * Read shared strings table.
         *
         * @param ZipArchive $zip
         * @return array<int, string>
         */
        private function readSharedStrings(ZipArchive $zip) {
            $shared = array();
            $xml    = $zip->getFromName('xl/sharedStrings.xml');
            if ($xml === false) {
                return $shared;
            }

            $doc = @simplexml_load_string($xml);
            if (!$doc) {
                return $shared;
            }

            foreach ($doc->si as $si) {
                $shared[] = $this->collectSharedText($si);
            }

            return $shared;
        }

        /**
         * Resolve first worksheet path using workbook relationships.
         *
         * @param ZipArchive $zip
         * @return string|null
         */
        private function resolveFirstSheetPath(ZipArchive $zip) {
            $workbookXml = $zip->getFromName('xl/workbook.xml');
            if ($workbookXml === false) {
                return 'xl/worksheets/sheet1.xml';
            }

            $workbook = @simplexml_load_string($workbookXml);
            if (!$workbook) {
                return 'xl/worksheets/sheet1.xml';
            }

            $workbook->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $sheet = $workbook->xpath('//w:sheets/w:sheet');
            if (empty($sheet)) {
                return 'xl/worksheets/sheet1.xml';
            }

            $first = $sheet[0];
            $rid   = (string) $first['r:id'];
            if ($rid === '') {
                return 'xl/worksheets/sheet1.xml';
            }

            $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
            if ($relsXml === false) {
                return 'xl/worksheets/sheet1.xml';
            }

            $rels = @simplexml_load_string($relsXml);
            if (!$rels) {
                return 'xl/worksheets/sheet1.xml';
            }

            foreach ($rels->Relationship as $rel) {
                if ((string) $rel['Id'] === $rid) {
                    $target = (string) $rel['Target'];
                    if ($target === '') {
                        break;
                    }
                    if (strpos($target, 'worksheets/') === 0) {
                        return 'xl/' . $target;
                    }
                    return $target;
                }
            }

            return 'xl/worksheets/sheet1.xml';
        }

        /**
         * Collect text from shared string item.
         *
         * @param SimpleXMLElement $si
         * @return string
         */
        private function collectSharedText($si) {
            $textParts = array();
            if (isset($si->t)) {
                $textParts[] = (string) $si->t;
            }
            if (isset($si->r)) {
                foreach ($si->r as $run) {
                    if (isset($run->t)) {
                        $textParts[] = (string) $run->t;
                    }
                }
            }
            return trim(implode('', $textParts));
        }

        /**
         * Collect inline string value.
         *
         * @param SimpleXMLElement $is
         * @return string
         */
        private function collectInlineString($is) {
            $textParts = array();
            if (!$is) {
                return '';
            }
            if (isset($is->t)) {
                $textParts[] = (string) $is->t;
            }
            if (isset($is->r)) {
                foreach ($is->r as $run) {
                    if (isset($run->t)) {
                        $textParts[] = (string) $run->t;
                    }
                }
            }
            return trim(implode('', $textParts));
        }
    }
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
