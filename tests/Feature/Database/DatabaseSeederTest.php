<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('database seeder includes the required QA demo accounts', function () {
    config(['app.seed_demo_qa_users' => true]);
    putenv('ADMIN_PASSWORD=password');
    $_ENV['ADMIN_PASSWORD'] = 'password';
    $_SERVER['ADMIN_PASSWORD'] = 'password';

    $this->seed(DatabaseSeeder::class);

    $requiredEmails = [
        'alice@demo.test',
        'bob@demo.test',
        'carol@demo.test',
        'david@demo.test',
        'eve@demo.test',
        'user@demo.test',
        'editor@demo.test',
        'subject_admin@demo.test',
        'site_admin@demo.test',
    ];

    expect(
        User::whereIn('email', $requiredEmails)
            ->pluck('email')
            ->sort()
            ->values()
            ->all()
    )->toBe(collect($requiredEmails)->sort()->values()->all());

    $demoSiteAdmin = User::where('email', 'site_admin@demo.test')->firstOrFail();

    expect(Hash::check('Site_Admin123!', $demoSiteAdmin->password))->toBeTrue();
});

test('database seeder can skip QA demo accounts when the seed flag is disabled', function () {
    config(['app.seed_demo_qa_users' => false]);
    putenv('ADMIN_PASSWORD=password');
    $_ENV['ADMIN_PASSWORD'] = 'password';
    $_SERVER['ADMIN_PASSWORD'] = 'password';

    $this->seed(DatabaseSeeder::class);

    expect(User::whereIn('email', [
        'alice@demo.test',
        'bob@demo.test',
        'carol@demo.test',
        'david@demo.test',
        'eve@demo.test',
        'user@demo.test',
        'editor@demo.test',
        'subject_admin@demo.test',
        'site_admin@demo.test',
    ])->exists())->toBeFalse();
});
