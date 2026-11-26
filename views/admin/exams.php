<?php
// Eye Exams: recent exams, searchable by folder number or name.
// New exams are started from an appointment or a patient folder.
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/ExamController.php';
require_staff(['Optometrist']);

$controller = new ExamController();
$result     = $controller->getExamPage($_GET);

$pageTitle = 'Eye Exams';
require __DIR__ . '/../layout/admin_header.php';
?>

<div class="admin-toolbar">
    <form method="GET" action="" class="admin-filters">
        <label for="q" class="visually-hidden">Search exams</label>
        <input type="search" id="q" name="q" value="<?= e($result['q']) ?>" placeholder="Folder number or name">
        <button type="submit" class="btn btn-outline btn-small">
            <span class="material-symbols-outlined" aria-hidden="true">search</span> Search
        </button>
        <?php if ($result['q'] !== ''): ?>
            <a href="<?= url('views/admin/exams.php') ?>" class="admin-link-small">Clear</a>
        <?php endif; ?>
    </form>
    <p class="admin-intro">To start an exam, open today's <a href="<?= url('views/admin/appointments.php') ?>">appointments</a> or a <a href="<?= url('views/admin/patients.php') ?>">patient folder</a>.</p>
</div>

<div class="admin-panel">
    <?php if (empty($result['exams'])): ?>
        <p class="admin-empty">No eye exams <?= $result['q'] !== '' ? 'match "' . e($result['q']) . '"' : 'recorded yet' ?>.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Patient</th>
                        <th scope="col">Complaint</th>
                        <th scope="col">Diagnosis</th>
                        <th scope="col">Optometrist</th>
                        <th scope="col"><span class="visually-hidden">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['exams'] as $x): ?>
                        <tr>
                            <td><?= e(date('d M Y', strtotime($x['exam_date']))) ?></td>
                            <td>
                                <?= e($x['patient_fname'] . ' ' . $x['patient_lname']) ?>
                                <br><small class="muted"><?= e($x['folder_no']) ?></small>
                            </td>
                            <td><?= e($x['chief_complaint'] ?? '') ?></td>
                            <td>
                                <?= e(mb_strimwidth($x['diagnosis'] ?? '', 0, 60, '…')) ?>
                                <?php if ((int) $x['has_prescription'] > 0): ?><span class="badge badge-info">Rx</span><?php endif; ?>
                            </td>
                            <td><?= e($x['optometrist_name'] ?? '') ?></td>
                            <td><a href="<?= url('views/admin/exam_detail.php?id=' . (int) $x['exam_id']) ?>" class="btn btn-outline btn-small">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="admin-count"><?= $result['total'] ?> exam(s)</p>

        <?php if ($result['pages'] > 1): ?>
            <nav class="pagination" aria-label="Pages">
                <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                    <?php if ($i === $result['page']): ?>
                        <span class="current" aria-current="page"><?= $i ?></span>
                    <?php else: ?>
                        <?php $query = http_build_query(array_filter(['q' => $result['q'], 'page' => $i > 1 ? $i : null])); ?>
                        <a href="<?= e(url('views/admin/exams.php') . ($query ? '?' . $query : '')) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/admin_footer.php'; ?>
