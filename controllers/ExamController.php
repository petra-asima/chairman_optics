<?php
// Controller: checks the eye exam form, saves attachments safely, and
// turns the refraction into a prescription.
require_once __DIR__ . '/../classes/ExamClass.php';
require_once __DIR__ . '/../classes/PatientClass.php';
require_once __DIR__ . '/../classes/AppointmentClass.php';
require_once __DIR__ . '/../classes/AuditClass.php';

class ExamController
{
    // Visual acuity choices (Snellen, metric), best to worst.
    // CF = counts fingers, HM = hand movements, PL = perceives light,
    // NPL = no perception of light. A list stops typing mistakes.
    public const VA_VALUES = ['6/4', '6/5', '6/6', '6/7.5', '6/9', '6/12', '6/18', '6/24', '6/36', '6/60',
                              '3/60', '1/60', 'CF', 'HM', 'PL', 'NPL'];

    // The six visual acuity boxes: column name => label
    public const VA_FIELDS = [
        'va_unaided_re' => 'Unaided, right eye (RE)',
        'va_unaided_le' => 'Unaided, left eye (LE)',
        'va_aided_re'   => 'Aided, right eye (RE)',
        'va_aided_le'   => 'Aided, left eye (LE)',
        'va_pinhole_re' => 'Pinhole, right eye (RE)',
        'va_pinhole_le' => 'Pinhole, left eye (LE)',
    ];

    // Allowed ranges for the refraction (in dioptres, axis in degrees, PD in mm)
    private const SPHERE_LIMIT   = 30.0;
    private const CYLINDER_LIMIT = 10.0;
    private const ADD_MAX        = 4.0;
    private const PD_MIN         = 40.0;
    private const PD_MAX         = 80.0;

    // Attachment rules
    public const MAX_FILES = 3;
    private const MAX_FILE_BYTES = 5 * 1024 * 1024;
    private const FILE_TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
    ];
    private const UPLOAD_FOLDER = __DIR__ . '/../uploads/exam_attachments/';

    public const PER_PAGE = 25;

    private $exam;
    private $patient;
    private $appointment;
    private $audit;

    public function __construct()
    {
        $this->exam        = new ExamClass();
        $this->patient     = new PatientClass();
        $this->appointment = new AppointmentClass();
        $this->audit       = new AuditClass();
    }

    // =================================================================
    // Reading
    // =================================================================

    /**
     * Who the exam is for, from ?appointment_id=N or ?patient_id=N.
     * An appointment must already be linked to a patient folder.
     * @return array ['patient' => row, 'appointment' => row|null] or ['error' => text]
     */
    public function getStartContext(array $get): array
    {
        $appointmentId = (int) ($get['appointment_id'] ?? 0);
        if ($appointmentId > 0) {
            $a = $this->appointment->getById($appointmentId);
            if (!$a || !in_array($a['appointment_status'], ['Booked', 'Confirmed'], true)) {
                return ['error' => 'That appointment is not open for an exam.'];
            }
            if ($a['patient_id'] === null) {
                return ['error' => 'Link a patient folder to this appointment first (on the Appointments page).'];
            }
            return ['patient' => $this->patient->getPatientById((int) $a['patient_id']), 'appointment' => $a];
        }

        $patient = $this->patient->getPatientById((int) ($get['patient_id'] ?? 0));
        if (!$patient) {
            return ['error' => 'Choose a patient first: open their folder and click "Start Eye Exam".'];
        }
        return ['patient' => $patient, 'appointment' => null];
    }

    /** @return array|false */
    public function getExam(int $examId)
    {
        return $this->exam->getExam($examId);
    }

    public function getExamPage(array $get): array
    {
        $q     = mb_substr(trim((string) ($get['q'] ?? '')), 0, 100);
        $total = $this->exam->countExams($q);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min(max(1, (int) ($get['page'] ?? 1)), $pages);

        return ['q' => $q, 'total' => $total, 'page' => $page, 'pages' => $pages,
                'exams' => $this->exam->getExams($q, self::PER_PAGE, ($page - 1) * self::PER_PAGE)];
    }

    /**
     * A lens power for printing: +1.25, -0.50, or "Plano" for zero sphere.
     * @param bool $isSphere  a zero sphere is written "Plano" by optometrists
     */
    public static function formatPower($value, bool $isSphere = false): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        $value = (float) $value;
        if ($value == 0.0) {
            return $isSphere ? 'Plano' : '0.00';
        }
        return ($value > 0 ? '+' : '') . number_format($value, 2);
    }

    // =================================================================
    // Saving an exam
    // =================================================================

    /**
     * Read one number from the refraction boxes.
     * @return array [bool $ok, float|null $value]  (an empty box is ok and null)
     */
    private function readNumber(string $text): array
    {
        $text = str_replace(' ', '', $text);
        if ($text === '') {
            return [true, null];
        }
        if (!preg_match('/^[+-]?\d{1,2}(\.\d{1,2})?$/', $text)) {
            return [false, null];
        }
        return [true, (float) $text];
    }

    /** Lens powers come in steps of 0.25 dioptres. */
    private function isQuarterStep(float $value): bool
    {
        return abs($value * 4 - round($value * 4)) < 0.0001;
    }

    /**
     * Check one eye's refraction.
     * @param string $eye  're' or 'le'
     * @return array [errors, values]
     */
    private function checkEye(array $data, string $eye): array
    {
        $errors = [];
        $label  = $eye === 're' ? 'Right eye' : 'Left eye';
        $values = [];

        foreach (['sphere', 'cylinder', 'add'] as $part) {
            [$ok, $value] = $this->readNumber($data[$eye . '_' . $part]);
            $limit = ['sphere' => self::SPHERE_LIMIT, 'cylinder' => self::CYLINDER_LIMIT, 'add' => self::ADD_MAX][$part];

            if (!$ok || ($value !== null && (abs($value) > $limit || !$this->isQuarterStep($value)))) {
                $errors[$eye . '_' . $part] = $label . ' ' . $part . ': a number up to ' . $limit . ' in steps of 0.25.';
            } elseif ($part === 'add' && $value !== null && $value <= 0) {
                $errors[$eye . '_add'] = $label . ' add must be a positive number (e.g. +2.00).';
            }
            $values[$part] = $value;
        }

        // Axis: a whole number from 1 to 180, needed exactly when there is a cylinder
        $axisText = trim($data[$eye . '_axis']);
        $axis = null;
        if ($axisText !== '') {
            $axis = filter_var($axisText, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 180]]);
            if ($axis === false) {
                $errors[$eye . '_axis'] = $label . ' axis: a whole number from 1 to 180.';
                $axis = null;
            }
        }
        $hasCylinder = $values['cylinder'] !== null && $values['cylinder'] != 0.0;
        if ($hasCylinder && $axis === null && !isset($errors[$eye . '_axis'])) {
            $errors[$eye . '_axis'] = $label . ': a cylinder needs an axis.';
        }
        if (!$hasCylinder && $axis !== null) {
            $errors[$eye . '_axis'] = $label . ': an axis is only used with a cylinder.';
        }
        $values['axis'] = $axis;

        return [$errors, $values];
    }

    /**
     * Check every field of the exam form.
     * @return array [errors, prescription values or null]
     */
    public function validateExam(array $data): array
    {
        $errors = [];

        foreach (array_keys(self::VA_FIELDS) as $field) {
            if ($data[$field] !== '' && !in_array($data[$field], self::VA_VALUES, true)) {
                $errors[$field] = 'Choose a value from the list.';
            }
        }
        if (strlen($data['chief_complaint']) > 255) {
            $errors['chief_complaint'] = 'The complaint can be up to 255 characters.';
        }
        if (strlen($data['diagnosis']) > 2000 || strlen($data['notes']) > 2000) {
            $errors['diagnosis'] = 'Diagnosis and notes can be up to 2000 characters each.';
        }

        [$reErrors, $re] = $this->checkEye($data, 're');
        [$leErrors, $le] = $this->checkEye($data, 'le');
        $errors = array_merge($errors, $reErrors, $leErrors);

        [$ok, $pd] = $this->readNumber($data['pd']);
        if (!$ok || ($pd !== null && ($pd < self::PD_MIN || $pd > self::PD_MAX))) {
            $errors['pd'] = 'PD: a number of millimetres from ' . self::PD_MIN . ' to ' . self::PD_MAX . '.';
        }
        if (strlen($data['rx_notes']) > 2000) {
            $errors['rx_notes'] = 'Prescription notes can be up to 2000 characters.';
        }

        // A prescription is made when any sphere or cylinder was entered
        $prescription = null;
        $anyPower = $re['sphere'] !== null || $re['cylinder'] !== null || $le['sphere'] !== null || $le['cylinder'] !== null;
        if ($anyPower) {
            $prescription = [
                're_sphere' => $re['sphere'], 're_cylinder' => $re['cylinder'], 're_axis' => $re['axis'], 're_add' => $re['add'],
                'le_sphere' => $le['sphere'], 'le_cylinder' => $le['cylinder'], 'le_axis' => $le['axis'], 'le_add' => $le['add'],
                'pd'        => $pd,
                'notes'     => $data['rx_notes'] !== '' ? $data['rx_notes'] : null,
            ];
        } elseif ($re['add'] !== null || $le['add'] !== null || $pd !== null) {
            $errors['re_sphere'] = 'Enter the sphere (use 0 for Plano) before an add or PD.';
        }

        return [$errors, $prescription];
    }

    /**
     * Turn PHP's $_FILES layout for a multiple upload into a simple list.
     * $_FILES['x']['name'][0], ['tmp_name'][0] ... becomes [['name' => ..., 'tmp_name' => ...], ...]
     */
    public static function listUploads(array $files): array
    {
        $list = [];
        foreach ($files['name'] ?? [] as $i => $name) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue; // an empty file box
            }
            $list[] = [
                'name'     => $name,
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
        }
        return $list;
    }

    /** Check every uploaded file before any is saved. @return string an error, or '' */
    private function checkUploads(array $uploads): string
    {
        if (count($uploads) > self::MAX_FILES) {
            return 'You can attach up to ' . self::MAX_FILES . ' files.';
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        foreach ($uploads as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
                return '"' . $file['name'] . '" did not upload. Please try again.';
            }
            if ($file['size'] > self::MAX_FILE_BYTES) {
                return '"' . $file['name'] . '" is larger than 5 MB.';
            }
            // The real type is read from the file, not from its name
            if (!isset(self::FILE_TYPES[$finfo->file($file['tmp_name'])])) {
                return '"' . $file['name'] . '" must be a PDF, JPG or PNG.';
            }
        }
        return '';
    }

    /**
     * Move checked uploads into uploads/exam_attachments/ with random names.
     * @return array|false  file_name + file_path for each, or false if one could not be saved
     */
    private function storeUploads(array $uploads)
    {
        $finfo  = new finfo(FILEINFO_MIME_TYPE);
        $stored = [];
        foreach ($uploads as $file) {
            $savedName = 'exam_' . bin2hex(random_bytes(8)) . '.' . self::FILE_TYPES[$finfo->file($file['tmp_name'])];
            if (!move_uploaded_file($file['tmp_name'], self::UPLOAD_FOLDER . $savedName)) {
                log_error('Could not save exam attachment. Check permissions on uploads/exam_attachments/.');
                $this->deleteStored($stored);
                return false;
            }
            // Keep the original name only for display, cleaned and shortened
            $original = mb_substr(preg_replace('/[^\w .()-]/u', '_', basename($file['name'])), 0, 100);
            $stored[] = ['file_name' => $original, 'file_path' => $savedName];
        }
        return $stored;
    }

    /** Remove files that were saved, if the database save failed. */
    private function deleteStored(array $stored): void
    {
        foreach ($stored as $file) {
            $path = self::UPLOAD_FOLDER . basename($file['file_path']);
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Save a whole exam.
     * @param array $files  the list from listUploads()
     * @return array ['success' => true, 'exam_id' => N, 'message' => ...]
     *               or ['success' => false, 'errors' => [...]]
     */
    public function saveExam(array $data, array $files, int $optometristId): array
    {
        $patient = $this->patient->getPatientById((int) $data['patient_id']);
        if (!$patient) {
            return ['success' => false, 'errors' => ['form' => 'That patient was not found.']];
        }

        // An appointment, if given, must be open and belong to this patient
        $appointmentId = (int) $data['appointment_id'];
        if ($appointmentId > 0) {
            $a = $this->appointment->getById($appointmentId);
            if (!$a || (int) $a['patient_id'] !== (int) $patient['patient_id']
                || !in_array($a['appointment_status'], ['Booked', 'Confirmed'], true)) {
                return ['success' => false, 'errors' => ['form' => 'That appointment is no longer open for an exam.']];
            }
        }

        [$errors, $prescription] = $this->validateExam($data);
        $fileError = $this->checkUploads($files);
        if ($fileError !== '') {
            $errors['attachments'] = $fileError;
        }
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $stored = $this->storeUploads($files);
        if ($stored === false) {
            return ['success' => false, 'errors' => ['attachments' => "We couldn't save the attachments. Please tell the Admin."]];
        }

        $exam = [
            'patient_id'      => (int) $patient['patient_id'],
            'appointment_id'  => $appointmentId > 0 ? $appointmentId : null,
            'optometrist_id'  => $optometristId,
            'chief_complaint' => $data['chief_complaint'] !== '' ? $data['chief_complaint'] : null,
            'diagnosis'       => $data['diagnosis'] !== '' ? $data['diagnosis'] : null,
            'notes'           => $data['notes'] !== '' ? $data['notes'] : null,
        ];
        foreach (array_keys(self::VA_FIELDS) as $field) {
            $exam[$field] = $data[$field] !== '' ? $data[$field] : null;
        }

        if ($prescription !== null) {
            // The prescription also belongs to the patient's online account, if linked
            $prescription['customer_id'] = $patient['customer_id'] !== null ? (int) $patient['customer_id'] : null;
        }

        $examId = $this->exam->saveExam($exam, $prescription, $stored);
        if ($examId === false) {
            $this->deleteStored($stored); // do not leave files with no exam
            return ['success' => false, 'errors' => ['form' => "We couldn't save the exam. Please try again."]];
        }

        $this->audit->log($optometristId, 'CREATE',
            'Eye exam for ' . $patient['folder_no'] . ($prescription ? ' with prescription' : '')
            . (count($stored) ? ', ' . count($stored) . ' attachment(s)' : ''),
            'co_eye_exams', $examId);

        return ['success' => true, 'exam_id' => $examId,
                'message' => 'Eye exam saved for ' . $patient['patient_fname'] . ' ' . $patient['patient_lname']
                           . ($prescription ? ', with a prescription.' : '.')];
    }

    // =================================================================
    // Attachments
    // =================================================================

    /**
     * Find an attachment file to send to the browser.
     * @return array|false  file_name (to show) and full_path (on disk)
     */
    public function getAttachmentFile(int $attachmentId)
    {
        $row = $this->exam->getAttachment($attachmentId);
        if (!$row) {
            return false;
        }
        // basename() stops a stored path ever pointing outside the folder
        $fullPath = self::UPLOAD_FOLDER . basename($row['file_path']);
        if (!is_file($fullPath)) {
            return false;
        }
        return ['file_name' => $row['file_name'], 'full_path' => $fullPath, 'exam_id' => (int) $row['exam_id']];
    }
}
