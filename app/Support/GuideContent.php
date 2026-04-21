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
                'body' => "- Browse lessons from the **Lessons** menu and click on a lesson to view it.\n"
                    ."- The official version is highlighted with a green checkmark — it is the school's approved edition.",
            ],
            [
                'title' => 'Editing Lessons',
                'roles' => ['editor', 'subject_admin', 'site_administrator'],
                'body' => "- Click **Edit This Plan** to enter edit mode.\n"
                    ."- Make your edits in the edit window.\n"
                    ."- Pasting from Word or Google Docs? Use **Paste as Plain Text** (Ctrl+Shift+V / Cmd+Shift+V) to avoid formatting problems.\n"
                    ."- Choose a version bump: **Patch** (small fix), **Minor** (new content), or **Major** (complete rewrite).\n"
                    .'- Add an optional revision note, then click **Save Edits** to create the new version.',
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
                'title' => 'Official Versions',
                'roles' => ['subject_admin', 'site_administrator'],
                'body' => "- The official version is the school's approved edition for a lesson.\n"
                    .'- Only one version per lesson family can be official at a time.',
            ],
            [
                'title' => 'Favorites',
                'roles' => null,
                'body' => "- Click **Mark as Favorite** (★) on any version to save it to your personal list.\n"
                    ."- You can only favorite one version per lesson family.\n"
                    .'- A warning appears if your favorited version differs from the current official version.',
            ],
            [
                'title' => 'Messaging Other Users',
                'roles' => null,
                'body' => "- Use **Message About This Lesson** to contact another user about a lesson.\n"
                    ."- Messages to you appear in your **Inbox** (accessible from the user menu, top right).\n"
                    .'- Click any message row to read it; you can reply from the message view.',
            ],
            [
                'title' => 'Print & Export',
                'roles' => null,
                'body' => "- **Print**: opens your browser's print dialog with a clean layout.\n"
                    ."- **Save PDF**: saves the selected version as a PDF file.\n"
                    ."- **Email PDF**: enter any email address to send the lesson plan as a PDF attachment.\n"
                    ."- **Save .docx**: saves the selected version as a Word (.docx) file.\n"
                    .'- **Email .docx**: enter any email address to send the lesson plan as a .docx attachment.',
            ],
            [
                'title' => 'Deletion Requests',
                'roles' => ['subject_admin', 'site_administrator'],
                'body' => "- Click **Request Deletion** on a version to flag it for removal.\n"
                    ."- A Site Administrator must approve the request before the version is deleted.\n"
                    .'- The contributor and all site administrators are notified by inbox message when a request is submitted.',
            ],
            [
                'title' => 'User Types and Permissions',
                'roles' => null,
                'body' => "- **Teachers** can browse lessons, compare versions, favorite versions, message other users, translate to Swahili, print, and export.\n"
                    ."- **Editors** can do all of that, edit lesson plans within their assigned subject and grade, and use the **Ask AI** function.\n"
                    ."- **Subject Administrators** can do all editor actions for their subject-grades, mark a version official, request deletion, and promote teachers to be editors.\n"
                    ."- **Site Administrators** can manage subjects, grades, users, subject-grade assignments, and deletion requests.\n"
                    .'- System users are internal accounts and do not sign in to the app panel.',
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
                'body' => "- Vinjari masomo kutoka menyu ya **Masomo** na ubofye somo ili kulifungua.\n"
                    .'- Toleo rasmi linaonyeshwa kwa alama ya tiki ya kijani — hilo ndilo toleo lililoidhinishwa na shule.',
            ],
            [
                'title' => 'Kuhariri Masomo',
                'roles' => ['editor', 'subject_admin', 'site_administrator'],
                'body' => "- Bonyeza **Hariri Mpango Huu** kuingia katika hali ya kuhariri.\n"
                    ."- Fanya mabadiliko yako kwenye dirisha la kuhariri.\n"
                    ."- Unapobandika kutoka Word au Google Docs? Tumia **Bandika kama Maandishi Wazi** (Ctrl+Shift+V / Cmd+Shift+V) kuepuka matatizo ya uumbizaji.\n"
                    ."- Chagua aina ya kuongeza toleo: **Kiraka** (marekebisho madogo), **Ndogo** (maudhui mapya), au **Kubwa** (uandishi upya kamili).\n"
                    .'- Ongeza dokezo la marekebisho (hiari), kisha bonyeza **Hifadhi Mabadiliko** kuunda toleo jipya.',
            ],
            [
                'title' => 'Kulinganisha Matoleo',
                'roles' => null,
                'body' => "- Kwenye ukurasa wa somo, tumia **Linganisha na Toleo la Awali** au **Linganisha na Toleo Rasmi** kwa haraka.\n"
                    ."- Au chagua toleo lolote kutoka sehemu ya Linganisha kwenye orodha ya kushoto.\n"
                    ."- Nyongeza zinaonyeshwa kwa **kijani**, na yaliyofutwa kwa **waridi**.\n"
                    .'- Badilisha kati ya mpangilio wa **Side-by-Side** na **Stacked** kwa kutumia kitufe cha hali ya kulinganisha.',
            ],
            [
                'title' => 'Matoleo Rasmi',
                'roles' => ['subject_admin', 'site_administrator'],
                'body' => "- Toleo rasmi ni toleo lililoidhinishwa na shule kwa somo.\n"
                    .'- Toleo moja tu kwa kila familia ya somo linaweza kuwa rasmi wakati mmoja.',
            ],
            [
                'title' => 'Vipendwa',
                'roles' => null,
                'body' => "- Bonyeza **Ongeza kwenye Vipendwa** (★) kwenye toleo lolote kuihifadhi kwenye orodha yako.\n"
                    ."- Unaweza kuhifadhi toleo moja tu kwa kila familia ya somo.\n"
                    .'- Onyo linaonekana ikiwa toleo ulilolipenda linatofautiana na toleo rasmi la sasa.',
            ],
            [
                'title' => 'Ujumbe kwa Watumiaji Wengine',
                'roles' => null,
                'body' => "- Tumia **Tuma Ujumbe Kuhusu Somo Hili** kuwasiliana na mtumiaji mwingine kuhusu somo.\n"
                    ."- Ujumbe unaokujia unaonekana kwenye **Kisanduku cha Barua** chako (kinapatikana kwenye menyu ya mtumiaji, kona ya juu kulia).\n"
                    .'- Bonyeza mstari wowote wa ujumbe kuusoma; unaweza kujibu kutoka kwenye mtazamo wa ujumbe.',
            ],
            [
                'title' => 'Chapisha na Hamisha',
                'roles' => null,
                'body' => "- **Chapisha**: hufungua mazungumzo ya kuchapisha ya kivinjari chako kwa mpangilio safi.\n"
                    ."- **Hifadhi PDF**: huhifadhi toleo lililochaguliwa kama faili la PDF.\n"
                    ."- **Tuma PDF kwa Barua Pepe**: ingiza anwani yoyote ya barua pepe kutuma mpango wa somo kama kiambatisho cha PDF.\n"
                    ."- **Hifadhi .docx**: huhifadhi toleo lililochaguliwa kama faili la Word (.docx).\n"
                    .'- **Tuma .docx kwa Barua Pepe**: ingiza anwani yoyote ya barua pepe kutuma mpango wa somo kama kiambatisho cha .docx.',
            ],
            [
                'title' => 'Maombi ya Kufuta',
                'roles' => ['subject_admin', 'site_administrator'],
                'body' => "- Bonyeza **Omba Kufutwa** kwenye toleo kuliashiria kwa kuondolewa.\n"
                    ."- Msimamizi wa Tovuti lazima aidhinishe ombi kabla toleo halijafutwa.\n"
                    .'- Mchango na wasimamizi wote wa tovuti wanataarifiwa kwa ujumbe wa sanduku la barua wakati ombi linawasilishwa.',
            ],
            [
                'title' => 'Aina za Watumiaji na Ruhusa',
                'roles' => null,
                'body' => "- **Walimu** wanaweza kutazama masomo, kulinganisha matoleo, kuweka vipendwa, kutuma ujumbe kwa watumiaji wengine, kutafsiri kwa Kiswahili, kuchapisha, na kuhamisha.\n"
                    ."- **Wahariri** wanaweza kufanya yote hayo, kuhariri mipango ya masomo ndani ya somo na darasa walilopewa, na kutumia kipengele cha **Ask AI**.\n"
                    ."- **Wasimamizi wa Somo** wanaweza kufanya vitendo vyote vya mhariri kwa subject-grade zao, kuweka toleo kuwa rasmi, kuomba kufutwa, na kuwapa walimu jukumu la kuwa wahariri.\n"
                    ."- **Wasimamizi wa Tovuti** wanaweza kusimamia masomo, madarasa, watumiaji, mgao wa subject-grade, na maombi ya kufutwa.\n"
                    .'- Watumiaji wa mfumo ni akaunti za ndani na hawaingii kwenye paneli ya programu.',
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
