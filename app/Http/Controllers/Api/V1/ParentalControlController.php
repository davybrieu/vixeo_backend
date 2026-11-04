<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ParentalPinRequest;
use App\Http\Requests\Api\V1\VerifyParentalPinRequest;
use App\Services\ParentalControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Account-wide parental PIN. When set, it is required to change profile restrictions,
 * create or delete profiles, and remove another profile's PIN. See docs/controle-parental.md.
 *
 * @tags Contrôle parental
 */
class ParentalControlController extends Controller
{
    public function __construct(private ParentalControl $parental) {}

    /**
     * Parental control status.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            /** True when a parental PIN is set. */
            'enabled' => $request->user()->hasParentalControl(),
        ]);
    }

    /**
     * Set or change the parental PIN.
     *
     * Turns parental control on. Changing it requires `current_pin` or `password`.
     */
    public function update(ParentalPinRequest $request): Response
    {
        $this->parental->setPin($request->user(), $request->validated('pin'));

        return response()->noContent();
    }

    /**
     * Turn parental control off.
     *
     * Turns parental control off. Requires `current_pin` or `password`.
     */
    public function destroy(ParentalPinRequest $request): Response
    {
        $this->parental->setPin($request->user(), null);

        return response()->noContent();
    }

    /**
     * Check the parental PIN.
     *
     * 204 if correct, 422 if not, 429 after 5 wrong attempts (5 minutes).
     * Used by the app to unlock a screen (leave a kid profile, open a locked folder…).
     */
    public function verify(VerifyParentalPinRequest $request): Response
    {
        if (! $request->user()->hasParentalControl() || ! $this->parental->check($request->user(), $request->validated('pin'))) {
            throw ValidationException::withMessages(['pin' => [__('Incorrect parental PIN.')]]);
        }

        return response()->noContent();
    }
}
