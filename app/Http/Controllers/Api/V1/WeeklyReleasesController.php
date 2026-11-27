<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProfile;
use App\Services\Tmdb\AgeGate;
use App\Services\Tmdb\WeeklyReleases;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Worldwide calendar of movies, new TV shows and individual episodes.
 *
 * @tags Catalogue
 */
class WeeklyReleasesController extends Controller
{
    public function __construct(
        private WeeklyReleases $releases,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['sometimes', 'in:all,movie,tv,episode'],
        ]);

        $from = CarbonImmutable::parse($validated['from'], 'UTC');
        $to = CarbonImmutable::parse($validated['to'], 'UTC');
        abort_if($from->diffInDays($to) > 6, 422, 'La période doit couvrir au maximum sept jours.');

        $items = $this->releases->between(
            $from->toDateString(),
            $to->toDateString(),
            $validated['type'] ?? 'all',
        );

        return response()->json([
            'data' => $items,
            'meta' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'count' => count($items),
                'worldwide' => true,
                'completeness' => 'TMDb data only; episode coverage depends on known/discovered series and published air dates.',
            ],
        ]);
    }
}
