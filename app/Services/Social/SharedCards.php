<?php

namespace App\Services\Social;

use App\Models\Profile;
use App\Models\ProfileList;
use App\Models\TmdbRating;
use App\Services\Tmdb\TmdbFormat as F;

/**
 * A title, a review or a list sent in a message (« Recommander à un ami », docs/messagerie.md):
 * what the conversation shows of it, kept with the message when it is sent. The sender must see it
 * (404 otherwise); the recipient opens it from the card.
 */
class SharedCards
{
    public function __construct(private TitleSnapshots $snapshots, private Audience $audience) {}

    /** @return array<string, mixed> */
    public function snapshot(Profile $sender, string $type, string $id): array
    {
        return match ($type) {
            'title' => $this->title($id),
            'review' => $this->review($sender, $id),
            default => $this->list($sender, $id),
        };
    }

    /** @return array<string, mixed> */
    private function title(string $id): array
    {
        abort_if(TitleSnapshots::parse($id) === null, 422, __('Unknown title.'));
        $snapshot = $this->snapshots->of([$id])[$id] ?? abort(404);

        $card = TitleSnapshots::card($snapshot);

        return [...$card, 'type' => 'title', 'media_type' => $card['type']];
    }

    /** @return array<string, mixed> */
    private function review(Profile $sender, string $id): array
    {
        $review = ctype_digit($id) ? TmdbRating::query()->with('profile')->whereNull('hidden_at')->find((int) $id) : null;
        abort_if($review === null || ! $this->audience->canSee($sender, $review->profile), 404);

        return [
            'type' => 'review',
            'review_id' => (int) $review->id,
            'item_id' => $review->titleId(),
            'title' => (string) $review->title,
            'season_number' => $review->season_number,
            'episode_number' => $review->episode_number,
            'poster_url' => F::image($review->poster_path, 'w342'),
            'value' => (float) $review->value,
            'excerpt' => $review->contains_spoilers ? null : SocialNotifications::excerpt($review->comment, 140),
            'author' => SocialCards::identity($review->profile),
        ];
    }

    /** @return array<string, mixed> */
    private function list(Profile $sender, string $id): array
    {
        $list = ctype_digit($id) ? ProfileList::query()->with(['profile', 'items' => fn ($q) => $q->where('position', '<', 4)])
            ->whereNull('hidden_at')->find((int) $id) : null;
        abort_if($list === null || ! $this->audience->canSee($sender, $list->profile), 404);

        return [
            'type' => 'list',
            'list_id' => (int) $list->id,
            'title' => (string) $list->title,
            'kind' => (string) $list->kind,
            'items_count' => (int) $list->items_count,
            'cover' => $list->items->map(fn ($item) => F::image($item->poster_path, 'w342'))->filter()->values()->all(),
            'author' => SocialCards::identity($list->profile),
        ];
    }
}
