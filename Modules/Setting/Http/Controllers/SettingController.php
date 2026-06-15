<?php

namespace Modules\Setting\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Modules\Setting\Entities\Setting;
use Modules\Setting\Http\Requests\StoreSettingsRequest;
use Modules\Setting\Http\Requests\StoreSmtpSettingsRequest;

class SettingController extends Controller
{
    /**
     * Delete a stored QR file from storage.
     *
     * @param string|null $url The URL of the stored file
     * @return bool Whether deletion was successful
     */
    private function deleteStoredQrFile(?string $url): bool
    {
        if (empty($url)) return false;

        try {
            $filename = basename(parse_url($url, PHP_URL_PATH));
            $storagePath = 'public/settings/' . $filename;
            if (Storage::exists($storagePath)) {
                return Storage::delete($storagePath);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to delete QR file: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Handle QR file upload and return the new URL.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string|null $oldUrl Previous file URL to delete
     * @return string|null New file URL or null on failure
     */
    private function handleQrFileUpload($file, ?string $oldUrl = null): ?string
    {
        try {
            $this->deleteStoredQrFile($oldUrl);
            $path = $file->store('public/settings');
            return Storage::url($path);
        } catch (\Exception $e) {
            Log::error('Failed to upload QR file: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Handle application logo upload with optimization for clarity.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string|null $oldUrl
     * @return string|null
     */
    private function handleSiteLogoUpload($file, ?string $oldUrl = null): ?string
    {
        try {
            $this->deleteStoredQrFile($oldUrl);

            $image = Image::make($file)->orientate();

            // Downscale very large logos, but never upscale smaller ones.
            $image->resize(1200, 360, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            // Mild sharpen to improve medium-quality logos.
            $image->sharpen(8);

            $filename = 'site-logo-' . Str::uuid() . '.png';
            $storagePath = 'public/settings/' . $filename;

            Storage::put($storagePath, (string) $image->encode('png', 90));

            return Storage::url($storagePath);
        } catch (\Exception $e) {
            Log::error('Failed to upload site logo: ' . $e->getMessage());
            return null;
        }
    }

    public function index() {
        abort_if(Gate::denies('access_settings'), 403);

        $settings = Setting::firstOrFail();

        return view('setting::index', compact('settings'));
    }


    public function update(StoreSettingsRequest $request) {
        $settings = Setting::firstOrFail();

        $data = [
            'company_name' => $request->company_name,
            'company_email' => $request->company_email,
            'company_phone' => $request->company_phone,
            'bank_name' => $request->bank_name,
            'bank_account' => $request->bank_account,
            'bank_branch' => $request->bank_branch,
            'bank_ifsc' => $request->bank_ifsc,
            'notification_email' => $request->notification_email,
            'company_address' => $request->company_address,
            'company_gst' => $request->company_gst,
            'default_currency_id' => $request->default_currency_id,
            'default_currency_position' => $request->default_currency_position,
        ];

        if ($request->boolean('remove_site_logo')) {
            $this->deleteStoredQrFile($settings->site_logo);
            $data['site_logo'] = null;
        }

        if ($request->hasFile('site_logo')) {
            $newUrl = $this->handleSiteLogoUpload($request->file('site_logo'), $settings->site_logo);
            if ($newUrl) {
                $data['site_logo'] = $newUrl;
            }
        }

        // Handle QR image removal requests
        if ($request->boolean('remove_gpay_qr')) {
            $this->deleteStoredQrFile($settings->gpay_qr);
            $data['gpay_qr'] = null;
        }

        if ($request->boolean('remove_phonepe_qr')) {
            $this->deleteStoredQrFile($settings->phonepe_qr);
            $data['phonepe_qr'] = null;
        }

        // Handle QR image uploads (takes precedence over removal)
        if ($request->hasFile('gpay_qr_file')) {
            $newUrl = $this->handleQrFileUpload($request->file('gpay_qr_file'), $settings->gpay_qr);
            if ($newUrl) {
                $data['gpay_qr'] = $newUrl;
            }
        }

        if ($request->hasFile('phonepe_qr_file')) {
            $newUrl = $this->handleQrFileUpload($request->file('phonepe_qr_file'), $settings->phonepe_qr);
            if ($newUrl) {
                $data['phonepe_qr'] = $newUrl;
            }
        }

        $settings->update($data);

        cache()->forget('settings');

        toast('Settings Updated!', 'info');

        return redirect()->route('settings.index');
    }


    public function updateSmtp(StoreSmtpSettingsRequest $request) {
        // Sanitize every value before it touches the .env file: strip quotes,
        // newlines and null bytes so a value can't break out of its line or
        // inject extra variables (required .env-write rule).
        $sanitize = fn ($v) => str_replace(['"', "\n", "\r", "\0"], '', (string) $v);

        $values = [
            'MAIL_MAILER'       => $sanitize($request->mail_mailer),
            'MAIL_HOST'         => $sanitize($request->mail_host),
            'MAIL_PORT'         => $sanitize($request->mail_port),
            'MAIL_USERNAME'     => $sanitize($request->mail_username),
            'MAIL_PASSWORD'     => $sanitize($request->mail_password),
            'MAIL_ENCRYPTION'   => $sanitize($request->mail_encryption),
            'MAIL_FROM_ADDRESS' => $sanitize($request->mail_from_address),
            'MAIL_FROM_NAME'    => $sanitize($request->mail_from_name),
        ];

        try {
            $path = base_path('.env');
            $env  = file_get_contents($path);

            foreach ($values as $key => $value) {
                $line    = $key . '="' . $value . '"';
                $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
                // Replace the existing KEY=... line whether it is quoted or not;
                // append it if the key is missing. Use a callback so $ or \ in the
                // value are never treated as regex backreferences.
                if (preg_match($pattern, $env)) {
                    $env = preg_replace_callback($pattern, fn () => $line, $env);
                } else {
                    $env .= PHP_EOL . $line;
                }
            }

            file_put_contents($path, $env);
            Artisan::call('config:clear');

            toast('Mail Settings Updated!', 'info');
        } catch (\Throwable $exception) {
            Log::error($exception);
            session()->flash('settings_smtp_message', 'Something Went Wrong!');
        }

        return redirect()->route('settings.index');
    }

    /**
     * Remove stored QR image via AJAX.
     * Expects JSON: { which: 'gpay'|'phonepe' }
     */
    public function removeQr(Request $request)
    {
        abort_if(Gate::denies('access_settings'), 403);

        $which = $request->input('which');
        if (!in_array($which, ['gpay', 'phonepe'])) {
            return response()->json(['ok' => false, 'message' => 'Invalid target'], 400);
        }

        $settings = Setting::firstOrFail();
        $field = $which === 'gpay' ? 'gpay_qr' : 'phonepe_qr';

        if (empty($settings->{$field})) {
            return response()->json(['ok' => true, 'message' => 'Nothing to remove']);
        }

        $this->deleteStoredQrFile($settings->{$field});
        $settings->update([$field => null]);
        cache()->forget('settings');

        return response()->json(['ok' => true]);
    }
}
