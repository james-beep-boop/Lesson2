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
            $deletionRequest = new DeletionRequest([
                'lesson_plan_version_id' => $version->id,
                'reason' => $reason,
            ]);
            $deletionRequest->requested_by_user_id = $requestedBy->id;
            $deletionRequest->save();

            $subject = 'Deletion request: version '.$version->version;
            $body = $requestedBy->username.' has requested deletion of version '
                .$version->version.' of lesson plan ID '.$version->lesson_plan_family_id.".\n\n"
                .($reason ? 'Reason: '.$reason : '');

            // Notify contributor (if different from requestedBy).
            if ($version->contributor_id !== $requestedBy->id) {
                $message = new Message([
                    'to_user_id' => $version->contributor_id,
                    'subject' => $subject,
                    'body' => $body,
                ]);
                $message->from_user_id = $requestedBy->id;
                $message->save();
            }

            // Notify all Site Admins.
            $siteAdmins = User::role('site_administrator')->get();
            foreach ($siteAdmins as $admin) {
                if ($admin->id !== $requestedBy->id) {
                    $message = new Message([
                        'to_user_id' => $admin->id,
                        'subject' => $subject,
                        'body' => $body,
                    ]);
                    $message->from_user_id = $requestedBy->id;
                    $message->save();
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
            $deletionRequest->resolved_at = now();
            $deletionRequest->resolved_by_user_id = $resolvedBy->id;
            $deletionRequest->save();

            $version = $deletionRequest->version;
            if ($version) {
                // Clear official_version_id if this was the official version.
                $family = $version->family;
                if ($family && (int) $family->official_version_id === $version->id) {
                    $family->official_version_id = null;
                    $family->save();
                }
                $version->delete();
            }
        });
    }
}
