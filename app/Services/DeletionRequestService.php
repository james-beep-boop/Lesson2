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
            $version->loadMissing('family.subjectGrade.subject');

            $deletionRequest = new DeletionRequest([
                'lesson_plan_version_id' => $version->id,
                'reason' => $reason,
            ]);
            $deletionRequest->requested_by_user_id = $requestedBy->id;
            $deletionRequest->save();

            $subject = 'Deletion request: version '.$version->version;
            $body = $requestedBy->username.' has requested deletion of '
                .$this->planLabel($version).'.'."\n\n"
                .'Lesson plan ID: '.$version->lesson_plan_family_id."\n"
                .($reason ? "\n".'Reason: '.$reason."\n" : '')
                ."\n".'[deletion_request:'.$deletionRequest->id.']';

            // Collect recipient IDs to avoid sending duplicate messages.
            $notifiedIds = [];

            // Notify contributor (if different from requestedBy).
            if ($version->contributor_id && $version->contributor_id !== $requestedBy->id) {
                $this->sendMessage($version->contributor_id, $requestedBy->id, $subject, $body);
                $notifiedIds[] = $version->contributor_id;
            }

            // Notify Subject Admin (if exists, not the requester, and not already notified).
            $subjectAdminId = $version->family?->subjectGrade?->subject_admin_user_id;
            if ($subjectAdminId && $subjectAdminId !== $requestedBy->id && ! in_array($subjectAdminId, $notifiedIds)) {
                $this->sendMessage($subjectAdminId, $requestedBy->id, $subject, $body);
                $notifiedIds[] = $subjectAdminId;
            }

            // Notify all Site Admins (always, even when Subject Admin exists).
            $siteAdmins = User::role('site_administrator')->get();
            foreach ($siteAdmins as $admin) {
                if ($admin->id !== $requestedBy->id && ! in_array($admin->id, $notifiedIds)) {
                    $this->sendMessage($admin->id, $requestedBy->id, $subject, $body);
                    $notifiedIds[] = $admin->id;
                }
            }

            return $deletionRequest;
        });
    }

    private function sendMessage(int $toId, int $fromId, string $subject, string $body): void
    {
        $message = new Message([
            'to_user_id' => $toId,
            'subject' => $subject,
            'body' => $body,
        ]);
        $message->from_user_id = $fromId;
        $message->save();
    }

    private function planLabel(LessonPlanVersion $version): string
    {
        $family = $version->family;
        $subjectGrade = $family?->subjectGrade;
        $subjectName = $subjectGrade?->subject?->name;

        if (! $family || ! $subjectGrade || ! $subjectName) {
            return 'version '.$version->version;
        }

        return $subjectName
            .' Grade '.$subjectGrade->grade
            .' Day '.$family->day
            .' version '.$version->version;
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
                $family = $version->family;
                $version->delete();

                if (! $family) {
                    return;
                }

                if ($family->versions()->doesntExist()) {
                    $family->delete();

                    return;
                }

                if ((int) $family->official_version_id === $version->id) {
                    $versionService = app(VersionService::class);

                    $versionService->setOfficialVersion(
                        $family,
                        $versionService->preferredOfficialVersion($family, $version->id)
                    );
                }
            }
        });
    }
}
