<?php
// Controller: gathers the dashboard figures the logged-in role may see.
// Each figure is only worked out for the roles that use that section
// (the same roles as the menu), so, for example, an Optometrist never
// sees sales figures and no time is spent on queries nobody will see.
require_once __DIR__ . '/../classes/DashboardClass.php';
require_once __DIR__ . '/../classes/ProductClass.php';

class DashboardController
{
    // How far back the best-sellers list looks
    public const BEST_SELLER_DAYS = 30;

    private $dashboard;
    private $product;

    public function __construct()
    {
        $this->dashboard = new DashboardClass();
        $this->product   = new ProductClass();
    }

    /**
     * Every figure this role may see. A figure the role may not see is null.
     * @return array
     */
    public function getDashboard(): array
    {
        // Today runs from midnight to midnight, clinic time
        $today    = date('Y-m-d');
        $from     = $today . ' 00:00:00';
        $to       = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
        $bestFrom = date('Y-m-d', strtotime('-' . self::BEST_SELLER_DAYS . ' days')) . ' 00:00:00';

        $figures = [
            'sales'        => null,
            'patients'     => null,
            'glasses'      => null,
            'low_stock'    => null,
            'best_sellers' => null,
        ];

        // Money: Receptionist and Accountant (and the Admin)
        if (staff_can(['Receptionist', 'Accountant'])) {
            $sales = $this->dashboard->getSalesBetween($from, $to);
            $sales['received'] = $this->dashboard->getPaymentsBetween($from, $to);
            $figures['sales'] = $sales;

            $figures['best_sellers'] = $this->dashboard->getBestSellers($bestFrom, 5);
        }

        // Clinic: Receptionist and Optometrist
        if (staff_can(['Receptionist', 'Optometrist'])) {
            $patients = $this->dashboard->getAppointmentsOn($today);
            $patients['new_patients'] = $this->dashboard->countNewPatientsBetween($from, $to);
            $figures['patients'] = $patients;
        }

        // Lab: Receptionist and Lab Technician
        if (staff_can(['Receptionist', 'Lab Technician'])) {
            $figures['glasses'] = $this->dashboard->getGlassesInProgress();
        }

        // Stock: Receptionist (same list as the Stock page)
        if (staff_can(['Receptionist'])) {
            $figures['low_stock'] = $this->product->getLowStock();
        }

        return $figures;
    }
}
