<?php
// Controller: validates customer data, then calls the CustomerClass model.
require_once __DIR__ . '/../classes/CustomerClass.php';

class CustomerController
{
    private $customer;

    public function __construct()
    {
        $this->customer = new CustomerClass();
    }

    /**
     * Check every registration field.
     * @return array  field name => error message (empty when all is fine)
     */
    public function validateRegistration(array $data): array
    {
        $errors = [];
        $namePattern = "/^[A-Za-z][A-Za-z' -]{1,49}$/";

        if (!preg_match($namePattern, $data['fname'])) {
            $errors['fname'] = 'Enter your first name using letters only.';
        }
        if (!preg_match($namePattern, $data['lname'])) {
            $errors['lname'] = 'Enter your surname using letters only.';
        }
        if (!in_array($data['gender'], GENDERS, true)) {
            $errors['gender'] = 'Please select a gender.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 100) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (!preg_match('/^(\+233|0)[235][0-9]{8}$/', $data['contact'])) {
            $errors['contact'] = 'Enter a valid Ghana phone number, for example 024 123 4567.';
        }
        if (strlen($data['password']) < 8
            || !preg_match('/[A-Za-z]/', $data['password'])
            || !preg_match('/[0-9]/', $data['password'])) {
            $errors['password'] = 'Use at least 8 characters with at least one letter and one number.';
        }
        if ($data['password'] !== $data['confirm']) {
            $errors['confirm'] = 'The passwords do not match.';
        }

        return $errors;
    }

    /**
     * Register a new customer.
     * @return array ['success' => true, 'customer_id' => N] or ['success' => false, 'errors' => [...]]
     */
    public function register(array $data): array
    {
        $errors = $this->validateRegistration($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        if ($this->customer->emailExists($data['email'])) {
            return ['success' => false,
                    'errors'  => ['email' => 'An account with this email already exists. Please log in.']];
        }

        // Save every phone number the same way: 0241234567 becomes +233241234567
        $contact = preg_replace('/^0/', '+233', $data['contact']);
        $hash    = password_hash($data['password'], PASSWORD_DEFAULT);

        $newId = $this->customer->addCustomer(
            $data['fname'], $data['lname'], $data['gender'],
            $data['email'], $contact, $hash
        );

        if ($newId === false) {
            return ['success' => false,
                    'errors'  => ['form' => "We couldn't create your account. Please try again."]];
        }

        return ['success' => true, 'customer_id' => $newId];
    }

    /**
     * Log a customer in.
     * @return array the customer row on success, or ['error' => '...'] on failure
     */
    public function login(string $email, string $pass): array
    {
        $customer = $this->customer->login($email, $pass);

        if ($customer === false) {
            return ['error' => 'Incorrect email or password.'];
        }
        return $customer;
    }

    /** One customer's details, or false. */
    public function getCustomerById(int $id)
    {
        return $this->customer->getCustomerById($id);
    }
}