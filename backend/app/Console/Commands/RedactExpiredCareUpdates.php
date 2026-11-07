<?php

namespace App\Console\Commands;

use App\Models\CareUpdate;
use App\Models\MediaObject;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RedactExpiredCareUpdates extends Command
{
    protected $signature = 'care-updates:redact-expired {--batch=100}';

    protected $description = 'Redact care update content and remove expired private images';

    public function handle(): int
    {
        $batch = filter_var($this->option('batch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($batch === false) {
            $this->error('Batch size must be between 1 and 1000.');

            return self::FAILURE;
        }

        $cutoff = now('UTC')->subDays(90);
        $redacted = 0;
        $failed = 0;

        CareUpdate::query()->whereNull('redacted_at')->where('created_at', '<=', $cutoff)
            ->whereDoesntHave('booking.reports', function ($query): void {
                $query->where(function ($reports): void {
                    $reports->whereIn('status', ['OPEN', 'IN_REVIEW'])
                        ->orWhereNull('resolved_at')
                        ->orWhere('resolved_at', '>', now('UTC')->subDays(90));
                });
            })
            ->with('media')->orderBy('id')->chunkById((int) $batch, function ($updates) use (&$redacted, &$failed): void {
                foreach ($updates as $update) {
                    try {
                        $this->redact($update);
                        $redacted++;
                    } catch (Throwable $exception) {
                        $failed++;
                        Log::error('care_update.retention_redaction_failed', [
                            'care_update_id' => $update->id,
                            'exception' => $exception::class,
                        ]);
                    }
                }
            });

        $this->info("Redacted {$redacted} care update(s); {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function redact(CareUpdate $update): void
    {
        $media = $update->media;
        $deleteMedia = $media !== null && ! $this->mediaReferencedElsewhere($media, $update);

        if ($deleteMedia) {
            DB::table('media_objects')->where('id', $media->id)->update(['state' => 'PENDING_DELETE']);
            $disk = Storage::disk('local');
            if ($disk->exists($media->object_key) && ! $disk->delete($media->object_key)) {
                throw new \RuntimeException('Storage refused to delete expired care update image.');
            }
        }

        DB::transaction(function () use ($update, $deleteMedia): void {
            $locked = CareUpdate::query()->whereKey($update->id)->lockForUpdate()->first();
            if ($locked === null || $locked->redacted_at !== null) {
                return;
            }
            $locked->body = null;
            $locked->media_object_id = null;
            $locked->redacted_at = now('UTC');
            $locked->save();

            if ($deleteMedia) {
                MediaObject::query()->whereKey($update->media_object_id)
                    ->where('state', 'PENDING_DELETE')->delete();
            }
        }, 3);
    }

    private function mediaReferencedElsewhere(MediaObject $media, CareUpdate $current): bool
    {
        return DB::table('care_updates')->where('media_object_id', $media->id)->where('id', '<>', $current->id)->exists()
            || DB::table('message_attachments')->where('media_object_id', $media->id)->exists()
            || DB::table('pets')->where('photo_media_id', $media->id)->exists()
            || DB::table('listing_media')->where('media_object_id', $media->id)->exists();
    }
}
