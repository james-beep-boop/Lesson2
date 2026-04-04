<?php

namespace App\Support;

class GuideContent
{
    /**
     * Returns the guide sections for the given language.
     * Each section has: 'title', 'roles' (null = all), 'body' (markdown string).
     */
    public static function sections(string $lang = 'en'): array
    {
        return $lang === 'sw' ? static::swahili() : static::english();
    }

    private static function english(): array
    {
        return [
            [
                'title' => 'Viewing Lessons',
                'roles' => null,
                'body' => "- Browse lessons from the **Lessons** menu.\n"
                    ."- Click a lesson row to open its detail page.\n"
                    ."- The **official version** is highlighted in green — it is the school's approved edition.\n"
                    .'- Click any version in the left sidebar to switch between versions.',
            ],
            [
                'title' => 'Comparing Versions',
                'roles' => null,
                'body' => "- On the lesson page, use **Compare to Previous Version** or **Compare to Official Version** for instant diff shortcuts.\n"
                    ."- Or choose any version from the Compare section in the left sidebar.\n"
                    ."- Additions are highlighted in **green**, deletions in **pink**.\n"
                    .'- Toggle between **Side-by-Side** and **Stacked** layouts using the button in compare mode.',
            ],
            [
                'title' => 'Favorites',
                'roles' => null,
                'body' => "- Click **Mark as Favorite** (★) on any version to save it to your personal list.\n"
                    ."- You can only favorite one version per lesson family.\n"
                    .'- A warning appears if your favorited version differs from the current official version.',
            ],
            [
                'title' => 'Messaging',
                'roles' => null,
                'body' => "- Use **Message About This Lesson** to contact another user with full lesson context pre-filled.\n"
                    ."- Choose: message the author, subject administrator, site administrator, or any user.\n"
                    ."- Messages appear in your **Inbox** (accessible from the user menu, top right).\n"
                    .'- Click any message row to read it; you can reply from the message view.',
            ],
            [
                'title' => 'Print & Export',
                'roles' => null,
                'body' => "- **Print**: opens your browser's print dialog with a clean layout.\n"
                    ."- **Download PDF**: saves the selected version as a PDF file.\n"
                    .'- **Email PDF**: enter any email address to send the lesson plan as a PDF attachment.',
            ],
            [
                'title' => 'Editing & Saving a New Version',
                'roles' => ['editor', 'subject_admin', 'site_administrator'],
                'body' => "- Click **Edit This Plan** to enter edit mode.\n"
                    ."- Use the **View Lesson / Edit Lesson** tabs to switch between preview and source.\n"
                    ."- In the preview tab, **select text** then click **Edit Selected Text** to jump to that location in the source.\n"
                    ."- Choose a version bump: **Patch** (small fix), **Minor** (new content), or **Major** (complete rewrite).\n"
                    .'- Add an optional revision note, then click **Save Edits** to create the new version.',
            ],
            [
                'title' => 'Official Versions',
                'roles' => ['subject_admin', 'site_administrator'],
                'body' => "- The official version is the school's approved edition for a lesson.\n"
                    ."- Click **Mark as Official** on any version to promote it.\n"
                    .'- Only one version per lesson family can be official at a time.',
            ],
            [
                'title' => 'Deletion Requests',
                'roles' => ['subject_admin', 'site_administrator'],
                'body' => "- Click **Request Deletion** on a version to flag it for removal.\n"
                    ."- A Site Administrator must approve the request before the version is deleted.\n"
                    .'- The contributor and all site administrators are notified by inbox message when a request is submitted.',
            ],
            [
                'title' => 'Administration',
                'roles' => ['site_administrator'],
                'body' => "- From the **Admin Panel** (top menu), manage subjects, grades, users, and pending deletion requests.\n"
                    ."- Assign roles: set a Subject Administrator for each subject/grade, or add editors via the **Team** page.\n"
                    .'- Approve or reject deletion requests from the Deletion Requests admin resource.',
            ],
        ];
    }

    private static function swahili(): array
    {
        return [
            [
                'title' => 'Kutazama Masomo',
                'roles' => null,
                'body' => "- Tazama masomo kutoka menyu ya **Masomo**.\n"
                    ."- Bonyeza mstari wa somo ili kufungua ukurasa wake.\n"
                    ."- **Toleo rasmi** linaonyeshwa kwa rangi ya kijani — hilo ndilo toleo lililoidhinishwa na shule.\n"
                    .'- Bonyeza toleo lolote kwenye orodha ya kushoto kubadilisha matoleo.',
            ],
            [
                'title' => 'Kulinganisha Matoleo',
                'roles' => null,
                'body' => "- Kwenye ukurasa wa somo, tumia **Linganisha na Toleo la Awali** au **Linganisha na Toleo Rasmi** kwa haraka.\n"
                    ."- Au chagua toleo lolote kutoka sehemu ya Linganisha kwenye orodha ya kushoto.\n"
                    ."- Nyongeza zinaonyeshwa kwa **kijani**, mafutio kwa **waridi**.\n"
                    .'- Badilisha kati ya mpangilio wa **Pembeni-Kwa-Pembeni** na **Umewekwa juu ya mmoja** kwa kutumia kitufe.',
            ],
            [
                'title' => 'Vipendwa',
                'roles' => null,
                'body' => "- Bonyeza **Ongeza kwenye Vipendwa** (★) kwenye toleo lolote kuihifadhi kwenye orodha yako.\n"
                    ."- Unaweza tu kupenda toleo moja kwa kila familia ya somo.\n"
                    .'- Onyo linaonekana ikiwa toleo unalolipenda linatofautiana na toleo rasmi la sasa.',
            ],
            [
                'title' => 'Ujumbe',
                'roles' => null,
                'body' => "- Tumia **Tuma Ujumbe Kuhusu Somo Hili** kuwasiliana na mtumiaji mwingine pamoja na muktadha wa somo uliojazwa mapema.\n"
                    ."- Chagua: tuma ujumbe kwa mwandishi, msimamizi wa somo, msimamizi wa tovuti, au mtumiaji yeyote.\n"
                    ."- Ujumbe unaonekana kwenye **Kisanduku cha Barua** chako (unaopatikana kwenye menyu ya mtumiaji, kona ya juu kulia).\n"
                    .'- Bonyeza mstari wowote wa ujumbe kuusoma; unaweza kujibu kutoka kwenye mtazamo wa ujumbe.',
            ],
            [
                'title' => 'Chapisha na Hamisha',
                'roles' => null,
                'body' => "- **Chapisha**: hufungua mazungumzo ya kuchapisha ya kivinjari chako kwa mpangilio safi.\n"
                    ."- **Pakua PDF**: huhifadhi toleo lililochaguliwa kama faili la PDF.\n"
                    .'- **Tuma PDF kwa Barua Pepe**: ingiza anwani yoyote ya barua pepe kutuma mpango wa somo kama kiambatisho cha PDF.',
            ],
            [
                'title' => 'Kuhariri na Kuhifadhi Toleo Jipya',
                'roles' => ['editor', 'subject_admin', 'site_administrator'],
                'body' => "- Bonyeza **Hariri Mpango Huu** kuingia katika hali ya kuhariri.\n"
                    ."- Tumia vichupo **Tazama Somo / Hariri Somo** kubadilisha kati ya hakikisho na chanzo.\n"
                    ."- Katika kichupo cha hakikisho, **chagua maandishi** kisha bonyeza **Hariri Maandishi Yaliyochaguliwa** kuruka mahali hapo kwenye chanzo.\n"
                    ."- Chagua aina ya kuongeza toleo: **Kiraka** (marekebisho madogo), **Ndogo** (maudhui mapya), au **Kubwa** (uandishi upya kamili).\n"
                    .'- Ongeza dokezo la marekebisho (hiari), kisha bonyeza **Hifadhi Mabadiliko** kuunda toleo jipya.',
            ],
            [
                'title' => 'Matoleo Rasmi',
                'roles' => ['subject_admin', 'site_administrator'],
                'body' => "- Toleo rasmi ni toleo lililoidhinishwa na shule kwa somo.\n"
                    ."- Bonyeza **Fanya Rasmi** kwenye toleo lolote kulikuza.\n"
                    .'- Toleo moja tu kwa kila familia ya somo linaweza kuwa rasmi wakati mmoja.',
            ],
            [
                'title' => 'Maombi ya Kufuta',
                'roles' => ['subject_admin', 'site_administrator'],
                'body' => "- Bonyeza **Omba Kufutwa** kwenye toleo kuliashiria kwa kuondolewa.\n"
                    ."- Msimamizi wa Tovuti lazima aidhinishe ombi kabla toleo halijafutwa.\n"
                    .'- Mchango na wasimamizi wote wa tovuti wanataarifiwa kwa ujumbe wa sanduku la barua wakati ombi linawasilishwa.',
            ],
            [
                'title' => 'Utawala',
                'roles' => ['site_administrator'],
                'body' => "- Kutoka **Paneli ya Msimamizi** (menyu ya juu), simamia masomo, madarasa, watumiaji, na maombi ya kufuta yanayosubiri.\n"
                    ."- Panga majukumu: weka Msimamizi wa Somo kwa kila somo/darasa, au ongeza wahariri kupitia ukurasa wa **Timu**.\n"
                    .'- Idhinisha au kataa maombi ya kufuta kutoka kwa rasilimali ya Msimamizi ya Maombi ya Kufuta.',
            ],
        ];
    }
}
