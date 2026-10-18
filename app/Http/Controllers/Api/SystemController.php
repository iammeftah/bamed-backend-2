<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Category;
use App\Models\ErrorLog;
use App\Models\Product;
use App\Models\RequestLog;
use App\Models\Sale;
use App\Services\ActivityLogger;
use App\Services\ImageStorage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Developer dashboard. Every route is behind the "super" middleware.
 * Nothing here exposes passwords, tokens, request bodies or stack-trace arguments.
 */
class SystemController extends Controller
{
    /** GET /admin/system/overview?hours=24  (1 to 168) */
    public function overview(Request $request, ImageStorage $images)
    {
        $hours = min(168, max(1, (int) $request->query('hours', 24)));
        $since = now()->subHours($hours);

        $health = $this->health();
        $errors = $this->errors();
        $storage = $images->stats();

        return response()->json([
            'generatedAt' => now()->getTimestamp() * 1000,
            'hours' => $hours,
            'health' => $health,
            'problems' => $this->problems($health, $errors['last24h'], $storage),
            'traffic' => $this->traffic($since, $hours),
            'security' => $this->security($since),
            'errors' => $errors,
            'storage' => $storage,
            'business' => $this->business(),
            'team' => $this->team(),
        ]);
    }

    /** POST /admin/system/prune-images */
    public function pruneImages(Request $request, ImageStorage $images, ActivityLogger $log)
    {
        $deleted = $images->pruneOrphans();

        $log->record(
            $request->user(), 'delete', 'product', '0',
            "Nettoyage des photos orphelines : {$deleted} fichier(s) supprimé(s)",
        );

        return response()->json(['deleted' => $deleted]);
    }

    /** DELETE /admin/system/errors */
    public function clearErrors()
    {
        ErrorLog::query()->delete();

        return response()->noContent();
    }

    /* ---------------------------------------------------------------- */

    /** @return array<string, mixed> */
    private function health(): array
    {
        $t = microtime(true);
        $dbOk = true;
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $dbOk = false;
        }
        $dbMs = round((microtime(true) - $t) * 1000, 1);

        try {
            Cache::put('system:ping', 1, 10);
            $cacheOk = Cache::get('system:ping') === 1;
        } catch (\Throwable) {
            $cacheOk = false;
        }

        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        return [
            'app' => [
                'name' => (string) config('app.name'),
                'env' => (string) config('app.env'),
                'debug' => (bool) config('app.debug'),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'timezone' => (string) config('app.timezone'),
                'url' => (string) config('app.url'),
            ],
            'database' => [
                'ok' => $dbOk,
                'driver' => DB::getDriverName(),
                'latencyMs' => $dbMs,
                'sizeBytes' => $dbOk ? $this->dbSize() : null,
            ],
            'cache' => [
                'ok' => $cacheOk,
                'driver' => (string) config('cache.default'),
            ],
            'queue' => [
                'driver' => (string) config('queue.default'),
                'pending' => $this->count('jobs'),
                'failed' => $this->count('failed_jobs'),
            ],
            'disk' => [
                'freeBytes' => $free === false ? null : (int) $free,
                'totalBytes' => $total === false ? null : (int) $total,
                'storageLinked' => is_link(public_path('storage')),
                'writable' => is_writable(storage_path('app/public')),
            ],
        ];
    }

    private function count(string $table): int
    {
        try {
            return Schema::hasTable($table) ? DB::table($table)->count() : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function dbSize(): ?int
    {
        try {
            $driver = DB::getDriverName();

            if ($driver === 'pgsql') {
                return (int) DB::selectOne('select pg_database_size(current_database()) as s')->s;
            }

            if ($driver === 'sqlite') {
                $file = (string) config('database.connections.sqlite.database');

                return is_file($file) ? (int) filesize($file) : null;
            }
        } catch (\Throwable) {
            // unknown size is fine
        }

        return null;
    }

    /**
     * Things the developer should fix. level: danger | warning | info.
     *
     * @param  array<string, mixed>  $health
     * @param  array<string, int>  $storage
     * @return list<array{level: string, message: string}>
     */
    private function problems(array $health, int $errors24h, array $storage): array
    {
        $p = [];
        $prod = app()->isProduction();

        if (! $health['database']['ok']) {
            $p[] = ['level' => 'danger', 'message' => 'La base de données ne répond pas.'];
        }
        if (! $health['cache']['ok']) {
            $p[] = ['level' => 'danger', 'message' => 'Le cache ne fonctionne pas.'];
        }
        if (! $health['disk']['writable']) {
            $p[] = ['level' => 'danger', 'message' => "Le dossier storage/app/public n'est pas inscriptible : les photos ne peuvent pas être envoyées."];
        }
        if ($prod && $health['app']['debug']) {
            $p[] = ['level' => 'danger', 'message' => 'APP_DEBUG est activé en production : les erreurs affichent des détails internes.'];
        }
        if ($errors24h > 0) {
            $p[] = ['level' => 'warning', 'message' => "{$errors24h} erreur(s) serveur sur les dernières 24 h."];
        }
        if ($health['queue']['failed'] > 0) {
            $p[] = ['level' => 'warning', 'message' => $health['queue']['failed'].' tâche(s) en échec dans la file.'];
        }
        if (! $health['disk']['storageLinked']) {
            $p[] = ['level' => 'warning', 'message' => "Le lien public/storage est absent : lancez « php artisan storage:link »."];
        }
        if ($health['disk']['freeBytes'] !== null && $health['disk']['totalBytes']
            && $health['disk']['freeBytes'] / $health['disk']['totalBytes'] < 0.1) {
            $p[] = ['level' => 'warning', 'message' => 'Moins de 10 % d\'espace disque libre.'];
        }
        if ($prod && $health['database']['driver'] === 'sqlite') {
            $p[] = ['level' => 'warning', 'message' => 'SQLite est utilisé en production.'];
        }
        if ($prod && str_contains((string) config('app.url'), 'localhost')) {
            $p[] = ['level' => 'warning', 'message' => 'APP_URL pointe encore vers localhost : les URLs des photos seront fausses.'];
        }
        if ($prod && collect((array) config('cors.allowed_origins'))->contains(fn ($o) => str_contains((string) $o, 'localhost'))) {
            $p[] = ['level' => 'warning', 'message' => 'FRONTEND_URL contient encore localhost (CORS).'];
        }
        if ($storage['orphans'] > 0) {
            $p[] = ['level' => 'info', 'message' => $storage['orphans'].' photo(s) orpheline(s) peuvent être nettoyées.'];
        }

        return $p;
    }

    /** @return array<string, mixed> */
    private function traffic(Carbon $since, int $hours): array
    {
        $base = RequestLog::query()->where('created_at', '>=', $since);

        $t = (clone $base)->selectRaw(
            'count(*) as total,
             sum(case when status >= 200 and status < 300 then 1 else 0 end) as ok,
             sum(case when status >= 400 and status < 500 then 1 else 0 end) as e4,
             sum(case when status >= 500 then 1 else 0 end) as e5,
             avg(duration_ms) as avg_ms,
             count(distinct ip) as ips'
        )->first();

        $durations = (clone $base)->orderByDesc('id')->limit(20000)->pluck('duration_ms')->sort()->values();
        $p95 = $durations->isEmpty() ? 0 : (int) $durations[(int) floor(($durations->count() - 1) * 0.95)];

        // Requests per hour, with empty hours filled so the chart has no holes
        $expr = $this->hourExpr();
        $rows = (clone $base)
            ->selectRaw("{$expr} as h, count(*) as c, sum(case when status >= 500 then 1 else 0 end) as e")
            ->groupByRaw($expr)
            ->orderByRaw($expr)
            ->get()
            ->keyBy('h');

        $series = [];
        $start = now()->subHours($hours - 1)->startOfHour();
        for ($i = 0; $i < $hours; $i++) {
            $slot = $start->copy()->addHours($i);
            $row = $rows->get($slot->format('Y-m-d H:00:00'));
            $series[] = [
                'at' => $slot->getTimestamp() * 1000,
                'requests' => (int) ($row?->c ?? 0),
                'errors' => (int) ($row?->e ?? 0),
            ];
        }

        $routes = (clone $base)
            ->selectRaw('method, route, count(*) as c, avg(duration_ms) as avg_ms, sum(case when status >= 500 then 1 else 0 end) as e')
            ->groupBy('method', 'route')
            ->orderByDesc('c')
            ->limit(30)
            ->get()
            ->map(fn ($r) => [
                'method' => $r->method,
                'route' => $r->route,
                'count' => (int) $r->c,
                'avgMs' => round((float) $r->avg_ms, 1),
                'errors' => (int) $r->e,
            ]);

        return [
            'total' => (int) ($t->total ?? 0),
            'ok' => (int) ($t->ok ?? 0),
            'clientErrors' => (int) ($t->e4 ?? 0),
            'serverErrors' => (int) ($t->e5 ?? 0),
            'avgMs' => round((float) ($t->avg_ms ?? 0), 1),
            'p95Ms' => $p95,
            'uniqueIps' => (int) ($t->ips ?? 0),
            'series' => $series,
            'topRoutes' => $routes->take(8)->values()->all(),
            'slowest' => $routes->filter(fn ($r) => $r['count'] >= 3)->sortByDesc('avgMs')->take(5)->values()->all(),
        ];
    }

    /** SQL that rounds created_at down to the hour, as "YYYY-MM-DD HH:00:00". */
    private function hourExpr(): string
    {
        return match (DB::getDriverName()) {
            'pgsql' => "to_char(created_at, 'YYYY-MM-DD HH24:00:00')",
            'mysql', 'mariadb' => "date_format(created_at, '%Y-%m-%d %H:00:00')",
            default => "strftime('%Y-%m-%d %H:00:00', created_at)",
        };
    }

    /** @return array<string, mixed> */
    private function security(Carbon $since): array
    {
        $login = fn () => RequestLog::query()
            ->where('created_at', '>=', $since)
            ->where('route', 'api/auth/login');

        $byIp = $login()
            ->whereIn('status', [401, 429])
            ->selectRaw('ip, count(*) as c, max(created_at) as last_at')
            ->groupBy('ip')
            ->orderByDesc('c')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'ip' => $r->ip ?? 'inconnue',
                'count' => (int) $r->c,
                'lastAt' => Carbon::parse($r->last_at)->getTimestamp() * 1000,
            ])->values()->all();

        return [
            'successfulLogins' => $login()->where('status', 200)->count(),
            'failedLogins' => $login()->where('status', 401)->count(),
            'rateLimited' => $login()->where('status', 429)->count(),
            'deactivated' => $login()->where('status', 403)->count(),
            'adminDenied' => RequestLog::query()
                ->where('created_at', '>=', $since)
                ->where('route', 'like', 'api/admin/%')
                ->whereIn('status', [401, 403])
                ->count(),
            'byIp' => $byIp,
        ];
    }

    /** @return array<string, mixed> */
    private function errors(): array
    {
        return [
            'last24h' => ErrorLog::where('created_at', '>=', now()->subDay())->count(),
            'total' => ErrorLog::count(),
            'recent' => ErrorLog::orderByDesc('id')->limit(30)->get()->map(fn ($e) => [
                'id' => (string) $e->id,
                'at' => $e->created_at ? $e->created_at->getTimestamp() * 1000 : 0,
                'class' => $e->class,
                'message' => $e->message,
                'location' => $e->location,
                'method' => $e->method,
                'path' => $e->path,
                'trace' => $e->trace ?? '',
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function business(): array
    {
        $sales = Sale::query()->where('created_at', '>=', now()->subDay());

        return [
            'categories' => Category::count(),
            'products' => Product::count(),
            'activeProducts' => Product::where('active', true)->count(),
            'outOfStock' => Product::where('active', true)->where('stock', 0)->count(),
            'lowStock' => Product::where('active', true)->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_at')->count(),
            'sales24h' => (clone $sales)->count(),
            'revenue24h' => (float) (clone $sales)->sum('total'),
            'salesTotal' => Sale::count(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function team(): array
    {
        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', Admin::class)
            ->when(config('sanctum.expiration'), fn ($q, $m) => $q->where('created_at', '>=', now()->subMinutes((int) $m)))
            ->selectRaw('tokenable_id, count(*) as c, max(last_used_at) as last_used')
            ->groupBy('tokenable_id')
            ->get()
            ->keyBy('tokenable_id');

        return Admin::orderBy('id')->get()->map(function (Admin $a) use ($tokens) {
            $t = $tokens->get($a->id);

            return [
                'id' => (string) $a->id,
                'name' => $a->name,
                'phone' => $a->phone,
                'role' => $a->isSuperAdmin() ? 'super_admin' : 'admin',
                'active' => (bool) $a->active,
                'sessions' => (int) ($t?->c ?? 0),
                'lastSeen' => $t?->last_used ? Carbon::parse($t->last_used)->getTimestamp() * 1000 : null,
            ];
        })->values()->all();
    }
}