<?php

namespace App\Libraries;

/**
 * Memecah dump SQL menjadi statement tunggal dengan memperhatikan string, identifier
 * ber-backtick, dan komentar — sehingga tanda ";" di dalam data tidak memotong query
 * (masalah pada implementasi lama yang memakai explode(';')).
 */
class SqlImporter
{
    /**
     * @return list<string>
     */
    public static function split(string $sql): array
    {
        $statements = [];
        $buffer     = '';
        $len        = strlen($sql);
        $quote      = null;

        for ($i = 0; $i < $len; $i++) {
            $c    = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            if ($quote !== null) {
                $buffer .= $c;
                if ($c === '\\' && $quote !== '`') {
                    $buffer .= $next;
                    $i++;
                } elseif ($c === $quote) {
                    if ($next === $quote) { // kutip ganda di-escape: '' atau ""
                        $buffer .= $next;
                        $i++;
                    } else {
                        $quote = null;
                    }
                }

                continue;
            }

            // Komentar baris: "-- " atau "#"
            if (($c === '-' && $next === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2]))) || $c === '#') {
                $end = strpos($sql, "\n", $i);
                $i   = $end === false ? $len : $end;
                $buffer .= "\n";

                continue;
            }

            // Komentar blok. Komentar bersyarat MySQL (/*! ... */) tetap dipertahankan.
            if ($c === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $end = $end === false ? $len : $end + 2;
                if (($sql[$i + 2] ?? '') === '!') {
                    $buffer .= substr($sql, $i, $end - $i);
                }
                $i = $end - 1;

                continue;
            }

            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
                $buffer .= $c;

                continue;
            }

            if ($c === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';

                continue;
            }

            $buffer .= $c;
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }
}
