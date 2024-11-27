<?php

namespace App\Repositories;

class StringFormattingRepository
{
    public function forPrint($text)
    {
        $words = explode(' ', $this->cleanString($text));
        $reverse = false;
        foreach ($words as $index => $word) {
            if ($this->isHebrew($word)) {
                $words[$index] = $this->mbStrRev($word);
                $reverse = true;
            } else {
                $words[$index] = $word;
            }
        }
        if ($reverse) {
            $words = array_reverse($words);
        }
        return join(' ', $words);
    }

    public function cleanString($string)
    {
        $string = trim($string);
        if (!mb_check_encoding($string, 'UTF-8')) {
            $string = mb_convert_encoding($string, 'UTF-8');
        }
        $string = html_entity_decode($string, ENT_COMPAT, 'UTF-8'); // remove html entities
        $string = iconv("UTF-8", "UTF-8//IGNORE", $string); // drop all non utf-8 characters

        // this is some bad utf-8 byte sequence that makes mysql complain - control and formatting i think
        $string = preg_replace('/(?>[\x00-\x1F]|\xC2[\x80-\x9F]|\xE2[\x80-\x8F]{2}|\xE2\x80[\xA4-\xA8]|\xE2\x81[\x9F-\xAF])/', ' ', $string);

        $string = preg_replace('/\s+/', ' ', $string); // reduce all multiple whitespace to a single space

        return $string;
    }

    private function isHebrew($word)
    {
        for ($i = 0, $cnt = strlen($word); $i < $cnt; ++$i) {
            if (ord($word[$i]) > 127) {
                return true;
            }
        }
        return false;
    }

    private function mbStrRev($string, $encoding = null)
    {
        if ($encoding === null) {
            $encoding = mb_detect_encoding($string);
        }

        $length = mb_strlen($string, $encoding);
        $reversed = '';
        while ($length-- > 0) {
            $reversed .= mb_substr($string, $length, 1, $encoding);
        }

        return $reversed;
    }
}
