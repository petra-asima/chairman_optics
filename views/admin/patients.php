<?php
// Patient folders: search by folder number, name or phone, and open a new folder.
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/PatientController.php';
require_staff(['Receptionist', 'Optometrist']);

$controller = new PatientController();
$result     = $controller->getPatientPage($_GET);

$pageTitle = 'Patients';
require __DIR__ . '/../layout/admin_header.php';
?>

<div class="admin-toolbar">
    <form method="GET" action="" class="admin-filters">
        <label for="q" class="visually-hidden">Search patients</label>
        <input type="search" id="q" name="q" value="<?= e($result['q']) ?>"
               placeholder="Folder number, name or phone" autofocus>
        <button type="submit" class="btn btn-outline btn-small">
            <span class="material-symbols-outlined" aria-hidden="true">search</span> Search
        </button>
        <?php if ($result['q'] !== ''): ?>
            <a href="<?= url('views/admin/patients.php') ?>" class="admin-link-small">Clear</a>
        <?php endif; ?>
    </form>

    <a href="<?= url('views/admin/patient_form.php') ?>" class="btn btn-primary btn-small">
        <span class="material-symbols-outlined" aria-hidden="true">person_add</span> New Patient
    </a>
</div>

<div class="admin-panel">
    <?php if (empty($result['patients'])): ?>
        <p class="admin-empty">
            <?php if ($result['q'] !== ''): ?>
                No patient matches "<?= e($result['q']) ?>".
                <a href="<?= url('views/admin/patient_form.php') ?>">Open a new folder</a>.
            <?php else: ?>
                No patients yet. <a href="<?= url('views/admin/patient_form.php') ?>">Open the first folder</a>.
            <?php endif; ?>
        </p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Folder</th>
                        <th scope="col">Name</th>
                        <th scope="col">Gender</th>
                        <th scope="col" class="num">Age</th>
                        <th scope="col">Phone</th>
                        <th scope="col">Registered</th>
                        <th scope="col"><span class="visually-hidden">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['patients'] as $p): ?>
                        <?php
                        $link = url('views/admin/patient_detail.php?id=' . (int) $p['patient_id']);
                        $age  = PatientController::age($p['date_of_birth']);
                        ?>
                        <tr>
                            <td><a href="<?= $link ?>"><strong><?= e($p['folder_no']) ?></strong></a></td>
                            <td>
                                <?= e($p['patient_fname'] . ' ' . $p['patient_lname']) ?>
                                <?php if ($p['customer_id']): ?>
                                    <span class="badge badge-info" title="Has an online account">Online</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($p['patient_gender']) ?></td>
                            <td class="num"><?= $age !== null ? $age : '<span class="muted">-</span>' ?></td>
                            <td><?= e($p['patient_contact']) ?></td>
                            <td><?= e(date('d M Y', strtotime($p['date_registered']))) ?></td>
                            <td><a href="<?= $link ?>" class="btn btn-outline btn-small">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="admin-count"><?= $result['total'] ?> patient(s)</p>

        <?php if ($result['pages'] > 1): ?>
            <nav class="pagination" aria-label="Pages">
                <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                    <?php if ($i === $result['page']): ?>
                        <span class="current" aria-current="page"><?= $i ?></span>
                    <?php else: ?>
                        <?php $query = http_build_query(array_filter(['q' => $result['q'], 'page' => $i > 1 ? $i : null])); ?>
                        <a href="<?= e(url('views/admin/patients.php') . ($query ? '?' . $query : '')) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/admin_footer.php'; ?>
