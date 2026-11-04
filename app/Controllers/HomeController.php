<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;

/**
 * HomeController
 * Public marketing landing page.
 */
class HomeController extends BaseController
{
    public function index(): void
    {
        if (Auth::check()) {
            $this->redirect('/app/dashboard');
        }

        $this->render('landing.index', [
            'pageTitle' => 'YingLume — Free Open-Source Modern Finance & Accounting SaaS',
        ], 'landing');
    }
}
