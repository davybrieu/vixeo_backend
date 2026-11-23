<?php

namespace App\Services\Social;

use App\Models\DirectMessage;
use App\Models\Profile;
use App\Models\ProfileList;
use App\Models\Report;
use App\Models\SocialComment;
use App\Models\TmdbRating;
use App\Services\Messaging\Conversations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reports and moderation (docs/moderation.md): a profile reports a profile, a review, a list, a
 * comment or a message it can see; the moderation hides it (or suspends the profile), dismisses
 * the report, or restores what it hid, with a reason given to the author and to who reported it.
 */
class Reports
{
    public const REASONS = [
        'spam' => 'Spam ou publicité',
        'harassment' => 'Harcèlement ou intimidation',
        'hate' => 'Discours haineux',
        'sexual' => 'Contenu sexuel',
        'violence' => 'Violence ou menaces',
        'spoiler' => 'Spoiler non signalé',
        'illegal' => 'Contenu illégal',
        'other' => 'Autre raison',
    ];

    public function __construct(
        private Audience $audience,
        private Conversations $conversations,
        private SocialNotifications $notifications,
    ) {}

    /** [reporter] reports a subject it can see (404 otherwise, 422 for its own). */
    public function create(Profile $reporter, string $type, int $id, string $reason, ?string $details): Report
    {
        [$subject, $author] = $this->subject($type, $id);
        abort_if($subject === null || $author === null, 404);
        abort_if((int) $author->id === (int) $reporter->id, 422, __('You cannot report yourself.'));
        $visible = $type === 'message'
            ? $this->conversations->isMember($reporter, (int) $subject->conversation_id)
            : $this->audience->canSee($reporter, $author);
        abort_unless($visible, 404);

        $report = Report::query()->updateOrCreate(
            ['reporter_profile_id' => $reporter->id, 'subject_type' => $type, 'subject_id' => $id, 'status' => Report::OPEN],
            ['author_profile_id' => $author->id, 'reason' => $reason, 'details' => $details],
        );
        if ($report->wasRecentlyCreated) {
            $this->alert($report);
        }

        return $report;
    }

    /**
     * The moderation hides the subject of [report] (a profile is suspended), and every open report
     * of it is closed. Its author and who reported it are told why.
     */
    public function hide(Report $report, string $decision): void
    {
        [$subject, $author] = $this->subject($report->subject_type, $report->subject_id);
        if ($subject instanceof Profile) {
            $subject->forceFill(['suspended_at' => now()])->save();
        } elseif ($subject instanceof DirectMessage) {
            $this->conversations->erase($subject);
        } elseif ($subject !== null) {
            $subject->forceFill(['hidden_at' => now()])->save();
        }
        $label = $this->label($report->subject_type, $subject);
        if ($author !== null) {
            $this->notifications->moderation($author, $report->subject_type === 'profile'
                ? "Votre profil a été suspendu par la modération : {$decision}"
                : "{$label} a été masqué par la modération : {$decision}");
        }
        $this->close($report, Report::HIDDEN, $decision, 'Merci pour votre signalement : le contenu a été retiré.');
    }

    /** Nothing against the rules: the report is closed, who reported it is told. */
    public function dismiss(Report $report, string $decision): void
    {
        $this->close($report, Report::DISMISSED, $decision, "Merci pour votre signalement. Après examen, le contenu reste en ligne : {$decision}");
    }

    /** What was hidden comes back (an appeal of its author). */
    public function restore(Report $report, string $decision): void
    {
        [$subject, $author] = $this->subject($report->subject_type, $report->subject_id);
        if ($subject instanceof Profile) {
            $subject->forceFill(['suspended_at' => null])->save();
        } elseif ($subject !== null && ! $subject instanceof DirectMessage) {
            $subject->forceFill(['hidden_at' => null])->save();
        }
        $report->forceFill(['status' => Report::DISMISSED, 'decision' => $decision, 'handled_at' => now()])->save();
        if ($author !== null) {
            $this->notifications->moderation($author, "{$this->label($report->subject_type, $subject)} est de nouveau visible : {$decision}");
        }
    }

    /**
     * What a report points to, and its author (null when deleted since).
     *
     * @return array{Model|null, Profile|null}
     */
    public function subject(string $type, int $id): array
    {
        $subject = match ($type) {
            'profile' => Profile::query()->whereKey($id)->where('is_kid', false)->first(),
            'review' => TmdbRating::query()->with('profile')->find($id),
            'list' => ProfileList::query()->with('profile')->find($id),
            'comment' => SocialComment::query()->with('profile')->find($id),
            'message' => DirectMessage::query()->find($id),
            default => null,
        };
        $author = match (true) {
            $subject instanceof Profile => $subject,
            $subject instanceof DirectMessage => Profile::query()->find($subject->sender_profile_id),
            $subject !== null => $subject->profile,
            default => null,
        };

        return [$subject, $author];
    }

    /** What the report is about, in a sentence: « Votre avis sur Dune ». */
    public function label(string $type, ?Model $subject): string
    {
        return match (true) {
            $subject instanceof TmdbRating => "Votre avis sur {$subject->title}",
            $subject instanceof ProfileList => "Votre liste « {$subject->title} »",
            $subject instanceof SocialComment => 'Votre commentaire « '.SocialNotifications::excerpt($subject->body, 40).' »',
            $subject instanceof DirectMessage => 'Votre message',
            default => 'Votre profil',
        };
    }

    /** A short text of what was reported, for the moderation (`vixeo:reports`). */
    public function excerpt(Report $report): string
    {
        [$subject] = $this->subject($report->subject_type, $report->subject_id);

        return match (true) {
            $subject instanceof Profile => "@{$subject->username} — {$subject->name} — ".Str::limit((string) $subject->bio, 60),
            $subject instanceof TmdbRating => "{$subject->title} ({$subject->value}/10) — ".Str::limit((string) $subject->comment, 80),
            $subject instanceof ProfileList => "{$subject->title} — ".Str::limit((string) $subject->description, 80),
            $subject instanceof SocialComment, $subject instanceof DirectMessage => Str::limit((string) $subject->body, 100),
            default => '(supprimé)',
        };
    }

    private function close(Report $report, string $status, string $decision, string $toReporters): void
    {
        $reports = Report::query()->where('subject_type', $report->subject_type)->where('subject_id', $report->subject_id)
            ->where('status', Report::OPEN)->with('reporter')->get();
        foreach ($reports->push($report)->unique('id') as $open) {
            $open->forceFill(['status' => $status, 'decision' => $decision, 'handled_at' => now()])->save();
            if ($open->reporter !== null) {
                $this->notifications->moderation($open->reporter, $toReporters);
            }
        }
    }

    /** Tells the moderation (VIXEO_CONTACT_EMAIL), after the response. */
    private function alert(Report $report): void
    {
        $to = config('vixeo.publisher.email');
        if (! is_string($to) || $to === '') {
            return;
        }
        $id = $report->id;
        dispatch(function () use ($id, $to) {
            $report = Report::query()->find($id);
            if ($report === null) {
                return;
            }
            try {
                $reports = app(self::class);
                Mail::raw(sprintf(
                    "Signalement n° %d (%s, %s)\n\n%s\n\nPrécisions : %s\n\nphp artisan vixeo:moderate %d hide|dismiss --reason=\"…\"",
                    $report->id, $report->subject_type, self::REASONS[$report->reason] ?? $report->reason,
                    $reports->excerpt($report), $report->details ?? '—', $report->id,
                ), fn ($message) => $message->to($to)->subject("[Vixeo] Signalement n° {$report->id}"));
            } catch (Throwable $e) {
                report($e);
            }
        })->afterResponse();
    }
}
