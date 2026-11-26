<?php
// The back-office menu, and which roles can see each item.
// Admin sees everything, so Admin is not listed. An empty roles list
// means every staff role can see it.
// 'ready' => false shows the item greyed out until that page is built.
// 'subpages' (optional) are other pages in the same section, so the
// menu item stays highlighted on them too.
// [Confirm the role access with the owner.]
return [
    ['label' => 'Dashboard',           'icon' => 'dashboard',          'page' => 'views/admin/dashboard.php',    'roles' => [],                                  'ready' => true],
    ['label' => 'Patients',            'icon' => 'folder_shared',      'page' => 'views/admin/patients.php',     'roles' => ['Receptionist', 'Optometrist'],     'ready' => true,
     'subpages' => ['patient_form.php', 'patient_detail.php']],
    ['label' => 'Appointments',        'icon' => 'calendar_month',     'page' => 'views/admin/appointments.php', 'roles' => ['Receptionist', 'Optometrist'],     'ready' => true,
     'subpages' => ['appointment_form.php']],
    ['label' => 'Eye Exams',           'icon' => 'visibility',         'page' => 'views/admin/exams.php',        'roles' => ['Optometrist'],                     'ready' => true,
     'subpages' => ['exam_form.php', 'exam_detail.php']],
    ['label' => 'Orders and Lab',      'icon' => 'precision_manufacturing', 'page' => 'views/admin/orders.php',  'roles' => ['Receptionist', 'Lab Technician'],  'ready' => true,
     'subpages' => ['order_detail.php']],
    ['label' => 'Products and Stock',  'icon' => 'inventory_2',        'page' => 'views/admin/product.php',      'roles' => ['Receptionist'],                    'ready' => true,
     'subpages' => ['product_form.php', 'brand.php', 'category.php', 'stock.php', 'lens_options.php']],
    ['label' => 'Payments and Invoices', 'icon' => 'receipt_long',     'page' => 'views/admin/payments.php',     'roles' => ['Receptionist', 'Accountant'],      'ready' => false],
    ['label' => 'Expenses',            'icon' => 'payments',           'page' => 'views/admin/expenses.php',     'roles' => ['Accountant'],                      'ready' => false],
    ['label' => 'Debtors',             'icon' => 'account_balance_wallet', 'page' => 'views/admin/debtors.php',  'roles' => ['Receptionist', 'Accountant'],      'ready' => false],
    ['label' => 'Reports (P&L)',       'icon' => 'monitoring',         'page' => 'views/admin/reports.php',      'roles' => ['Accountant'],                      'ready' => false],
    ['label' => 'Staff Accounts',      'icon' => 'badge',              'page' => 'views/admin/staff.php',        'roles' => ['Admin'],                           'ready' => true,
     'subpages' => ['staff_form.php']],
    ['label' => 'Audit Trail',         'icon' => 'history',            'page' => 'views/admin/audit.php',        'roles' => ['Admin'],                           'ready' => false],
];