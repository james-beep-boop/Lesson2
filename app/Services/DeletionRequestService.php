<?php

namespace App\Services;

use App\Models\DeletionRequest;
use App\Models\LessonPlanVersion;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeletionRequestService
{
    /**
     * Create a deletion request and notify the contributor + all Site Admins.
     */
    public function request(LessonPlanVersion $version, User $requestedBy, ?string $reason = null): DeletionRequest
    {
        return DB::transaction(function () use ($version, $requestedBy, $reason) {
            $deletionRequest = DeletionRequest::create([
                'lesson_plan_version_id' => $version->id,
                'requested_by_user_id' => $requestedBy->id,
                'reason' => $reason,
            ]);

            $subject = 'Deletion request: version ' . $version->version;
            $body = $requestedBy->username . ' has requested deletion of version '
                . $version->version . ' of lesson plan ID ' . $version->lesson_plan_family_id . ".\n\n"
                . ($reason ? 'Reason: ' . $reason : '');

            // Notify contributor (if different from requestedBy).
            if ($version->contributor_id !== $requestedBy->id) {
                Message::create([
                    'from_user_id' => $requestedBy->id,
                    'to_user_id' => $version->contributor_id,
                    'subject' => $subject,
                    'body' => $body,
                ]);
            }

            // Notify all Site Admins.
            $siteAdmins = User::role('site_administrator')->get();
            foreach ($siteAdmins as $admin) {
                if ($admin->id !== $requestedBy->id) {
                    Message::create([
                        'from_user_id' => $requestedBy->id,
                        'to_user_id' => $admin->id,
                        'subject' => $subject,
                        'body' => $body,
                    ]);
                }
            }

            return $deletionRequest;
        });
    }

    /**
     * Hard-delete a version and mark the request resolved (Site Admin action).
     */
    public function resolve(DeletionRequest $deletionRequest, User $resolvedBy): void
    {
        DB::transaction(function () use ($deletionRequest, $resolvedBy) {
            $deletionRequest->update([
                'resolved_at' => now(),
                'resolved_by_user_id' => $resolvedBy->id,
            ]);

            $version = $deletionRequest->version;
            if ($version) {
                // Clear official_version_id if this was the official version.
                $family = $version->family;
                if ($family && (int) $family->official_version_id === $version->id) {
                    $family->update(['official_version_id' => null]);
                }
                $version->delete();
            }
        });
    }
}
