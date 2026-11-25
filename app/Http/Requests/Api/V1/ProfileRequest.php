<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Profile;
use App\Services\AvatarCatalog;
use App\Services\CoverCatalog;
use App\Services\ParentalControl;
use App\Support\Languages;
use App\Support\SocialLinks;
use App\Support\Usernames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class ProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge(['username' => mb_strtolower((string) $this->input('username'))]);
        }
        // A handle with or without « @ », or the address of the page: kept as the handle.
        $links = $this->input('social_links');
        if (is_array($links)) {
            $this->merge(['social_links' => collect($links)->map(
                fn (mixed $value, mixed $network) => is_string($network) && array_key_exists($network, SocialLinks::NETWORKS)
                    ? SocialLinks::normalize($network, $value) : $value,
            )->all()]);
        }
    }

    public function rules(): array
    {
        $profile = $this->route('profile');
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [
                $required, 'string', 'max:50',
                Rule::unique('profiles', 'name')
                    ->where('user_id', $this->user()->id)
                    ->ignore($profile?->id),
            ],
            /** 3 to 30 letters, digits or _; never a page of the site or of the app (`Usernames::RESERVED`). */
            'username' => [
                'sometimes', 'required', 'string', 'min:3', 'max:30', 'regex:/^[a-zA-Z0-9_]+$/',
                Rule::notIn(Usernames::RESERVED),
                Rule::unique('profiles', 'username')->ignore($profile?->id),
            ],
            /**
             * Private account (like Twitter): its lists and reviews are only seen by the followers it
             * accepts; following it sends a request. Made public: the waiting requests are accepted.
             */
            'is_private' => ['sometimes', 'boolean'],
            /**
             * Accounts on social networks, by network (`instagram`, `x`, `tiktok`, `youtube`,
             * `letterboxd`, `threads`, `bluesky`: the handle, « @ » and the address of the page
             * accepted; `website`: an address). Only the networks given change; null removes one.
             */
            'social_links' => ['sometimes', 'nullable', 'array:'.implode(',', array_keys(SocialLinks::NETWORKS))],
            'social_links.*' => ['nullable', 'string', 'max:200', function (string $attribute, mixed $value, \Closure $fail) {
                $network = substr($attribute, strlen('social_links.'));
                if (is_string($value) && ! SocialLinks::valid($network, $value)) {
                    $fail($network === 'website' ? __('This address is not valid.') : __('This :network account is not valid.', [
                        'network' => SocialLinks::NETWORKS[$network]['label'] ?? $network,
                    ]));
                }
            }],
            /** Biography of the public profile: 160 characters at most. */
            'bio' => ['sometimes', 'nullable', 'string', 'max:'.Profile::BIO_MAX_LENGTH],
            /** Avatar id from GET /avatars. Null = initial letter. */
            'avatar' => ['sometimes', 'nullable', 'string', Rule::in(app(AvatarCatalog::class)->ids())],
            /** Cover id from GET /covers: the banner of the profile page, when it has no image of its own (`cover_url`). */
            'cover' => ['sometimes', 'string', Rule::in(app(CoverCatalog::class)->ids())],
            /** Kid profile. Without `max_age_rating`, its age limit is 10. */
            'is_kid' => ['sometimes', 'boolean'],
            /** Maximum age rating allowed for this profile (e.g. 12, 16). Null = no limit. */
            'max_age_rating' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:21'],
            /** With an age limit, also show titles whose age rating is unknown. Default false. */
            'allow_unrated' => ['sometimes', 'boolean'],
            /** Sources or folders hidden on this profile, e.g. "tv" (all live TV) or "tv#Sport" (100 max). */
            'locked_sources' => ['sometimes', 'nullable', 'array', 'list', 'max:100'],
            'locked_sources.*' => ['string', 'max:191', 'distinct'],
            /** 4-digit PIN protecting the profile. Send null to remove it. */
            'pin' => ['sometimes', 'nullable', 'string', 'digits:4'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:255'],
            /** Preferred audio language (ISO 639-1, e.g. "fr"). Null = original version. */
            'audio_language' => ['sometimes', 'nullable', 'string', Rule::in(Languages::AUDIO)],
            /** Preferred subtitles ("fr", "en", "pt-BR"…). Null = no subtitles. */
            'subtitle_language' => ['sometimes', 'nullable', 'string', Rule::in(Languages::SUBTITLES)],
            /** Hide episode artwork, titles and descriptions until the episode is selected. */
            'no_spoiler' => ['sometimes', 'boolean'],
            /** The player skips the opening credits by itself (else it shows a "Passer" button). */
            'skip_intro' => ['sometimes', 'boolean'],
            /** The player skips the recap ("Précédemment…") by itself. */
            'skip_recap' => ['sometimes', 'boolean'],
            /** The player skips the end credits by itself (goes to the next episode). */
            'skip_credits' => ['sometimes', 'boolean'],
            /** The player skips the preview of the next episode by itself. */
            'skip_preview' => ['sometimes', 'boolean'],
            /** At the end of a movie or an episode, the player proposes to rate it (default true). */
            'rate_after_watching' => ['sometimes', 'boolean'],
            /** The first launch (rate known titles, accounts to follow) was done or skipped. */
            'onboarded' => ['sometimes', 'accepted'],
            /** Parental PIN: required (when parental control is on) to create a profile or change its restrictions. */
            'parental_pin' => ['sometimes', 'nullable', 'string', 'digits:4'],
            /** Current PIN of the profile: required to change or remove it, unless `parental_pin` is given. */
            'current_pin' => ['sometimes', 'nullable', 'string', 'digits:4'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                $this->authorizeSensitiveChanges();
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    $validator->errors()->add($field, $messages[0]);
                }
            }
        }];
    }

    /** Profile attributes to save (PINs used for authorization are not stored). */
    public function profileData(): array
    {
        $data = $this->safe()->except(['parental_pin', 'current_pin', 'onboarded']);
        if ($this->has('onboarded')) {
            $data['onboarded_at'] = now();
        }
        if (isset($data['username'])) {
            $data['username'] = mb_strtolower($data['username']);
        }
        if (array_key_exists('social_links', $data)) {
            $profile = $this->route('profile');
            $data['social_links'] = SocialLinks::merge($profile instanceof Profile ? $profile->social_links : null, $data['social_links'] ?? []);
        }

        return $data;
    }

    private function authorizeSensitiveChanges(): void
    {
        $parental = app(ParentalControl::class);
        $user = $this->user();
        /** @var Profile|null $profile */
        $profile = $this->route('profile');

        // A new profile could have no restriction at all: parental PIN needed.
        if ($profile === null) {
            $parental->authorize($user, $this->input('parental_pin'));

            return;
        }

        if ($this->changesRestrictions($profile)) {
            $parental->authorize($user, $this->input('parental_pin'));
        }

        // Changing or removing a profile PIN: that PIN, or the parental PIN.
        if ($this->has('pin') && $profile->hasPin() && ! $profile->checkPin((string) $this->input('pin'))) {
            if ($this->filled('current_pin') && $profile->checkPin((string) $this->input('current_pin'))) {
                return;
            }

            if ($user->hasParentalControl() && $this->filled('parental_pin')) {
                $parental->authorize($user, $this->input('parental_pin'));

                return;
            }

            throw ValidationException::withMessages(['current_pin' => [__('Incorrect PIN.')]]);
        }
    }

    private function changesRestrictions(Profile $profile): bool
    {
        foreach (Profile::RESTRICTION_FIELDS as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $new = $this->input($field);
            $current = $profile->getAttribute($field);

            if ($field === 'locked_sources') {
                $new = array_values($new ?? []);
                $current = array_values($current ?? []);
                sort($new);
                sort($current);
            } elseif ($field === 'max_age_rating') {
                $new = $new === null ? null : (int) $new;
            } else {
                $new = filter_var($new, FILTER_VALIDATE_BOOLEAN);
            }

            if ($new !== $current) {
                return true;
            }
        }

        return false;
    }
}
