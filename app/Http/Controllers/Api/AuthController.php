<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    private function generateOtp(string $email, string $type): string
    {
        OtpCode::where('email', $email)->where('type', $type)->delete();
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        OtpCode::create([
            'email'      => $email,
            'code'       => $code,
            'type'       => $type,
            'expires_at' => now()->addMinutes(15),
        ]);
        return $code;
    }

    private function sendOtpEmail(string $email, string $code, string $type): void
    {
        $subject = $type === 'email_verification' ? 'Verifikasi Email - Budget Trip Solo' : 'Reset Password - Budget Trip Solo';
        $title   = $type === 'email_verification' ? 'Verifikasi Email Anda' : 'Reset Password';
        $desc    = $type === 'email_verification'
            ? 'Gunakan kode berikut untuk memverifikasi akun Anda:'
            : 'Gunakan kode berikut untuk mereset password Anda:';

        $html = "
        <!DOCTYPE html>
        <html>
        <body style='font-family:Arial,sans-serif;background:#faf5f0;padding:24px;margin:0'>
          <div style='max-width:480px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;text-align:center'>
            <div style='width:60px;height:60px;background:#C0392B;border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center'>
              <span style='color:white;font-size:28px'>✈</span>
            </div>
            <h2 style='color:#C0392B;margin:0 0 4px'>Budget Trip Solo</h2>
            <h3 style='color:#1A1A1A;margin:0 0 16px'>$title</h3>
            <p style='color:#666;font-size:14px;margin:0 0 24px'>$desc</p>
            <div style='background:#faf5f0;border:2px dashed #C0392B;border-radius:12px;padding:20px;margin:0 0 24px'>
              <span style='font-size:38px;font-weight:bold;color:#C0392B;letter-spacing:10px'>$code</span>
            </div>
            <p style='color:#999;font-size:12px'>Kode berlaku <strong>15 menit</strong>. Jangan bagikan kode ini kepada siapapun.</p>
          </div>
        </body>
        </html>";

        Http::withHeaders([
            'api-key'      => env('BREVO_API_KEY'),
            'Content-Type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [
            'sender'      => ['name' => config('app.name'), 'email' => config('mail.from.address')],
            'to'          => [['email' => $email]],
            'subject'     => $subject,
            'htmlContent' => $html,
        ]);
    }

    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'     => 'required|string|max:255',
                'email'    => 'required|email',
                'password' => ['required', 'confirmed', Password::min(6)],
            ]);

            // Hapus akun lama yang belum diverifikasi agar bisa daftar ulang
            User::where('email', $validated['email'])
                ->whereNull('email_verified_at')
                ->delete();

            if (User::where('email', $validated['email'])->exists()) {
                return response()->json(['success' => false, 'message' => 'Validasi gagal.', 'errors' => ['email' => ['Email sudah digunakan.']]], 422);
            }

            DB::transaction(function () use ($validated) {
                $user = User::create([
                    'name'     => $validated['name'],
                    'email'    => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'role'     => 'user',
                ]);

                $code = $this->generateOtp($user->email, 'email_verification');
                $this->sendOtpEmail($user->email, $code, 'email_verification');
            });

            return response()->json([
                'success' => true,
                'message' => 'Registrasi berhasil. Cek email untuk kode OTP.',
                'data'    => ['email' => $validated['email'], 'requires_verification' => true],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function verifyEmail(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
                'otp'   => 'required|string|size:6',
            ]);

            $record = OtpCode::where('email', $validated['email'])
                ->where('type', 'email_verification')
                ->latest()
                ->first();

            if (!$record || $record->code !== $validated['otp']) {
                return response()->json(['success' => false, 'message' => 'Kode OTP salah.'], 422);
            }
            if ($record->isExpired()) {
                $record->delete();
                return response()->json(['success' => false, 'message' => 'Kode OTP sudah kadaluarsa.'], 422);
            }

            $user = User::where('email', $validated['email'])->first();
            $user->email_verified_at = now();
            $user->save();
            $record->delete();

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Email berhasil diverifikasi.',
                'data'    => ['user' => $user, 'token' => $token],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function sendOtp(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
                'type'  => 'required|in:email_verification,password_reset',
            ]);

            $code = $this->generateOtp($validated['email'], $validated['type']);
            $this->sendOtpEmail($validated['email'], $code, $validated['type']);

            return response()->json(['success' => true, 'message' => 'Kode OTP telah dikirim ke email.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email'    => 'required|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $validated['email'])->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                return response()->json(['success' => false, 'message' => 'Email atau password salah.'], 401);
            }
            if (!$user->is_active) {
                return response()->json(['success' => false, 'message' => 'Akun Anda dinonaktifkan.'], 403);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil.',
                'data'    => ['user' => $user, 'token' => $token],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function forgotPassword(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            $code = $this->generateOtp($validated['email'], 'password_reset');
            $this->sendOtpEmail($validated['email'], $code, 'password_reset');

            return response()->json([
                'success' => true,
                'message' => 'Kode OTP telah dikirim ke email.',
                'data'    => ['email' => $validated['email']],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors  = $e->errors();
            $firstMsg = collect($errors)->flatten()->first();
            return response()->json(['success' => false, 'message' => $firstMsg ?? 'Validasi gagal.', 'errors' => $errors], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $validated = $request->validate([
                'email'    => 'required|email|exists:users,email',
                'otp'      => 'required|string|size:6',
                'password' => ['required', 'confirmed', Password::min(6)],
            ]);

            $record = OtpCode::where('email', $validated['email'])
                ->where('type', 'password_reset')
                ->latest()
                ->first();

            if (!$record || $record->code !== $validated['otp']) {
                return response()->json(['success' => false, 'message' => 'Kode OTP salah.'], 422);
            }
            if ($record->isExpired()) {
                $record->delete();
                return response()->json(['success' => false, 'message' => 'Kode OTP sudah kadaluarsa.'], 422);
            }

            $user = User::where('email', $validated['email'])->first();
            $user->update(['password' => Hash::make($validated['password'])]);
            $record->delete();

            return response()->json(['success' => true, 'message' => 'Password berhasil direset. Silakan masuk.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return response()->json(['success' => true, 'message' => 'Logout berhasil.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function me(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'Data pengguna.', 'data' => $request->user()]);
    }

    public function updateProfile(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'   => 'sometimes|string|max:255',
                'phone'  => 'sometimes|nullable|string|max:20',
                'bio'    => 'sometimes|nullable|string|max:500',
                'avatar' => 'sometimes|nullable|url',
            ]);
            $request->user()->update($validated);
            return response()->json(['success' => true, 'message' => 'Profil berhasil diperbarui.', 'data' => $request->user()->fresh()]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $validated = $request->validate([
                'current_password' => 'required|string',
                'password'         => ['required', 'confirmed', Password::min(6)],
            ]);
            if (!Hash::check($validated['current_password'], $request->user()->password)) {
                return response()->json(['success' => false, 'message' => 'Password lama tidak sesuai.'], 422);
            }
            $request->user()->update(['password' => Hash::make($validated['password'])]);
            return response()->json(['success' => true, 'message' => 'Password berhasil diubah.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }
}
