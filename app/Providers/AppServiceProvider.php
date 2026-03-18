<?php

namespace App\Providers;

use App\Models\Configuracion;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $publicPath = $this->resolvePublicPath();

        if ($publicPath !== null) {
            config(['dompdf.public_path' => $publicPath]);
        }

        $locale = config('app.locale', 'es');

        try {
            if (Schema::hasTable('configuraciones')) {
                $configuredLocale = Configuracion::valor('locale');

                if (is_string($configuredLocale) && in_array($configuredLocale, ['es', 'en'], true)) {
                    $locale = $configuredLocale;
                }
            }
        } catch (\Throwable) {
            // During early bootstrap or migrations, use env locale fallback.
        }

        app()->setLocale($locale);
        config(['app.locale' => $locale]);
    }

    private function resolvePublicPath(): ?string
    {
        $candidates = [
            base_path('public'),
            base_path('public_html'),
            dirname(base_path()).DIRECTORY_SEPARATOR.'public_html',
        ];

        $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? trim((string) $_SERVER['DOCUMENT_ROOT']) : '';
        if ($documentRoot !== '') {
            $candidates[] = $documentRoot;
        }

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            $real = realpath($candidate);
            if ($real !== false && is_dir($real)) {
                return $real;
            }
        }

        return null;
    }
}
