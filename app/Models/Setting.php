<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];

    private static ?array $cache = null;

    public static function defaults(): array
    {
        return [
            'company_name'      => 'SIA Konveksi',
            'company_tagline'   => 'Garment & Konveksi',
            'company_address'   => '',
            'company_phone'     => '',
            'company_email'     => '',
            'company_npwp'      => '',
            'company_license'   => '',
            'sign_prepared_name'  => '',
            'sign_prepared_title' => 'Bagian Keuangan',
            'sign_approved_name'  => '',
            'sign_approved_title' => 'Pemilik Usaha',
            'logo_path'         => null,
            'favicon_path'      => null,
            'sidebar_color'     => '#1E2A5A',
            'accent_color'      => '#14B8A6',
            'sidebar_collapsed' => '0',
        ];
    }

    public static function values(): array
    {
        if (self::$cache === null) {
            try {
                $db = self::query()->pluck('value', 'key')->all();
            } catch (\Throwable $e) {
                $db = [];   // tabel belum ada (sebelum migrate)
            }
            self::$cache = array_merge(self::defaults(), array_filter($db, fn($v) => $v !== null));
        }

        return self::$cache;
    }

    public static function val(string $key, $default = null)
    {
        return self::values()[$key] ?? $default;
    }

    public static function put(array $pairs): void
    {
        foreach ($pairs as $k => $v) {
            self::updateOrCreate(['key' => $k], ['value' => $v]);
        }
        self::$cache = null;
    }

    /** Data yang dibagikan ke layout dan halaman login. */
    public static function appData(): array
    {
        $v   = self::values();
        $url = fn($p) => $p ? asset('storage/' . $p) : null;

        return [
            'name'        => $v['company_name'],
            'tagline'     => $v['company_tagline'],
            'logo_url'    => $url($v['logo_path']),
            'favicon_url' => $url($v['favicon_path']) ?: $url($v['logo_path']),
            'collapsed'   => $v['sidebar_collapsed'] === '1',
            'theme'       => self::theme($v['sidebar_color'], $v['accent_color']),
        ];
    }

    /** Menurunkan seluruh warna dari dua warna pilihan. Rumus yang sama dipakai di JavaScript halaman Tampilan. */
    public static function theme(string $bg, string $accent): array
    {
        $bg     = self::hex($bg, '#1E2A5A');
        $accent = self::hex($accent, '#14B8A6');
        $lum    = self::lum($bg);
        $light  = $lum > 0.6;

        $brand = $light ? self::mix($accent, '#000000', 0.4)
               : ($lum > 0.35 ? self::mix($bg, '#000000', 0.3) : $bg);

        return [
            'bg'     => $bg,
            'accent' => $accent,
            'brand'  => $brand,
            'light'  => $light,
            'title'  => $light ? '#0F172A' : '#FFFFFF',
            'text'   => $light ? self::mix($bg, '#000000', 0.65) : self::mix($bg, '#FFFFFF', 0.72),
            'muted'  => $light ? self::mix($bg, '#000000', 0.45) : self::mix($bg, '#FFFFFF', 0.5),
            'hover'  => $light ? self::mix($bg, '#000000', 0.05) : self::mix($bg, '#FFFFFF', 0.08),
            'line'   => $light ? self::mix($bg, '#000000', 0.08) : self::mix($bg, '#FFFFFF', 0.12),
            'active' => $light ? self::mix($accent, '#000000', 0.35) : '#FFFFFF',
        ];
    }

    private static function hex(?string $c, string $fallback): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $c) ? strtoupper($c) : $fallback;
    }

    private static function rgb(string $h): array
    {
        return [hexdec(substr($h, 1, 2)), hexdec(substr($h, 3, 2)), hexdec(substr($h, 5, 2))];
    }

    private static function mix(string $a, string $b, float $t): string
    {
        [$r1, $g1, $b1] = self::rgb($a);
        [$r2, $g2, $b2] = self::rgb($b);

        return sprintf('#%02X%02X%02X',
            round($r1 * (1 - $t) + $r2 * $t),
            round($g1 * (1 - $t) + $g2 * $t),
            round($b1 * (1 - $t) + $b2 * $t));
    }

    private static function lum(string $h): float
    {
        [$r, $g, $b] = self::rgb($h);

        return (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
    }
}