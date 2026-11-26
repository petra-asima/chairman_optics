<?php
// Controller: the booking rules for eye exam appointments.
// Customers book online; the front desk books walk-in and phone patients
// and moves appointments along (Booked > Confirmed > Completed).
require_once __DIR__ . '/../classes/AppointmentClass.php';
require_once __DIR__ . '/../classes/PatientClass.php';
require_once __DIR__ . '/../classes/StaffClass.php';
require_once __DIR__ . '/../classes/AuditClass.php';

class AppointmentController
{
    // ---------- Clinic settings [Confirm all of these with the owner] ----------
    public const OPEN_TIME    = '08:00';   // first appointment of the day
    public const CLOSE_TIME   = '17:00';   // the clinic closes at this time
    public const SLOT_MINUTES = 30;        // length of one appointment
    public const CLOSED_DAYS  = [0];       // 0 = Sunday (PHP's date('w'))
    public const PER_SLOT     = 1;         // patients that can be booked at the same time

    // ---------- Online booking rules [Confirm with the owner] ----------
    public const BOOK_AHEAD_DAYS     = 60; // furthest date a customer can choose
    public const MIN_NOTICE_MINUTES  = 60; // online bookings must start at least this long from now
    public const MAX_UPCOMING_ONLINE = 1;  // upcoming appointments one customer may hold

    public const STATUSES  = ['Booked', 'Confirmed', 'Completed', 'Cancelled', 'No-show'];
    public const DESK_VIAS = ['Walk-in', 'Phone'];

    // Which status can follow which. Completed, Cancelled and No-show are final.
    private const NEXT_STATUSES = [
        'Booked'    => ['Confirmed', 'Completed', 'Cancelled', 'No-show'],
        'Confirmed' => ['Completed', 'Cancelled', 'No-show'],
    ];

    private $appointment;
    private $patient;
    private $staff;
    private $audit;

    public function __construct()
    {
        $this->appointment = new AppointmentClass();
        $this->patient     = new PatientClass();
        $this->staff       = new StaffClass();
        $this->audit       = new AuditClass();
    }

    // =================================================================
    // Dates and time slots
    // =================================================================

    /** Is this a real date written as YYYY-MM-DD? */
    public static function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('!Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }

    /** Is the clinic open on this date? */
    public static function isOpenDay(string $date): bool
    {
        return !in_array((int) date('w', strtotime($date)), self::CLOSED_DAYS, true);
    }

    /** Every slot of the day: ['08:00', '08:30', ... '16:30']. */
    public static function allSlots(): array
    {
        $slots = [];
        $time  = strtotime('2000-01-01 ' . self::OPEN_TIME);
        $close = strtotime('2000-01-01 ' . self::CLOSE_TIME);
        while ($time + self::SLOT_MINUTES * 60 <= $close) {
            $slots[] = date('H:i', $time);
            $time += self::SLOT_MINUTES * 60;
        }
        return $slots;
    }

    /**
     * The slots that can still be booked on a date.
     * @param bool $online  true = customer booking, so the minimum notice applies
     */
    public function getFreeSlots(string $date, bool $online): array
    {
        if (!self::isValidDate($date) || !self::isOpenDay($date) || $date < date('Y-m-d')) {
            return [];
        }

        $taken    = $this->appointment->getTakenTimes($date);
        $earliest = time() + ($online ? self::MIN_NOTICE_MINUTES * 60 : 0);
        $free     = [];

        foreach (self::allSlots() as $slot) {
            $full = ($taken[$slot] ?? 0) >= self::PER_SLOT;
            // Staff can still book the slot that is happening right now (a walk-in)
            $startsAt = strtotime($date . ' ' . $slot) + ($online ? 0 : self::SLOT_MINUTES * 60);
            if (!$full && $startsAt >= $earliest) {
                $free[] = $slot;
            }
        }
        return $free;
    }

    // =================================================================
    // Customer: book online, see and cancel their own
    // =================================================================

    /**
     * A customer books online.
     * @return array ['success' => bool, 'message' => text]
     */
    public function bookOnline(int $customerId, array $data): array
    {
        $date = $data['appointment_date'];
        $time = $data['appointment_time'];

        if (!self::isValidDate($date)) {
            return ['success' => false, 'message' => 'Please choose a date.'];
        }
        if ($date < date('Y-m-d') || $date > date('Y-m-d', strtotime('+' . self::BOOK_AHEAD_DAYS . ' days'))) {
            return ['success' => false, 'message' => 'Please choose a date within the next ' . self::BOOK_AHEAD_DAYS . ' days.'];
        }
        if (!self::isOpenDay($date)) {
            return ['success' => false, 'message' => 'The clinic is closed on that day. Please choose another date.'];
        }
        if (!in_array($time, $this->getFreeSlots($date, true), true)) {
            return ['success' => false, 'message' => 'That time is no longer available. Please choose another.'];
        }
        if (strlen($data['reason']) > 255) {
            return ['success' => false, 'message' => 'Please keep the reason under 255 characters.'];
        }
        if ($this->appointment->countUpcomingForCustomer($customerId) >= self::MAX_UPCOMING_ONLINE) {
            return ['success' => false,
                    'message' => 'You already have an upcoming appointment. Please cancel it first if you need a different time.'];
        }

        // If this account is already linked to a patient folder, link the booking too
        $folder = $this->patient->getPatientByCustomerId($customerId);

        $result = $this->appointment->bookAppointment([
            'customer_id'        => $customerId,
            'patient_id'         => $folder ? (int) $folder['patient_id'] : null,
            'optometrist_id'     => null,
            'appointment_date'   => $date,
            'appointment_time'   => $time . ':00',
            'reason'             => $data['reason'] !== '' ? $data['reason'] : null,
            'booked_via'         => 'Online',
            'appointment_status' => 'Booked',
        ], self::PER_SLOT);

        if ($result === 'full') {
            return ['success' => false, 'message' => 'Sorry, someone has just booked that time. Please choose another.'];
        }
        if ($result === false) {
            return ['success' => false, 'message' => "We couldn't save your booking. Please try again."];
        }

        // [Module: Notifications] A booking confirmation SMS and email go here.

        return ['success' => true,
                'message' => 'Your eye exam is booked for ' . date('l j F Y', strtotime($date)) . ' at '
                           . date('g:i a', strtotime($time)) . '.'];
    }

    public function getCustomerAppointments(int $customerId): array
    {
        return $this->appointment->getCustomerAppointments($customerId);
    }

    /** Can this appointment still be cancelled by the customer? */
    public static function customerCanCancel(array $a): bool
    {
        return in_array($a['appointment_status'], ['Booked', 'Confirmed'], true)
            && strtotime($a['appointment_date'] . ' ' . $a['appointment_time']) > time();
    }

    public function cancelByCustomer(int $appointmentId, int $customerId): array
    {
        $a = $this->appointment->getById($appointmentId);
        if (!$a || (int) $a['customer_id'] !== $customerId || !self::customerCanCancel($a)) {
            return ['success' => false, 'message' => 'That appointment cannot be cancelled.'];
        }
        if (!$this->appointment->cancelByCustomer($appointmentId, $customerId)) {
            return ['success' => false, 'message' => 'That appointment cannot be cancelled.'];
        }
        return ['success' => true, 'message' => 'Your appointment has been cancelled.'];
    }

    // =================================================================
    // Staff: day view, front-desk booking and updates
    // =================================================================

    public function getOptometrists(): array
    {
        return $this->staff->getActiveStaffByRole('Optometrist');
    }

    /** The appointments for one day, and a week of counts around it. */
    public function getDayView(string $date): array
    {
        $from = date('Y-m-d', strtotime($date . ' -3 days'));
        $to   = date('Y-m-d', strtotime($date . ' +3 days'));
        return [
            'appointments' => $this->appointment->getDay($date),
            'week'         => $this->appointment->countByDay($from, $to),
            'from'         => $from,
        ];
    }

    /** The statuses an appointment can move to next (empty when final). */
    public static function nextStatuses(string $current): array
    {
        return self::NEXT_STATUSES[$current] ?? [];
    }

    /** Is this staff id an active Optometrist? */
    private function isOptometrist(int $staffId): bool
    {
        return in_array($staffId, array_map('intval', array_column($this->getOptometrists(), 'staff_id')), true);
    }

    /**
     * The front desk books a walk-in or phone patient by folder number.
     * Desk bookings start as Confirmed: the patient has spoken to us.
     * @return array success + appointment date, or errors
     */
    public function bookAtDesk(array $data, int $staffId): array
    {
        $errors = [];

        $patient = $this->patient->getPatientByFolder(strtoupper($data['folder_no']));
        if (!$patient) {
            $errors['folder_no'] = 'No patient has that folder number. Open a folder for them first.';
        }

        $date = $data['appointment_date'];
        if (!self::isValidDate($date) || $date < date('Y-m-d')) {
            $errors['appointment_date'] = 'Choose today or a later date.';
        } elseif (!self::isOpenDay($date)) {
            $errors['appointment_date'] = 'The clinic is closed on that day.';
        } elseif (!in_array($data['appointment_time'], $this->getFreeSlots($date, false), true)) {
            $errors['appointment_time'] = 'That time is full or has passed. Choose another.';
        }

        if (!in_array($data['booked_via'], self::DESK_VIAS, true)) {
            $errors['booked_via'] = 'Choose walk-in or phone.';
        }
        $optometristId = (int) $data['optometrist_id'];
        if ($optometristId > 0 && !$this->isOptometrist($optometristId)) {
            $errors['optometrist_id'] = 'Choose an optometrist from the list.';
        }
        if (strlen($data['reason']) > 255) {
            $errors['reason'] = 'Please keep the reason under 255 characters.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $result = $this->appointment->bookAppointment([
            'customer_id'        => $patient['customer_id'] !== null ? (int) $patient['customer_id'] : null,
            'patient_id'         => (int) $patient['patient_id'],
            'optometrist_id'     => $optometristId > 0 ? $optometristId : null,
            'appointment_date'   => $date,
            'appointment_time'   => $data['appointment_time'] . ':00',
            'reason'             => $data['reason'] !== '' ? $data['reason'] : null,
            'booked_via'         => $data['booked_via'],
            'appointment_status' => 'Confirmed',
        ], self::PER_SLOT);

        if ($result === 'full') {
            return ['success' => false, 'errors' => ['appointment_time' => 'Someone has just booked that time. Choose another.']];
        }
        if ($result === false) {
            return ['success' => false, 'errors' => ['form' => "We couldn't save the booking. Please try again."]];
        }

        $this->audit->log($staffId, 'CREATE',
            'Booked ' . $patient['folder_no'] . ' for ' . $date . ' ' . $data['appointment_time'] . ' (' . $data['booked_via'] . ')',
            'co_appointments', (int) $result);

        return ['success' => true, 'date' => $date,
                'message' => 'Booked ' . $patient['patient_fname'] . ' ' . $patient['patient_lname'] . ' ('
                           . $patient['folder_no'] . ') for ' . date('D j M', strtotime($date)) . ' at '
                           . date('g:i a', strtotime($data['appointment_time'])) . '.'];
    }

    /**
     * Save the front desk's changes to one appointment.
     * @param array $data  appointment_status, optometrist_id, folder_no, notes
     */
    public function updateByStaff(int $appointmentId, array $data, int $staffId): array
    {
        $a = $this->appointment->getById($appointmentId);
        if (!$a) {
            return ['success' => false, 'message' => 'That appointment was not found.', 'date' => date('Y-m-d')];
        }
        $fail = fn(string $message): array => ['success' => false, 'message' => $message, 'date' => $a['appointment_date']];

        // The status may stay the same, or move to an allowed next status
        $status = $data['appointment_status'];
        if ($status !== $a['appointment_status'] && !in_array($status, self::nextStatuses($a['appointment_status']), true)) {
            return $fail('An appointment that is ' . $a['appointment_status'] . ' cannot be changed to ' . $status . '.');
        }

        // Link (or change) the patient folder by folder number
        $patientId = $a['patient_id'] !== null ? (int) $a['patient_id'] : null;
        $folderNo  = strtoupper(trim($data['folder_no']));
        if ($folderNo !== '' && $folderNo !== $a['folder_no']) {
            $patient = $this->patient->getPatientByFolder($folderNo);
            if (!$patient) {
                return $fail('No patient has folder number ' . $folderNo . '.');
            }
            $patientId = (int) $patient['patient_id'];
        }

        // Business rule: the exam is recorded in a folder, so a completed
        // appointment must belong to one
        if ($status === 'Completed' && $patientId === null) {
            return $fail('Link a patient folder before marking the appointment Completed.');
        }

        $optometristId = (int) $data['optometrist_id'];
        if ($optometristId > 0 && !$this->isOptometrist($optometristId)) {
            return $fail('Choose an optometrist from the list.');
        }
        if (strlen($data['notes']) > 255) {
            return $fail('Notes can be up to 255 characters.');
        }

        $ok = $this->appointment->updateAppointment($appointmentId, $status,
                                                    $optometristId > 0 ? $optometristId : null,
                                                    $patientId, $data['notes'] !== '' ? $data['notes'] : null);
        if (!$ok) {
            return $fail("We couldn't save the changes. Please try again.");
        }

        $this->audit->log($staffId, 'UPDATE',
            'Appointment ' . $a['appointment_date'] . ' ' . substr($a['appointment_time'], 0, 5) . ' for '
            . $a['person_name'] . ': ' . $a['appointment_status'] . ' to ' . $status,
            'co_appointments', $appointmentId);

        return ['success' => true, 'message' => 'Appointment for ' . $a['person_name'] . ' saved.', 'date' => $a['appointment_date']];
    }
}
