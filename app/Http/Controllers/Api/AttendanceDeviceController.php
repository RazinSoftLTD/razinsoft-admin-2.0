<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Support\AttendanceRecorder;
use Illuminate\Http\Request;

/**
 * Push endpoint for an on-site sync bridge. A ZKTeco reader can't call an HTTPS API itself,
 * so a small agent on the office network pulls its logs and posts them here with the
 * device's API token. Accepts the same shapes as the manual import.
 */
class AttendanceDeviceController extends Controller
{
    public function push(Request $request)
    {
        $token = $request->bearerToken() ?: $request->input('token');
        $device = AttendanceDevice::where('api_token', $token)->where('is_active', true)->first();

        abort_unless($device, 401, 'Unknown or inactive device token.');

        $rows = is_array($request->input('logs'))
            ? AttendanceController::parseDeviceLogs(json_encode($request->input('logs')))
            : AttendanceController::parseDeviceLogs((string) $request->getContent());

        if (! $rows) {
            return response()->json(['ok' => false, 'error' => 'No readable punches in the payload.'], 422);
        }

        $summary = AttendanceRecorder::ingestDeviceLogs($device, $rows);

        return response()->json(['ok' => true, 'device' => $device->name] + $summary);
    }
}
