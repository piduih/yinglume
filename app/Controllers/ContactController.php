<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Contact;

/**
 * ContactController
 * Customers, Vendors, and Trade Partners management.
 */
class ContactController extends BaseController
{
    private Contact $contactModel;

    public function __construct()
    {
        parent::__construct();
        $this->contactModel = new Contact();
    }

    public function index(): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $type   = (string)$this->input('type', 'all');
        $search = (string)$this->input('search', '');

        $where = ['tenant_id = :tid'];
        $params = [':tid' => $tenantId];

        if ($type === 'customer') {
            $where[] = "type IN ('customer', 'both')";
        } elseif ($type === 'vendor') {
            $where[] = "type IN ('vendor', 'both')";
        }

        if ($search !== '') {
            $where[] = '(name LIKE :s OR email LIKE :s2 OR phone LIKE :s3)';
            $params[':s']  = '%' . $search . '%';
            $params[':s2'] = '%' . $search . '%';
            $params[':s3'] = '%' . $search . '%';
        }

        $sql = "SELECT * FROM contacts WHERE " . implode(' AND ', $where) . " ORDER BY name ASC";
        $contacts = $db->select($sql, $params);

        $this->render('contacts.index', [
            'pageTitle' => 'Contacts Directory',
            'contacts'  => $contacts,
            'type'      => $type,
            'search'    => $search,
        ], 'main');
    }

    public function create(): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $this->render('contacts.form', [
            'pageTitle' => 'New Contact',
            'contact'   => null,
        ], 'main');
    }

    public function store(): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $this->validateCSRF();

        $errors = $this->validate([
            'name' => 'required|min:2|max:150',
            'type' => 'required|in:customer,vendor,both',
        ]);

        if ($errors) {
            $this->flash('error', reset($errors));
            $this->redirect('/app/contacts/create');
        }

        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $id = $db->insert(
            "INSERT INTO contacts (uuid, tenant_id, type, name, email, phone, address, city, state, country, postal_code, tax_number, credit_limit, payment_terms, notes, is_active, created_at, updated_at)
             VALUES (:uuid, :tid, :type, :name, :email, :phone, :addr, :city, :state, :country, :post, :tax, :credit, :terms, :notes, 1, NOW(), NOW())",
            [
                ':uuid'    => generate_uuid(),
                ':tid'     => $tenantId,
                ':type'    => (string)$this->input('type'),
                ':name'    => (string)$this->input('name'),
                ':email'   => (string)$this->input('email', ''),
                ':phone'   => (string)$this->input('phone', ''),
                ':addr'    => (string)$this->input('address', ''),
                ':city'    => (string)$this->input('city', ''),
                ':state'   => (string)$this->input('state', ''),
                ':country' => (string)$this->input('country', 'Malaysia'),
                ':post'    => (string)$this->input('postal_code', ''),
                ':tax'     => (string)$this->input('tax_number', ''),
                ':credit'  => (float)$this->input('credit_limit', 0.0),
                ':terms'   => (int)$this->input('payment_terms', 30),
                ':notes'   => (string)$this->input('notes', ''),
            ]
        );

        $this->flash('success', 'Contact created successfully.');
        $this->redirect('/app/contacts/' . $id);
    }

    public function show(string $id): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $contact = $db->selectOne("SELECT * FROM contacts WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        if (!$contact) {
            $this->flash('error', 'Contact not found.');
            $this->redirect('/app/contacts');
        }

        // Recent Invoices if customer
        $invoices = $db->select(
            "SELECT * FROM invoices WHERE contact_id = :cid AND tenant_id = :tid ORDER BY invoice_date DESC LIMIT 10",
            [':cid' => $id, ':tid' => $tenantId]
        );

        // Recent Bills if vendor
        $bills = $db->select(
            "SELECT * FROM bills WHERE contact_id = :cid AND tenant_id = :tid ORDER BY bill_date DESC LIMIT 10",
            [':cid' => $id, ':tid' => $tenantId]
        );

        // Calculate balances
        $arBalance = $db->selectOne("SELECT COALESCE(SUM(amount_due), 0) AS due FROM invoices WHERE contact_id = :cid AND tenant_id = :tid AND status NOT IN ('draft', 'void', 'paid')", [':cid' => $id, ':tid' => $tenantId])['due'] ?? 0;
        $apBalance = $db->selectOne("SELECT COALESCE(SUM(amount_due), 0) AS due FROM bills WHERE contact_id = :cid AND tenant_id = :tid AND status NOT IN ('draft', 'void', 'paid')", [':cid' => $id, ':tid' => $tenantId])['due'] ?? 0;

        $this->render('contacts.show', [
            'pageTitle' => $contact['name'],
            'contact'   => $contact,
            'invoices'  => $invoices,
            'bills'     => $bills,
            'arBalance' => (float)$arBalance,
            'apBalance' => (float)$apBalance,
        ], 'main');
    }

    public function edit(string $id): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $contact = $db->selectOne("SELECT * FROM contacts WHERE id = :id AND tenant_id = :tid", [':id' => $id, ':tid' => $tenantId]);
        if (!$contact) {
            $this->flash('error', 'Contact not found.');
            $this->redirect('/app/contacts');
        }

        $this->render('contacts.form', [
            'pageTitle' => 'Edit Contact — ' . $contact['name'],
            'contact'   => $contact,
        ], 'main');
    }

    public function update(string $id): void
    {
        $this->requireRole(['owner', 'admin', 'accountant']);
        $this->validateCSRF();

        $errors = $this->validate([
            'name' => 'required|min:2|max:150',
            'type' => 'required|in:customer,vendor,both',
        ]);

        if ($errors) {
            $this->flash('error', reset($errors));
            $this->redirect('/app/contacts/' . $id . '/edit');
        }

        $tenantId = Auth::tenantId();
        $db = Database::getInstance();

        $db->update(
            "UPDATE contacts 
             SET type = :type, name = :name, email = :email, phone = :phone, address = :addr, city = :city, 
                 state = :state, country = :country, postal_code = :post, tax_number = :tax, credit_limit = :credit, 
                 payment_terms = :terms, notes = :notes, updated_at = NOW()
             WHERE id = :id AND tenant_id = :tid",
            [
                ':type'    => (string)$this->input('type'),
                ':name'    => (string)$this->input('name'),
                ':email'   => (string)$this->input('email', ''),
                ':phone'   => (string)$this->input('phone', ''),
                ':addr'    => (string)$this->input('address', ''),
                ':city'    => (string)$this->input('city', ''),
                ':state'   => (string)$this->input('state', ''),
                ':country' => (string)$this->input('country', 'Malaysia'),
                ':post'    => (string)$this->input('postal_code', ''),
                ':tax'     => (string)$this->input('tax_number', ''),
                ':credit'  => (float)$this->input('credit_limit', 0.0),
                ':terms'   => (int)$this->input('payment_terms', 30),
                ':notes'   => (string)$this->input('notes', ''),
                ':id'      => $id,
                ':tid'     => $tenantId,
            ]
        );

        $this->flash('success', 'Contact updated successfully.');
        $this->redirect('/app/contacts/' . $id);
    }

    public function ajax_list(): void
    {
        $this->requireAuth();
        $tenantId = Auth::tenantId();
        $type = (string)$this->input('type', 'customer');

        $db = Database::getInstance();
        $where = "tenant_id = :tid AND is_active = 1";
        if ($type === 'customer') {
            $where .= " AND type IN ('customer', 'both')";
        } else {
            $where .= " AND type IN ('vendor', 'both')";
        }

        $contacts = $db->select("SELECT id, name, email, payment_terms FROM contacts WHERE {$where} ORDER BY name ASC", [':tid' => $tenantId]);
        $this->json(['contacts' => $contacts]);
    }
}
