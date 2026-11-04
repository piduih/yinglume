<?php
/**
 * YingLume Finance — Application Routes
 *
 * @var \App\Core\Router $router
 */

declare(strict_types=1);

// ── Public Routes ────────────────────────────────────────────────────────────
$router->get('/',                 'HomeController@index',         'home');
$router->get('/login',            'AuthController@login',         'login');
$router->post('/login',           'AuthController@doLogin');
$router->get('/register',         'AuthController@register',      'register');
$router->post('/register',        'AuthController@doRegister');
$router->get('/logout',           'AuthController@logout',        'logout');
$router->post('/logout',          'AuthController@logout');
$router->get('/forgot-password',  'AuthController@forgotPassword','forgot.password');
$router->post('/forgot-password', 'AuthController@doForgotPassword');

// ── Authenticated App Routes ─────────────────────────────────────────────────
$router->group(['prefix' => '/app', 'middleware' => 'auth'], function (\App\Core\Router $router): void {

    // Dashboard
    $router->get('/dashboard', 'DashboardController@index', 'dashboard');

    // Chart of Accounts
    $router->get('/accounts',                     'AccountController@index',       'accounts.index');
    $router->get('/accounts/create',              'AccountController@create',      'accounts.create');
    $router->post('/accounts/create',             'AccountController@store');
    $router->get('/accounts/{id}/edit',           'AccountController@edit',        'accounts.edit');
    $router->post('/accounts/{id}/edit',          'AccountController@update');
    $router->post('/accounts/{id}/deactivate',    'AccountController@deactivate');
    $router->get('/accounts/ajax-search',         'AccountController@ajax_search');

    // Journal Entries
    $router->get('/journal',              'JournalController@index',  'journal.index');
    $router->get('/journal/create',       'JournalController@create', 'journal.create');
    $router->post('/journal/create',      'JournalController@store');
    $router->get('/journal/{id}',         'JournalController@show',   'journal.show');
    $router->post('/journal/{id}/post',   'JournalController@post');
    $router->post('/journal/{id}/void',   'JournalController@void');

    // Contacts (Customers & Vendors)
    $router->get('/contacts',             'ContactController@index',     'contacts.index');
    $router->get('/contacts/create',      'ContactController@create',    'contacts.create');
    $router->post('/contacts/create',     'ContactController@store');
    $router->get('/contacts/{id}',        'ContactController@show',      'contacts.show');
    $router->get('/contacts/{id}/edit',   'ContactController@edit',      'contacts.edit');
    $router->post('/contacts/{id}/edit',  'ContactController@update');
    $router->get('/contacts/ajax-list',   'ContactController@ajax_list');

    // Invoices (Accounts Receivable)
    $router->get('/invoices',                  'InvoiceController@index',        'invoices.index');
    $router->get('/invoices/create',           'InvoiceController@create',       'invoices.create');
    $router->post('/invoices/create',          'InvoiceController@store');
    $router->get('/invoices/{id}',             'InvoiceController@show',         'invoices.show');
    $router->post('/invoices/{id}/sent',       'InvoiceController@markSent');
    $router->get('/invoices/{id}/pay',         'InvoiceController@pay',          'invoices.pay');
    $router->post('/invoices/{id}/pay',        'InvoiceController@recordPayment');
    $router->post('/invoices/{id}/void',       'InvoiceController@void');
    $router->get('/invoices/{id}/pdf',         'InvoiceController@pdf');
    $router->get('/invoices/ajax-item/{id}',   'InvoiceController@ajaxGetItem');

    // Bills (Accounts Payable)
    $router->get('/bills',                'BillController@index',         'bills.index');
    $router->get('/bills/create',         'BillController@create',        'bills.create');
    $router->post('/bills/create',        'BillController@store');
    $router->get('/bills/{id}',           'BillController@show',          'bills.show');
    $router->get('/bills/{id}/pay',       'BillController@pay',           'bills.pay');
    $router->post('/bills/{id}/pay',      'BillController@recordPayment');
    $router->post('/bills/{id}/void',     'BillController@void');

    // Banking
    $router->get('/banking',                       'BankController@index',          'banking.index');
    $router->get('/banking/create',                'BankController@create',         'banking.create');
    $router->post('/banking/create',               'BankController@store');
    $router->get('/banking/{id}',                  'BankController@show',           'banking.show');
    $router->post('/banking/{id}/transaction',     'BankController@addTransaction');
    $router->get('/banking/{id}/reconcile',        'BankController@reconcile',      'banking.reconcile');
    $router->post('/banking/transfer',             'BankController@transfer');

    // Items / Inventory
    $router->get('/items',                     'ItemController@index',      'items.index');
    $router->get('/items/create',              'ItemController@create',     'items.create');
    $router->post('/items/create',             'ItemController@store');
    $router->get('/items/{id}/edit',           'ItemController@edit',       'items.edit');
    $router->post('/items/{id}/edit',          'ItemController@update');
    $router->post('/items/{id}/deactivate',    'ItemController@deactivate');

    // Financial Reports
    $router->get('/reports/profit-loss',       'ReportController@profitLoss',   'reports.pl');
    $router->get('/reports/balance-sheet',     'ReportController@balanceSheet', 'reports.bs');
    $router->get('/reports/trial-balance',     'ReportController@trialBalance', 'reports.tb');
    $router->get('/reports/cash-flow',         'ReportController@cashFlow',     'reports.cf');
    $router->get('/reports/ar-aging',          'ReportController@arAging',      'reports.ar_aging');
    $router->get('/reports/ap-aging',          'ReportController@apAging',      'reports.ap_aging');
    $router->get('/reports/ledger',            'ReportController@generalLedger','reports.ledger');

    // Settings
    $router->get('/settings',                  'SettingsController@index',         'settings.index');
    $router->post('/settings',                 'SettingsController@update');
    $router->get('/settings/users',            'SettingsController@users',         'settings.users');
    $router->post('/settings/users/invite',    'SettingsController@inviteUser');
    $router->post('/settings/users/{id}/update','SettingsController@updateUser');
    $router->post('/settings/users/{id}/remove','SettingsController@removeUser');
    $router->get('/settings/tax',              'SettingsController@tax',           'settings.tax');
    $router->post('/settings/tax',             'SettingsController@saveTax');
    $router->get('/settings/fiscal-year',      'SettingsController@fiscalYear',    'settings.fy');
    $router->post('/settings/fiscal-year',     'SettingsController@saveFiscalYear');
});
