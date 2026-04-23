<?php

namespace App\Filament\Admin\Pages;

use App\Filament\App\Pages\AdminDashboard as AppAdminDashboard;

class Dashboard extends \Filament\Pages\Dashboard
{
    // Non-static: matches the parent Page class declaration
    protected string $view = 'filament.admin.pages.dashboard';

    public function mount(): void
    {
        $this->redirect(AppAdminDashboard::getUrl(panel: 'app'));
    }
}
