<?php
// Controller: checks patient details, stops duplicate folders, links a
// folder to the patient's online account, then calls PatientClass.
require_once __DIR__ . '/../classes/PatientClass.php';
require_once __DIR__ . '/../classes/AuditClass.php';

class PatientController
{
    // Patients on each page of the list
    public const PER_PAGE = 25;

    private $patient;
    private $audit;

    public function __construct()
    {
        $this->patient = new PatientClass();
        $this->audit   = new AuditClass();
    }

    // =================================================================
    // Reading
    // =================================================================

    /**
     * Phone numbers are saved as +233..., but staff usually type 024...
     * Return the extra spelling to search for as well.
     */
    private function phoneForms(string $q): array
    {
        $digits = str_replace(' ', '', $q);
        if (preg_match('/^0[0-9]{2,}$/', $digits)) {
            return ['+233' . substr($digits, 1)];
        }
        return [];
    }

    /** One page of the patient list, with an optional search. */
    public function getPatientPage(array $get): array
    {
        $q    = mb_substr(trim((string) ($get['q'] ?? '')), 0, 100);
        $page = max(1, (int) ($get['page'] ?? 1));

        $phones = $this->phoneForms($q);
        $total  = $this->patient->countPatients($q, $phones);
        $pages  = max(1, (int) ceil($total / self::PER_PAGE));
        $page   = min($page, $pages);

        return [
            'q'        => $q,
            'patients' => $this->patient->searchPatients($q, $phones, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'total'    => $total,
            'page'     => $page,
            'pages'    => $pages,
        ];
    }

    /** @return array|false */
    public function getPatient(int $patientId)
    {
        return $this->patient->getPatientById($patientId);
    }

    /** The patient's visit timeline, newest first. */
    public function getTimeline(array $patient): array
    {
        $customerId = $patient['customer_id'] !== null ? (int) $patient['customer_id'] : null;
        return $this->patient->getTimeline((int) $patient['patient_id'], $customerId);
    }

    /** Age in whole years from a date of birth, or null if not known. */
    public static function age(?string $dateOfBirth): ?int
    {
        if (!$dateOfBirth) {
            return null;
        }
        return (new DateTime($dateOfBirth))->diff(new DateTime('today'))->y;
    }

    // =================================================================
    // Adding and editing
    // =================================================================

    /**
     * Check every field.
     * @return array field name => error message (empty when all is fine)
     */
    public function validatePatient(array $data): array
    {
        $errors = [];
        $namePattern = "/^[A-Za-z][A-Za-z' -]{1,49}$/";

        if (!preg_match($namePattern, $data['patient_fname'])) {
            $errors['patient_fname'] = 'Enter the first name using letters only.';
        }
        if (!preg_match($namePattern, $data['patient_lname'])) {
            $errors['patient_lname'] = 'Enter the surname using letters only.';
        }
        if (!in_array($data['patient_gender'], GENDERS, true)) {
            $errors['patient_gender'] = 'Please choose an option.';
        }
        if (!preg_match('/^(\+233|0)[235][0-9]{8}$/', $data['patient_contact'])) {
            $errors['patient_contact'] = 'Enter a valid Ghana phone number, for example 024 123 4567.';
        }

        // Date of birth is optional, but must be a real date in the past
        if ($data['date_of_birth'] !== '') {
            $dob = DateTime::createFromFormat('!Y-m-d', $data['date_of_birth']);
            $oldest = new DateTime('-120 years');
            if (!$dob || $dob->format('Y-m-d') !== $data['date_of_birth']
                || $dob > new DateTime('today') || $dob < $oldest) {
                $errors['date_of_birth'] = 'Enter a real date of birth (not in the future).';
            }
        }

        if ($data['patient_email'] !== ''
            && (!filter_var($data['patient_email'], FILTER_VALIDATE_EMAIL) || strlen($data['patient_email']) > 100)) {
            $errors['patient_email'] = 'Enter a valid email address, or leave it empty.';
        }
        if (strlen($data['patient_address']) > 255) {
            $errors['patient_address'] = 'The address can be up to 255 characters.';
        }
        if (strlen($data['occupation']) > 100) {
            $errors['occupation'] = 'The occupation can be up to 100 characters.';
        }

        return $errors;
    }

    /**
     * Turn checked form data into the row the model saves.
     * The phone is saved as +233..., empty fields become NULL, and the
     * folder is linked to an online account with the same email.
     */
    private function buildRow(array $data): array
    {
        $email = $data['patient_email'] !== '' ? strtolower($data['patient_email']) : null;

        return [
            'customer_id'     => $email !== null ? $this->patient->findCustomerIdByEmail($email) : null,
            'patient_fname'   => $data['patient_fname'],
            'patient_lname'   => $data['patient_lname'],
            'patient_gender'  => $data['patient_gender'],
            'date_of_birth'   => $data['date_of_birth'] !== '' ? $data['date_of_birth'] : null,
            'patient_contact' => preg_replace('/^0/', '+233', $data['patient_contact']),
            'patient_email'   => $email,
            'patient_address' => $data['patient_address'] !== '' ? $data['patient_address'] : null,
            'occupation'      => $data['occupation'] !== '' ? $data['occupation'] : null,
        ];
    }

    /**
     * Open a new folder.
     * @return array ['success' => true, 'patient_id' => N, 'message' => ...]
     *               or ['success' => false, 'errors' => [...]]
     */
    public function addPatient(array $data, int $staffId): array
    {
        $errors = $this->validatePatient($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $row = $this->buildRow($data);

        // Business rule: one person, one folder
        $duplicate = $this->patient->findDuplicate($row['patient_fname'], $row['patient_lname'], $row['patient_contact']);
        if ($duplicate) {
            return ['success' => false,
                    'errors'  => ['form' => 'This patient already has folder ' . $duplicate['folder_no']
                                          . '. Search for it instead of opening a new one.']];
        }

        $row['registered_by'] = $staffId;
        $saved = $this->patient->addPatient($row);
        if ($saved === false) {
            return ['success' => false, 'errors' => ['form' => "We couldn't open the folder. Please try again."]];
        }

        $name = $row['patient_fname'] . ' ' . $row['patient_lname'];
        $this->audit->log($staffId, 'CREATE', 'Opened folder ' . $saved['folder_no'] . ' for ' . $name,
                          'co_patients', $saved['patient_id']);

        $message = 'Folder ' . $saved['folder_no'] . ' opened for ' . $name . '.';
        if ($row['customer_id'] !== null) {
            $message .= ' Linked to their online account.';
        }
        return ['success' => true, 'patient_id' => $saved['patient_id'], 'message' => $message];
    }

    /** Update a patient's details (the folder number never changes). */
    public function updatePatient(int $patientId, array $data, int $staffId): array
    {
        $old = $this->patient->getPatientById($patientId);
        if (!$old) {
            return ['success' => false, 'errors' => ['form' => 'That patient was not found.']];
        }

        $errors = $this->validatePatient($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $row = $this->buildRow($data);

        $duplicate = $this->patient->findDuplicate($row['patient_fname'], $row['patient_lname'],
                                                   $row['patient_contact'], $patientId);
        if ($duplicate) {
            return ['success' => false,
                    'errors'  => ['form' => 'Another folder (' . $duplicate['folder_no'] . ') already has this name and phone number.']];
        }

        if (!$this->patient->updatePatient($patientId, $row)) {
            return ['success' => false, 'errors' => ['form' => "We couldn't save the changes. Please try again."]];
        }

        $this->audit->log($staffId, 'UPDATE', 'Updated folder ' . $old['folder_no'], 'co_patients', $patientId);
        return ['success' => true, 'patient_id' => $patientId,
                'message' => 'Folder ' . $old['folder_no'] . ' updated.'];
    }
}
