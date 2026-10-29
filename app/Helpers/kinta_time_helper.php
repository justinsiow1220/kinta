<?php

if (! function_exists('kinta_local_time')) {
    /** Format a database UTC timestamp as Malaysia local time for admin pages. */
    function kinta_local_time($value, string $empty = '-'): string
    {
        if ($value === null || trim((string) $value) === '') {
            return $empty;
        }

        try {
            $utc = new DateTimeZone('UTC');
            $local = new DateTimeZone('Asia/Kuala_Lumpur');
            $timestamp = new DateTimeImmutable((string) $value, $utc);

            return $timestamp->setTimezone($local)->format('Y-m-d h:i:s a') . ' MYT';
        } catch (Throwable) {
            return (string) $value;
        }
    }
}
