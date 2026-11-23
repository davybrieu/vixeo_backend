<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesSocialProfiles;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProfile;
use App\Models\Profile;
use App\Models\Report;
use App\Services\Social\Reports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reports (docs/moderation.md).
 *
 * @tags Social
 */
class ReportController extends Controller
{
    use ResolvesSocialProfiles;

    /**
     * The reasons to report.
     *
     * `reason` and the text to show, in order.
     */
    public function reasons(): JsonResponse
    {
        return response()->json(['data' => collect(Reports::REASONS)->map(fn (string $label, string $reason) => [
            'reason' => $reason,
            'label' => $label,
        ])->values()]);
    }

    /**
     * Report a profile, a review, a list, a comment or a message.
     *
     * The moderation examines it; who reported it is told the decision (« Notifications »). Again
     * for the same thing: the open report is updated. 404: something the active profile can't see.
     */
    public function store(Request $request, Reports $reports): JsonResponse
    {
        $profile = ResolveProfile::profile($request);
        abort_unless($profile instanceof Profile, 422, __('Choose a profile.'));
        $validated = $request->validate([
            /** profile, review, list, comment or message. */
            'type' => ['required', 'string', Rule::in(Report::SUBJECTS)],
            /** Its id (the profile's `id`, the `review_id`…). */
            'id' => ['required', 'integer', 'min:1'],
            /** spam, harassment, hate, sexual, violence, spoiler, illegal or other. */
            'reason' => ['required', 'string', Rule::in(Report::REASONS)],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);
        $report = $reports->create($profile, (string) $validated['type'], (int) $validated['id'], (string) $validated['reason'], $validated['details'] ?? null);

        return response()->json(['data' => ['id' => (int) $report->id, 'status' => (string) $report->status]], 201);
    }
}
