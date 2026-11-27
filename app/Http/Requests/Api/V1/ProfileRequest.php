<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Profile;
use App\Services\AvatarCatalog;
use App\Services\CoverCatalog;
use App\Support\Languages;
use App\Support\SocialLinks;
use App\Support\Usernames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge(['username' => mb_strtolower((string) $this->input('username'))]);
        }

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
        return [
            'name' => [
                Rule::requiredIf($this->isMethod('post') || $this->boolean('registration_completed')), 'string', 'max:50',
                Rule::unique('profiles', 'name')->where('user_id', $this->user()->id)->ignore($profile?->id),
            ],
            'username' => [
                Rule::requiredIf($this->boolean('registration_completed')), 'required', 'string', 'min:3', 'max:30', 'regex:/^[a-zA-Z0-9_]+$/',
                Rule::notIn(Usernames::RESERVED),
                Rule::unique('profiles', 'username')->ignore($profile?->id),
            ],
            'is_private' => ['sometimes', 'boolean'],
            'social_links' => ['sometimes', 'nullable', 'array:'.implode(',', array_keys(SocialLinks::NETWORKS))],
            'social_links.*' => ['nullable', 'string', 'max:200', function (string $attribute, mixed $value, \Closure $fail) {
                $network = substr($attribute, strlen('social_links.'));
                if (is_string($value) && ! SocialLinks::valid($network, $value)) {
                    $fail($network === 'website' ? __('This address is not valid.') : __('This :network account is not valid.', [
                        'network' => SocialLinks::NETWORKS[$network]['label'] ?? $network,
                    ]));
                }
            }],
            'bio' => ['sometimes', 'nullable', 'string', 'max:'.Profile::BIO_MAX_LENGTH],
            'avatar' => [Rule::requiredIf($this->boolean('registration_completed')), 'string', Rule::in(app(AvatarCatalog::class)->ids())],
            'cover' => [Rule::requiredIf($this->boolean('registration_completed')), 'sometimes', 'string', Rule::in(app(CoverCatalog::class)->ids())],
            'position' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'audio_language' => ['sometimes', 'nullable', 'string', Rule::in(Languages::AUDIO)],
            'subtitle_language' => ['sometimes', 'nullable', 'string', Rule::in(Languages::SUBTITLES)],
            'no_spoiler' => ['sometimes', 'boolean'],
            'skip_intro' => ['sometimes', 'boolean'],
            'skip_recap' => ['sometimes', 'boolean'],
            'skip_credits' => ['sometimes', 'boolean'],
            'skip_preview' => ['sometimes', 'boolean'],
            'rate_after_watching' => ['sometimes', 'boolean'],
            'onboarded' => ['sometimes', 'accepted'],
            'registration_completed' => ['sometimes', 'accepted'],
        ];
    }

    /** Profile attributes to save. */
    public function profileData(): array
    {
        $data = $this->safe()->except(['onboarded', 'registration_completed']);
        if ($this->has('onboarded')) {
            $data['onboarded_at'] = now();
        }
        if ($this->has('registration_completed')) {
            $data['registration_completed_at'] = now();
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
}
