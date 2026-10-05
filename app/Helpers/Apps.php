<?php

namespace App\Helpers;

use App\Models\HistoryWorkflowStatus;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Apps
{
    /**
     * Memisahkan operator dan angka dari input user, return array[]
     */
    public static function splitNumberFormat($string)
    {
        return preg_split('/([<>=]+)/', $string, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Mengubah input user , menjadi .
     */
    public static function cleanNumberFormat($string)
    {

        $value = str_replace(',', '.', $string);
        $value = preg_replace('/\.(?=.*\.)/', '', $value);
        return $value;
    }

    /**
     * Check input date atau bukan .
     * 
     */
    public static function checkToDate($userInput)
    {
        $patterns = [
            '/^\d{4}-\d{2}-\d{2}$/',
            '/^\d{4}-\d{2}$/',
            '/^\d{2}-\d{2}-\d{4}$/',
            '/^[a-zA-Z]+\s+\d{4}$/',
            '/^\d{1,2}\s+[a-zA-Z]+\s+\d{4}$/',
            '/^\d{1,2}\s+[a-zA-Z]+\s+\d{1,2}$/',
            '/^\d{1,2}\s+[a-zA-Z]+\s+\d{1,2}\s*-\s*[a-zA-Z]+\s+\d{4}$/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $userInput)) {
                try {
                    return true;
                } catch (\Exception $e) {
                    $tryFormats = [
                        'Y-m-d',
                        'd-m-Y',
                        'F Y',
                        'j M Y',
                        'd M Y',
                        'd/m/Y',
                        'Y/m/d',
                        'm/d/Y',
                        'Y-M-d',
                        'd-M-Y',
                        'Y/m/d H:i:s',
                        'Y-m-d H:i:s',
                        'd/m/Y H:i:s',
                        'H:i:s Y-m-d',
                        'Ymd',
                        'd-M-y',
                        'd/m/y',
                        'j M, Y',
                        'M j, Y',
                        'j F, Y',
                        'F j, Y',
                        'm-d-y',
                        'j/n/Y',
                        'n/j/Y',
                        'Y.n.j',
                        'n.j.Y',
                        'Y j F',
                        'j F Y',
                        'Y j F H:i:s',
                        'j F Y H:i:s',
                        'Y-m-d\TH:i:sP',
                        'j F Y \a\t H:i:s',
                        'd F Y',
                        'F jS, Y',
                        'dS F Y',
                        'F d, Y',
                        'd F Y, l',
                        'F d, Y, l',
                        'l, F d, Y',
                    ];
                    foreach ($tryFormats as $format) {
                        $parsedDate = date_create_from_format($format, $userInput);
                        if ($parsedDate !== false) {
                            return true;
                        }
                    }
                }
                break;
            }
        }
        return false;
    }

    /**
     * Mengubah input date , menjadi .
     */
    public static function convertToDate($userInput, $request)
    {
        $patterns = [
            '/^\d{4}-\d{2}-\d{2}$/',
            '/^\d{4}-\d{2}$/',
            '/^\d{2}-\d{2}-\d{4}$/',
            '/^[a-zA-Z]+\s+\d{4}$/',
            '/^\d{1,2}\s+[a-zA-Z]+\s+\d{4}$/',
            '/^\d{1,2}\s+[a-zA-Z]+\s+\d{1,2}$/',
            '/^\d{1,2}\s+[a-zA-Z]+\s+\d{1,2}\s*-\s*[a-zA-Z]+\s+\d{4}$/',
        ];

        $tryFormats = [
            'Y-m-d',
            'd-m-Y',
            'F Y',
            'j F Y',
            'j M Y',
            'd M Y',
            'd/m/Y',
            'Y/m/d',
            'm/d/Y',
            'Y-M-d',
            'd-M-Y',
            'Y/m/d H:i:s',
            'Y-m-d H:i:s',
            'd/m/Y H:i:s',
            'H:i:s Y-m-d',
            'Ymd',
            'd-M-y',
            'd/m/y',
            'j M, Y',
            'M j, Y',
            'j F, Y',
            'F j, Y',
            'm-d-y',
            'j/n/Y',
            'n/j/Y',
            'Y.n.j',
            'n.j.Y',
            'Y j F',
            'Y j F H:i:s',
            'Y-m-d\TH:i:sP',
            'j F Y \a\t H:i:s',
            'd F Y',
            'F jS, Y',
            'dS F Y',
            'F d, Y',
            'd F Y, l',
            'F d, Y, l',
            'l, F d, Y'
        ];

        if (!is_array($userInput)) {
            return;
        }

        foreach ($userInput as $valueInput) {
            if (!isset($valueInput['value'])) {
                continue;
            }

            if (self::matchesPattern($valueInput['value'], $patterns) || ($formattedDate = self::parseDate($valueInput['value'], $tryFormats))) {
                self::updateRequestFilters($request, $formattedDate ?? Carbon::parse($valueInput['value'])->format('Y-m-d'));
                return;
            }
        }
    }

    private static function matchesPattern($value, $patterns)
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        return false;
    }

    private static function parseDate($value, $formats)
    {
        foreach ($formats as $format) {
            $parsedDate = date_create_from_format($format, $value);
            if ($parsedDate !== false) {
                return date_format($parsedDate, 'Y-m-d');
            }
        }
        return null;
    }

    private static function updateRequestFilters($request, $formattedDate)
    {
        $filters = $request->input('filter.filters', []);
        foreach ($filters as $key => &$filter) {
            $filter['value'] = $formattedDate;
        }
        $request->merge(['filter' => ['filters' => $filters]]);
    }
}
