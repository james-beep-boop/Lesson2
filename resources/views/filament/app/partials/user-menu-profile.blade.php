<div class="fi-dropdown-list">
    <div style="padding: 0.75rem 0.875rem 0.625rem;">
        <dl style="display: grid; grid-template-columns: 7rem minmax(0, 1fr); row-gap: 0.625rem; column-gap: 0.75rem; font-size: 0.8125rem; line-height: 1.55; margin: 0;">
            <dt style="opacity: 0.55; font-size: 0.75rem; white-space: nowrap;">Name</dt>
            <dd style="margin: 0; word-break: break-word;">{{ $user->name }}</dd>

            <dt style="opacity: 0.55; font-size: 0.75rem; white-space: nowrap;">Email</dt>
            <dd style="margin: 0; word-break: break-word;">{{ $user->email }}</dd>

            <dt style="opacity: 0.55; font-size: 0.75rem; white-space: nowrap; vertical-align: top;">Roles</dt>
            <dd style="margin: 0;">
                @foreach($user->detailedRoleLabels() as $roleLabel)
                    <div>{{ $roleLabel }}</div>
                @endforeach
            </dd>
        </dl>
    </div>
</div>
