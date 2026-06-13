<?php

if (!function_exists('settings')) {
    function settings() {
        return cache()->remember('settings', 24*60, function () {
            return \Modules\Setting\Entities\Setting::firstOrFail();
        });
    }
}

if (!function_exists('format_currency')) {
    function format_currency($value, $format = true, $show_symbol = true) {
        if (!$format) {
            return $value;
        }

        $settings = settings();
        $position = $settings->default_currency_position;
        // Currency relation can be null if the default currency row was deleted/recreated —
        // fall back to sane defaults instead of crashing every page that formats money
        $currency = $settings->currency;
        $symbol = $show_symbol ? ($currency->symbol ?? 'AED') : '';
        $decimal_separator = $currency->decimal_separator ?? '.';
        $thousand_separator = $currency->thousand_separator ?? ',';

        // Convert currency symbols to HTML entities for PDF compatibility
        $currency_entities = [
            '₹' => '₹', // Keep original for display, will be handled by font
            '$' => '$',
            '€' => '€',
            '£' => '£',
            '¥' => '¥',
        ];
        
        // Use original symbol but ensure proper encoding
        $display_symbol = isset($currency_entities[$symbol]) ? $currency_entities[$symbol] : $symbol;
        
        if ($position == 'prefix') {
            $formatted_value = $display_symbol . number_format((float) $value, 2, $decimal_separator, $thousand_separator);
        } else {
            $formatted_value = number_format((float) $value, 2, $decimal_separator, $thousand_separator) . $display_symbol;
        }

        // Strip trailing .00 or ,00
        $suffix = $decimal_separator . '00';
        if (substr($formatted_value, -strlen($suffix)) === $suffix) {
             $formatted_value = substr($formatted_value, 0, -strlen($suffix));
        }

        return $formatted_value;
    }
}

if (!function_exists('format_currency_symbol_for_pdf')) {
    function format_currency_symbol_for_pdf($symbol) {
        // Use DejaVu Sans compatible symbols or fallbacks
        $symbolMap = [
            '₹' => '₹',  // Try original first
            '$' => '$',
            '€' => '€',
            '£' => '£',
            '¥' => '¥'
        ];
        
        // Return mapped symbol or original if not in map
        return $symbolMap[$symbol] ?? $symbol;
    }
}

if (!function_exists('make_reference_id')) {
    function make_reference_id($prefix, $number) {
        $padded_text = $prefix . '-' . str_pad($number, 5, 0, STR_PAD_LEFT);

        return $padded_text;
    }
}

if (!function_exists('array_merge_numeric_values')) {
    function array_merge_numeric_values() {
        $arrays = func_get_args();
        $merged = array();
        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                if (!is_numeric($value)) {
                    continue;
                }
                if (!isset($merged[$key])) {
                    $merged[$key] = $value;
                } else {
                    $merged[$key] += $value;
                }
            }
        }

        return $merged;
    }
}

if (!function_exists('embed_image_for_pdf')) {
    /**
     * Convert an image URL to a data URI for PDF embedding.
     * Returns original URL if not a PDF request or conversion fails.
     *
     * @param string|null $url Image URL
     * @param bool $is_pdf Whether this is a PDF render context
     * @return string|null Data URI or original URL
     */
    function embed_image_for_pdf(?string $url, bool $is_pdf = true): ?string
    {
        if (!$url) return null;
        if (!$is_pdf) return $url;

        try {
            // Try local file first
            $path = public_path(parse_url($url, PHP_URL_PATH));
            if ($path && file_exists($path)) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mimeTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
                $mime = $mimeTypes[$ext] ?? 'image/png';
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
            }

            // Try remote URL
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $context = stream_context_create(['http' => ['timeout' => 5]]);
                $data = @file_get_contents($url, false, $context);
                if ($data !== false) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_buffer($finfo, $data);
                    finfo_close($finfo);
                    return 'data:' . $mime . ';base64,' . base64_encode($data);
                }
            }
        } catch (\Exception $e) {
            // Fall through to return original URL
        }

        return $url;
    }
}
