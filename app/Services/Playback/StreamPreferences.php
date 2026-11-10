<?php

namespace App\Services\Playback;

use App\Models\Profile;
use App\Models\ProfileStream;
use Illuminate\Support\Collection;

/**
 * Per-profile stream preferences for one content: the streams marked as favorites (❤, tried first)
 * and the last stream that actually worked (tried next). Kept apart from ProfileItem: a content is
 * not modified just because the viewer picked another stream. See docs/lecture.md.
 */
class StreamPreferences
{
    /**
     * The profile's preferences for a content, favorites first then the most recently working.
     *
     * @return Collection<int, ProfileStream>
     */
    public function forContent(Profile $profile, string $source, string $itemId): Collection
    {
        return $profile->streams()
            ->where('source', $source)
            ->where('item_id', $itemId)
            ->where(fn ($q) => $q->where('is_favorite', true)->orWhereNotNull('last_worked_at'))
            ->orderByDesc('is_favorite')
            ->orderByDesc('favorited_at')
            ->orderByDesc('last_worked_at')
            ->get();
    }

    /**
     * Mark (or unmark) a stream as favorite.
     */
    public function setFavorite(Profile $profile, string $source, string $itemId, string $streamId, bool $favorite): ProfileStream
    {
        $stream = $this->find($profile, $source, $itemId, $streamId);

        $stream->is_favorite = $favorite;
        $stream->favorited_at = $favorite ? ($stream->favorited_at ?? now()) : null;
        $stream->save();

        return $stream;
    }

    /**
     * Remember that a stream worked, so the player can try it first next time. A stream that was
     * reported dead is revived: it works again.
     */
    public function markWorked(Profile $profile, string $source, string $itemId, string $streamId, array $attributes = []): ProfileStream
    {
        $stream = $this->find($profile, $source, $itemId, $streamId);

        foreach (['stream_url', 'stream_type', 'extractor', 'quality', 'language'] as $field) {
            if (array_key_exists($field, $attributes) && $attributes[$field] !== null) {
                $stream->{$field} = $attributes[$field];
            }
        }

        $stream->last_worked_at = now();
        $stream->save();

        return $stream;
    }

    private function find(Profile $profile, string $source, string $itemId, string $streamId): ProfileStream
    {
        return $profile->streams()->firstOrNew([
            'source' => $source,
            'item_id' => $itemId,
            'stream_id' => $streamId,
        ]);
    }
}
