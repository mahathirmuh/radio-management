<?php

namespace App\Helpers;

use App\Models\Log\LogActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LogApps
{
    /** Request fields that must never be written to activity_logs / error_logs. */
    public const SENSITIVE_INPUT = ['password', 'password_confirmation', 'current_password', '_token', 'token'];

    public static function activityLog(Request $request, $user)
    {
        if ($request->has('models')) {
            //Prevent Data from Kendo Table input
            $data = $request->input('models');
        } else {
            $data = json_encode($request->except(self::SENSITIVE_INPUT));
        }
        LogActivity::create([
            'subject' => 'Merdeka Apps' ?? '-',
            'url' => Str::limit($request->fullUrl() ?? '-', 255, ''),
            'method' => $request->method() ?? '-',
            'agent' => Str::limit((string) ($request->header('user-agent') ?? '-'), 255, ''),
            'ip' => $request->ip() ?? '-',
            'data' => is_string($data) ? $data : json_encode($data), //Harus berupa Json
            'user_id' =>  $user,
        ]);
    }
    public static function sanitizeInput($input)
    {
        // Jika array, lakukan rekursi
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }

        // Jika bukan array, bersihkan string. Only tags are removed: encoding entities here would
        // store "A &amp; B" and encode it again on every save; escaping belongs to the output.
        return is_string($input)
            ? strip_tags($input)
            : $input;
    }
}
