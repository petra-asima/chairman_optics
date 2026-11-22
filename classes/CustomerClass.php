<?php
// Model: all SQL for the co_customer table lives here.
// All Chairman Optics tables start with co_ so they can share a
// database with other projects without name clashes.
require_once __DIR__ . '/../core/db_class.php';

class CustomerClass extends Database
{
    /**
     * Is this email already registered?
     * @return bool
     */
    public function emailExists(string $email): bool
    {
        $stmt = $this->conn->prepare('SELECT customer_id FROM co_customer WHERE customer_email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /**
     * Insert a new customer. $hash must already be made with password_hash().
     * @return int|false  the new customer_id, or false if the insert failed
     */
    public function addCustomer(string $fname, string $lname, string $gender,
                                string $email, string $contact, string $hash)
    {
        try {
            $stmt = $this->conn->prepare(
                'INSERT INTO co_customer
                    (customer_fname, customer_lname, customer_gender,
                     customer_email, customer_contact, customer_pass)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('ssssss', $fname, $lname, $gender, $email, $contact, $hash);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();
            return $newId;
        } catch (mysqli_sql_exception $ex) {
            log_error('addCustomer failed: ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * One customer row by email (includes the password hash, for login only).
     * @return array|false
     */
    public function getCustomerByEmail(string $email)
    {
        $stmt = $this->conn->prepare('SELECT * FROM co_customer WHERE customer_email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    /**
     * Find the customer, then check the password with password_verify().
     * @return array|false  the customer row on success, false on failure
     */
    public function login(string $email, string $pass)
    {
        $row = $this->getCustomerByEmail($email);

        if ($row && password_verify($pass, $row['customer_pass'])) {
            return $row;
        }
        return false;
    }

    /**
     * One customer's details by id. The password hash is not selected.
     * @return array|false
     */
    public function getCustomerById(int $id)
    {
        $stmt = $this->conn->prepare(
            'SELECT customer_id, customer_fname, customer_lname, customer_gender,
                    customer_email, customer_contact, date_created
             FROM co_customer WHERE customer_id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }
}