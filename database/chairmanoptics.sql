-- =====================================================================
-- Chairman Optics and Lens Centre
-- CS442 E-Commerce Final Project: full database
--
-- This file creates every table the system needs, for both the online
-- shop (customers) and the back office (clinic, inventory, accounting).
--
-- Every table and view starts with co_ (for Chairman Optics). This lets
-- the project share one database with other projects, such as the
-- shoppn lab, without any name clashes. Apart from the prefix, names
-- follow the shoppn lab (co_customer, co_categories, co_brands,
-- co_products, co_cart, co_orders, co_orderdetails, co_payment).
--
-- HOW TO IMPORT (school server or XAMPP):
--   1. In phpMyAdmin, click your database in the left panel FIRST.
--   2. Click Import, choose this file and click Import once.
--   The file only creates, deletes and re-creates tables that start
--   with co_, so other tables in the same database are never touched.
--   Re-importing deletes all co_ test data.
-- =====================================================================

SET NAMES utf8mb4;

-- Remove old co_ tables so the file can be imported again.
-- Foreign key checks are switched off only while deleting, so the
-- tables can be dropped in any order.
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS co_audit_log, co_notifications, co_expenses, co_payment,
    co_order_status_history, co_orderdetails, co_orders, co_cart, co_prescriptions,
    co_exam_attachments, co_eye_exams, co_appointments, co_stock_movements,
    co_lens_options, co_products, co_brands, co_categories, co_patients, co_staff,
    co_roles, co_customer;
SET FOREIGN_KEY_CHECKS = 1;


-- =====================================================================
-- PART A: PEOPLE (customers, staff, roles, patients)
-- =====================================================================

-- ---------------------------------------------------------------------
-- co_customer: people who register on the website.
-- Unchanged from the version already built, so registration and login
-- keep working without any code changes.
-- ---------------------------------------------------------------------
CREATE TABLE co_customer (
    customer_id      INT AUTO_INCREMENT PRIMARY KEY,
    customer_fname   VARCHAR(50)  NOT NULL,
    customer_lname   VARCHAR(50)  NOT NULL,
    customer_gender  VARCHAR(20)  NOT NULL,           -- fits "Prefer not to say"
    customer_email   VARCHAR(100) NOT NULL UNIQUE,
    customer_contact VARCHAR(15)  NOT NULL,           -- saved as +233...
    customer_pass    VARCHAR(255) NOT NULL,           -- bcrypt hash
    date_created     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- co_roles: the five staff roles agreed with the owner.
-- ---------------------------------------------------------------------
CREATE TABLE co_roles (
    role_id   INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- co_staff: back office users. Each staff member has exactly one role.
-- Staff are kept separate from customers so a customer can never log
-- in to staff pages by mistake.
-- ---------------------------------------------------------------------
CREATE TABLE co_staff (
    staff_id      INT AUTO_INCREMENT PRIMARY KEY,
    role_id       INT          NOT NULL,
    staff_fname   VARCHAR(50)  NOT NULL,
    staff_lname   VARCHAR(50)  NOT NULL,
    staff_email   VARCHAR(100) NOT NULL UNIQUE,
    staff_contact VARCHAR(15)  NOT NULL,
    staff_pass    VARCHAR(255) NOT NULL,              -- bcrypt hash
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,    -- 0 = account disabled
    last_login    DATETIME     NULL,
    date_created  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES co_roles(role_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- co_patients: clinic records with a folder number.
-- A patient may also have an online account (customer_id), but walk-in
-- patients do not need one, so customer_id can be NULL.
-- folder_no is generated in PHP, for example CO-2026-0001.
-- ---------------------------------------------------------------------
CREATE TABLE co_patients (
    patient_id      INT AUTO_INCREMENT PRIMARY KEY,
    folder_no       VARCHAR(20)  NOT NULL UNIQUE,
    customer_id     INT          NULL,
    patient_fname   VARCHAR(50)  NOT NULL,
    patient_lname   VARCHAR(50)  NOT NULL,
    patient_gender  VARCHAR(20)  NOT NULL,            -- fits "Prefer not to say"
    date_of_birth   DATE         NULL,
    patient_contact VARCHAR(15)  NOT NULL,
    patient_email   VARCHAR(100) NULL,
    patient_address VARCHAR(255) NULL,
    occupation      VARCHAR(100) NULL,
    registered_by   INT          NULL,                -- staff member who registered them
    date_registered DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id)   REFERENCES co_customer(customer_id) ON DELETE SET NULL,
    FOREIGN KEY (registered_by) REFERENCES co_staff(staff_id)       ON DELETE SET NULL,
    INDEX idx_patient_name (patient_lname, patient_fname),
    INDEX idx_patient_contact (patient_contact)
) ENGINE=InnoDB;


-- =====================================================================
-- PART B: PRODUCTS AND STOCK
-- =====================================================================

-- ---------------------------------------------------------------------
-- co_categories: product types (frames, sunglasses, contact lenses ...).
-- Same columns as the shoppn lab.
-- ---------------------------------------------------------------------
CREATE TABLE co_categories (
    cat_id   INT AUTO_INCREMENT PRIMARY KEY,
    cat_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- co_brands: frame and lens brands. Same columns as the shoppn lab.
-- ---------------------------------------------------------------------
CREATE TABLE co_brands (
    brand_id   INT AUTO_INCREMENT PRIMARY KEY,
    brand_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- co_products: everything sold, online or in the shop.
-- The first eight columns match the shoppn lab. The extra columns
-- support colour filtering, stock control and services.
-- is_service = 1 marks things like an eye exam fee, which can be
-- invoiced but have no stock.
-- ---------------------------------------------------------------------
CREATE TABLE co_products (
    product_id       INT AUTO_INCREMENT PRIMARY KEY,
    product_cat      INT           NOT NULL,
    product_brand    INT           NULL,             -- services have no brand
    product_title    VARCHAR(200)  NOT NULL,
    product_price    DECIMAL(10,2) NOT NULL,
    product_desc     TEXT          NULL,
    product_image    VARCHAR(255)  NULL,             -- e.g. images/products/frame1.jpg
    product_keywords VARCHAR(255)  NULL,
    -- extra columns (not in shoppn)
    product_colour   VARCHAR(50)   NULL,
    product_gender   VARCHAR(10)   NULL,             -- Men, Women, Unisex, Kids
    product_stock    INT           NOT NULL DEFAULT 0,
    reorder_level    INT           NOT NULL DEFAULT 5,  -- low-stock alert when stock <= this
    is_service       TINYINT(1)    NOT NULL DEFAULT 0,
    needs_lenses     TINYINT(1)    NOT NULL DEFAULT 0,  -- 1 = frame that can take prescription lenses
    is_active        TINYINT(1)    NOT NULL DEFAULT 1,  -- 0 = hidden from the shop
    date_added       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_cat)   REFERENCES co_categories(cat_id),
    FOREIGN KEY (product_brand) REFERENCES co_brands(brand_id) ON DELETE SET NULL,
    INDEX idx_product_price (product_price),
    INDEX idx_product_colour (product_colour)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- co_lens_options: lenses chosen when ordering prescription glasses.
-- option_group separates the lens type (single vision, bifocal,
-- progressive) from the coating (photochromic, blue cut).
-- ---------------------------------------------------------------------
CREATE TABLE co_lens_options (
    lens_id      INT AUTO_INCREMENT PRIMARY KEY,
    lens_name    VARCHAR(100)  NOT NULL UNIQUE,
    option_group ENUM('Lens type', 'Coating') NOT NULL,
    lens_price   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    lens_desc    VARCHAR(255)  NULL,
    is_active    TINYINT(1)    NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- co_stock_movements: every change to stock, so staff can see why a
-- number went up or down. Positive change_qty = stock in,
-- negative = stock out.
-- ---------------------------------------------------------------------
CREATE TABLE co_stock_movements (
    movement_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id  INT          NOT NULL,
    change_qty  INT          NOT NULL,
    reason      ENUM('Restock', 'Sale', 'Return', 'Adjustment') NOT NULL,
    order_id    INT          NULL,                    -- filled in for sales and returns
    staff_id    INT          NULL,                    -- NULL for online sales
    note        VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES co_products(product_id),
    FOREIGN KEY (staff_id)   REFERENCES co_staff(staff_id) ON DELETE SET NULL
    -- order_id foreign key is added after the orders table is created
) ENGINE=InnoDB;


-- =====================================================================
-- PART C: CLINIC (appointments, exams, prescriptions)
-- =====================================================================

-- ---------------------------------------------------------------------
-- co_appointments: eye exam bookings, online or at the front desk.
-- An online booking has a customer_id. Reception links it to a
-- patient_id when the person arrives.
-- ---------------------------------------------------------------------
CREATE TABLE co_appointments (
    appointment_id   INT AUTO_INCREMENT PRIMARY KEY,
    customer_id      INT          NULL,
    patient_id       INT          NULL,
    optometrist_id   INT          NULL,               -- staff member assigned
    appointment_date DATE         NOT NULL,
    appointment_time TIME         NOT NULL,
    reason           VARCHAR(255) NULL,
    booked_via       ENUM('Online', 'Walk-in', 'Phone') NOT NULL DEFAULT 'Online',
    appointment_status ENUM('Booked', 'Confirmed', 'Completed', 'Cancelled', 'No-show')
                     NOT NULL DEFAULT 'Booked',
    notes            TEXT         NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id)    REFERENCES co_customer(customer_id) ON DELETE SET NULL,
    FOREIGN KEY (patient_id)     REFERENCES co_patients(patient_id)  ON DELETE SET NULL,
    FOREIGN KEY (optometrist_id) REFERENCES co_staff(staff_id)       ON DELETE SET NULL,
    INDEX idx_appointment_day (appointment_date, appointment_time)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- co_eye_exams: one row per clinical eye exam.
-- Visual acuity is stored as text because it is written like 6/6 or
-- 6/18. RE = right eye, LE = left eye.
-- The refraction result is stored in the prescriptions table below.
-- ---------------------------------------------------------------------
CREATE TABLE co_eye_exams (
    exam_id         INT AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT          NOT NULL,
    appointment_id  INT          NULL,
    optometrist_id  INT          NULL,
    exam_date       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    chief_complaint VARCHAR(255) NULL,
    va_unaided_re   VARCHAR(10)  NULL,
    va_unaided_le   VARCHAR(10)  NULL,
    va_aided_re     VARCHAR(10)  NULL,
    va_aided_le     VARCHAR(10)  NULL,
    va_pinhole_re   VARCHAR(10)  NULL,
    va_pinhole_le   VARCHAR(10)  NULL,
    diagnosis       TEXT         NULL,
    notes           TEXT         NULL,
    FOREIGN KEY (patient_id)     REFERENCES co_patients(patient_id),
    FOREIGN KEY (appointment_id) REFERENCES co_appointments(appointment_id) ON DELETE SET NULL,
    FOREIGN KEY (optometrist_id) REFERENCES co_staff(staff_id)              ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- co_exam_attachments: files added to an exam (scans, referral letters).
-- Files are saved in uploads/, which the .htaccess file protects.
-- ---------------------------------------------------------------------
CREATE TABLE co_exam_attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id       INT          NOT NULL,
    file_name     VARCHAR(255) NOT NULL,              -- original name shown to staff
    file_path     VARCHAR(255) NOT NULL,              -- saved name inside uploads/
    uploaded_by   INT          NULL,
    uploaded_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id)     REFERENCES co_eye_exams(exam_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES co_staff(staff_id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- co_prescriptions: glasses prescriptions from two sources.
--   'Exam'   = written by the optometrist after refraction (exam_id set)
--   'Upload' = uploaded by a customer when ordering online (file_path set)
-- SPH = sphere, CYL = cylinder, AXIS = 0 to 180, ADD = reading addition,
-- PD = pupillary distance in mm.
-- ---------------------------------------------------------------------
CREATE TABLE co_prescriptions (
    prescription_id INT AUTO_INCREMENT PRIMARY KEY,
    source          ENUM('Exam', 'Upload') NOT NULL,
    patient_id      INT          NULL,
    customer_id     INT          NULL,
    exam_id         INT          NULL,
    re_sphere       DECIMAL(5,2) NULL,
    re_cylinder     DECIMAL(5,2) NULL,
    re_axis         SMALLINT     NULL,
    re_add          DECIMAL(4,2) NULL,
    le_sphere       DECIMAL(5,2) NULL,
    le_cylinder     DECIMAL(5,2) NULL,
    le_axis         SMALLINT     NULL,
    le_add          DECIMAL(4,2) NULL,
    pd              DECIMAL(4,1) NULL,
    file_path       VARCHAR(255) NULL,                -- uploaded prescription image or PDF
    notes           TEXT         NULL,
    is_verified     TINYINT(1)   NOT NULL DEFAULT 0,  -- optometrist has checked an upload
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id)  REFERENCES co_patients(patient_id)  ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES co_customer(customer_id) ON DELETE SET NULL,
    FOREIGN KEY (exam_id)     REFERENCES co_eye_exams(exam_id)    ON DELETE SET NULL
) ENGINE=InnoDB;


-- =====================================================================
-- PART D: SHOPPING (cart, orders, order items, order tracking)
-- =====================================================================

-- ---------------------------------------------------------------------
-- co_cart: items waiting to be bought.
-- Like shoppn, p_id, ip_add, c_id and qty are kept. Guests are tracked
-- by IP address until they log in.
-- cart_id is added as a primary key, and the lens and prescription
-- columns are added for prescription glasses.
-- ---------------------------------------------------------------------
CREATE TABLE co_cart (
    cart_id         INT AUTO_INCREMENT PRIMARY KEY,
    p_id            INT         NOT NULL,
    ip_add          VARCHAR(50) NULL,
    c_id            INT         NULL,
    qty             INT         NOT NULL DEFAULT 1,
    lens_type_id    INT         NULL,
    lens_coating_id INT         NULL,
    prescription_id INT         NULL,
    added_at        DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (p_id)            REFERENCES co_products(product_id)           ON DELETE CASCADE,
    FOREIGN KEY (c_id)            REFERENCES co_customer(customer_id)          ON DELETE CASCADE,
    FOREIGN KEY (lens_type_id)    REFERENCES co_lens_options(lens_id)          ON DELETE SET NULL,
    FOREIGN KEY (lens_coating_id) REFERENCES co_lens_options(lens_id)          ON DELETE SET NULL,
    FOREIGN KEY (prescription_id) REFERENCES co_prescriptions(prescription_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- co_orders: one row per sale, online or in the shop.
-- Online orders have a customer_id. Shop sales to walk-in patients
-- have a patient_id. order_status follows the lab workflow agreed with
-- the owner. order_total is saved at checkout so later price changes
-- do not change old invoices.
-- ---------------------------------------------------------------------
CREATE TABLE co_orders (
    order_id         INT AUTO_INCREMENT PRIMARY KEY,
    customer_id      INT           NULL,
    patient_id       INT           NULL,
    invoice_no       VARCHAR(30)   NOT NULL UNIQUE,   -- e.g. INV-2026-00001
    order_date       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    order_status     ENUM('Pending', 'Cutting', 'Fitting', 'Ready', 'Delivered', 'Cancelled')
                     NOT NULL DEFAULT 'Pending',
    order_source     ENUM('Online', 'In-store') NOT NULL DEFAULT 'Online',
    order_total      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    delivery_method  ENUM('Pickup', 'Delivery') NOT NULL DEFAULT 'Pickup',
    delivery_address VARCHAR(255)  NULL,
    created_by       INT           NULL,              -- staff member, for in-store sales
    notes            TEXT          NULL,
    FOREIGN KEY (customer_id) REFERENCES co_customer(customer_id) ON DELETE SET NULL,
    FOREIGN KEY (patient_id)  REFERENCES co_patients(patient_id)  ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES co_staff(staff_id)       ON DELETE SET NULL,
    INDEX idx_order_status (order_status),
    INDEX idx_order_date (order_date)
) ENGINE=InnoDB;

-- Now that orders exists, link stock movements to orders.
ALTER TABLE co_stock_movements
    ADD FOREIGN KEY (order_id) REFERENCES co_orders(order_id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- co_orderdetails: the items inside each order (shoppn name kept).
-- Prices are copied in at checkout for the same reason as order_total.
-- ---------------------------------------------------------------------
CREATE TABLE co_orderdetails (
    detail_id       INT AUTO_INCREMENT PRIMARY KEY,
    order_id        INT           NOT NULL,
    product_id      INT           NOT NULL,
    qty             INT           NOT NULL DEFAULT 1,
    unit_price      DECIMAL(10,2) NOT NULL,
    lens_type_id    INT           NULL,
    lens_coating_id INT           NULL,
    lens_price      DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- lens type + coating price per pair
    prescription_id INT           NULL,
    FOREIGN KEY (order_id)        REFERENCES co_orders(order_id)               ON DELETE CASCADE,
    FOREIGN KEY (product_id)      REFERENCES co_products(product_id),
    FOREIGN KEY (lens_type_id)    REFERENCES co_lens_options(lens_id)          ON DELETE SET NULL,
    FOREIGN KEY (lens_coating_id) REFERENCES co_lens_options(lens_id)          ON DELETE SET NULL,
    FOREIGN KEY (prescription_id) REFERENCES co_prescriptions(prescription_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- co_order_status_history: every status change, with who made it.
-- The customer's tracking page reads this to show a timeline.
-- ---------------------------------------------------------------------
CREATE TABLE co_order_status_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id   INT          NOT NULL,
    status     ENUM('Pending', 'Cutting', 'Fitting', 'Ready', 'Delivered', 'Cancelled') NOT NULL,
    changed_by INT          NULL,                     -- NULL when the system changed it
    note       VARCHAR(255) NULL,
    changed_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)   REFERENCES co_orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES co_staff(staff_id)  ON DELETE SET NULL
) ENGINE=InnoDB;


-- =====================================================================
-- PART E: MONEY (payments, expenses)
-- =====================================================================

-- ---------------------------------------------------------------------
-- co_payment: money received (shoppn name kept, plus extra columns).
-- An order can have more than one payment, for example a deposit at
-- the shop and the balance on collection. pay_reference holds the
-- Paystack reference, and it is unique so the same payment is never
-- recorded twice.
-- ---------------------------------------------------------------------
CREATE TABLE co_payment (
    pay_id         INT AUTO_INCREMENT PRIMARY KEY,
    order_id       INT           NOT NULL,
    customer_id    INT           NULL,
    amt            DECIMAL(10,2) NOT NULL,
    currency       VARCHAR(3)    NOT NULL DEFAULT 'GHS',
    payment_method ENUM('Mobile Money', 'Card', 'Cash', 'Bank Transfer') NOT NULL,
    pay_reference  VARCHAR(100)  NULL UNIQUE,
    pay_status     ENUM('Pending', 'Success', 'Failed') NOT NULL DEFAULT 'Pending',
    received_by    INT           NULL,                -- staff member, for cash at the shop
    payment_date   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)    REFERENCES co_orders(order_id),
    FOREIGN KEY (customer_id) REFERENCES co_customer(customer_id) ON DELETE SET NULL,
    FOREIGN KEY (received_by) REFERENCES co_staff(staff_id)       ON DELETE SET NULL,
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- co_expenses: money spent by the business, used for the P&L report.
-- expense_category is plain text so the owner's categories can be
-- added in PHP without changing the database.
-- ---------------------------------------------------------------------
CREATE TABLE co_expenses (
    expense_id       INT AUTO_INCREMENT PRIMARY KEY,
    expense_category VARCHAR(50)   NOT NULL,
    description      VARCHAR(255)  NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    expense_date     DATE          NOT NULL,
    receipt_file     VARCHAR(255)  NULL,              -- optional scan inside uploads/
    recorded_by      INT           NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recorded_by) REFERENCES co_staff(staff_id) ON DELETE SET NULL,
    INDEX idx_expense_date (expense_date)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Debtors and low-stock alerts
-- There are no tables for these. They are worked out with a query each
-- time they are needed, so the numbers are always correct and never
-- need updating by hand. (The school server does not allow CREATE VIEW,
-- so these queries live in the PHP model classes instead.)
--
-- Debtors: orders not fully paid
--   SELECT o.order_id, o.invoice_no, o.order_date, o.customer_id,
--          o.patient_id, o.order_total,
--          COALESCE(SUM(CASE WHEN p.pay_status = 'Success' THEN p.amt END), 0) AS amount_paid,
--          o.order_total - COALESCE(SUM(CASE WHEN p.pay_status = 'Success' THEN p.amt END), 0) AS balance
--   FROM co_orders o
--   LEFT JOIN co_payment p ON p.order_id = o.order_id
--   WHERE o.order_status <> 'Cancelled'
--   GROUP BY o.order_id, o.invoice_no, o.order_date, o.customer_id,
--            o.patient_id, o.order_total
--   HAVING balance > 0;
--
-- Low stock: products at or below their reorder level
--   SELECT product_id, product_title, product_stock, reorder_level
--   FROM co_products
--   WHERE is_service = 0 AND is_active = 1
--     AND product_stock <= reorder_level;
-- ---------------------------------------------------------------------


-- =====================================================================
-- PART F: SYSTEM (notifications, audit trail)
-- =====================================================================

-- ---------------------------------------------------------------------
-- co_notifications: a record of every SMS and email sent, with the
-- provider's reply, so failed messages can be found and resent.
-- ---------------------------------------------------------------------
CREATE TABLE co_notifications (
    notification_id   INT AUTO_INCREMENT PRIMARY KEY,
    channel           ENUM('SMS', 'Email') NOT NULL,
    recipient         VARCHAR(100) NOT NULL,          -- phone number or email address
    message           TEXT         NOT NULL,
    customer_id       INT          NULL,
    patient_id        INT          NULL,
    order_id          INT          NULL,
    appointment_id    INT          NULL,
    notif_status      ENUM('Queued', 'Sent', 'Failed') NOT NULL DEFAULT 'Queued',
    provider_response TEXT         NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at           DATETIME     NULL,
    FOREIGN KEY (customer_id)    REFERENCES co_customer(customer_id)         ON DELETE SET NULL,
    FOREIGN KEY (patient_id)     REFERENCES co_patients(patient_id)          ON DELETE SET NULL,
    FOREIGN KEY (order_id)       REFERENCES co_orders(order_id)              ON DELETE SET NULL,
    FOREIGN KEY (appointment_id) REFERENCES co_appointments(appointment_id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- co_audit_log: who did what and when in the back office.
-- Rows are only ever added, never edited or deleted.
-- ---------------------------------------------------------------------
CREATE TABLE co_audit_log (
    log_id     INT AUTO_INCREMENT PRIMARY KEY,
    staff_id   INT          NULL,
    action     VARCHAR(50)  NOT NULL,                 -- e.g. LOGIN, CREATE, UPDATE, DELETE
    table_name VARCHAR(50)  NULL,
    record_id  INT          NULL,
    details    TEXT         NULL,
    ip_address VARCHAR(50)  NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES co_staff(staff_id) ON DELETE SET NULL,
    INDEX idx_audit_date (created_at)
) ENGINE=InnoDB;


-- =====================================================================
-- PART G: STARTING DATA
-- Only structural data goes here. Real products, brands and prices
-- come from the owner and are added through the admin pages.
-- =====================================================================

-- The five agreed roles.
INSERT INTO co_roles (role_name) VALUES
    ('Admin'),
    ('Receptionist'),
    ('Optometrist'),
    ('Lab Technician'),
    ('Accountant');

-- First Admin account, so you can log in to the back office.
-- Email:    admin@chairmanoptics.local
-- Password: ChangeMe@2026
-- Change both straight after your first login, and always before
-- going live.
INSERT INTO co_staff (role_id, staff_fname, staff_lname, staff_email, staff_contact, staff_pass)
VALUES (1, 'System', 'Admin', 'admin@chairmanoptics.local', '+233000000000',
        '$2y$10$tNj0rQ.3mKnWdDAmnn5ym.kNdhfbmUFwrAj4Fm5qfJtxhQPurOG/a');

-- Product categories. [Confirm this list with the owner.]
INSERT INTO co_categories (cat_name) VALUES
    ('Eyeglass Frames'),
    ('Sunglasses'),
    ('Contact Lenses'),
    ('Lens Care'),
    ('Accessories'),
    ('Services');

-- Lens options from the agreed requirements.
-- Prices are 0.00 until the owner gives the real prices.
INSERT INTO co_lens_options (lens_name, option_group, lens_price) VALUES
    ('Single Vision', 'Lens type', 0.00),
    ('Bifocal',       'Lens type', 0.00),
    ('Progressive',   'Lens type', 0.00),
    ('Photochromic',  'Coating',   0.00),
    ('Blue Cut',      'Coating',   0.00);
