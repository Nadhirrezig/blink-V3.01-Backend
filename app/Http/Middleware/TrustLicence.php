<?php

namespace App\Http\Middleware;

use App\Services\ProjectService\ProjectService;
use App\Traits\ApiResponse;
use Artisan;
use Closure;
use Http;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;

class u
{
    use ApiResponse;

    const TTL = 604800; // 7 days

    protected array $allowRoutes = [
        'api/v1/install/*',
        'api/v1/rest/*',
        'api/v1/dashboard/galleries/*',
        'api/v1/auth/*',
        'api/v1/webhook/*',
    ];

	/**
	 * Handle an incoming request.
	 *
	 * @param Request $request
	 * @param Closure $next
	 * @return RedirectResponse|Response|mixed|void
	 * @throws InvalidArgumentException
	 */
    public function handle(Request $request, Closure $next)
    {
        $response = Cache::remember('tvoirifgjn.seirvjrc', self::TTL, function () {
            $response = (new ProjectService)->activationKeyCheck();
            $response = json_decode($response);

            if (
                isset($response->key) &&
                $response->key == config('credential.purchase_code') &&
                $response->active
            ) {
                return $response;
            }

            return null;
        });

        if (($response != null && optional($response)->local)) {

            try {
                if (!empty(Cache::get('block-ips'))) {
                    Cache::delete('block-ips');
                    Artisan::call('optimize:clear');
                }
            } catch (Throwable|InvalidArgumentException) {}

            return $next($request);
        }

        $host = str_replace(['https://', 'http://'], '', data_get($response, 'host'));

        if ($request->is($this->allowRoutes) || $host == request()->getHost()) {

            try {
                if (!empty(Cache::get('block-ips'))) {
                    Cache::delete('block-ips');
                    Artisan::call('optimize:clear');
                }
            } catch (Throwable|InvalidArgumentException) {}

            return $next($request);
        } else if (!$request->is($this->allowRoutes)) {
            $ips = collect(Cache::get('block-ips'));
            try {
                Cache::set('block-ips', $ips->merge([$request->ip()])->unique(), 86600000000);
            } catch (InvalidArgumentException) {}
            $this->sendMsg($response);
            abort(403);
        }

        $this->sendMsg($response);
    }

    /**
     * @param $response
     * @return void
     */
    public function sendMsg($response): void
    {
		Cache::set('tg-send-licence', 'true', 900);
    }

}
