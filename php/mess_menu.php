<?php
require_once '../includes/config.php';
$pageTitle = 'Mess Menu';
$activePage = 'mess';
requireLogin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isWarden()) {
    foreach ($_POST['menu'] as $day => $meals) {
        foreach ($meals as $meal_type => $menu_items) {
            $day_s        = sanitize($day);
            $meal_type_s  = sanitize($meal_type);
            $menu_items_s = sanitize($menu_items);

            $exists = $db->query("SELECT id FROM mess_menu WHERE day_of_week='$day_s' AND meal_type='$meal_type_s'")->fetch_row();
            if ($exists) {
                $stmt = $db->prepare("UPDATE mess_menu SET menu_items=?,updated_by=? WHERE day_of_week=? AND meal_type=?");
                $stmt->bind_param('siss', $menu_items_s,$_SESSION['user_id'],$day_s,$meal_type_s);
            } else {
                $stmt = $db->prepare("INSERT INTO mess_menu (day_of_week,meal_type,menu_items,updated_by) VALUES (?,?,?,?)");
                $stmt->bind_param('sssi', $day_s,$meal_type_s,$menu_items_s,$_SESSION['user_id']);
            }
            $stmt->execute();
        }
    }
    flashMessage('success', 'Mess menu updated successfully.');
    redirect(SITE_URL . 'php/mess_menu.php');
}

$days  = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$meals = ['breakfast','lunch','snacks','dinner'];

// Fetch all menu items
$menuData = [];
$rows = $db->query("SELECT * FROM mess_menu")->fetch_all(MYSQLI_ASSOC);
foreach ($rows as $row) {
    $menuData[$row['day_of_week']][$row['meal_type']] = $row['menu_items'];
}

$today = date('l');
$mealIcons = ['breakfast' => 'fa-sun', 'lunch' => 'fa-utensils', 'snacks' => 'fa-cookie-bite', 'dinner' => 'fa-moon'];

require_once '../includes/header.php';
?>

<div class="page-header">
    <div><h2>Mess Menu</h2><p>Weekly meal schedule</p></div>
    <?php if (isWarden()): ?>
        <button class="btn btn-primary" id="editMenuBtn" onclick="toggleEditMode()"><i class="fas fa-edit"></i> Edit Menu</button>
    <?php endif; ?>
</div>

<!-- Today's Menu Highlight -->
<div class="card" style="margin-bottom:20px;border-left:4px solid var(--secondary)">
    <div class="card-body">
        <h3 style="font-size:16px;margin-bottom:12px"><i class="fas fa-star" style="color:var(--warning)"></i> Today's Menu — <?= $today ?></h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
            <?php foreach ($meals as $meal): ?>
            <div style="padding:14px;background:var(--light-gray);border-radius:8px">
                <div style="font-weight:600;font-size:13px;text-transform:capitalize;margin-bottom:6px;color:var(--primary)">
                    <i class="fas <?= $mealIcons[$meal] ?>"></i> <?= ucfirst($meal) ?>
                </div>
                <div style="font-size:13px;color:var(--dark);line-height:1.5">
                    <?= htmlspecialchars($menuData[$today][$meal] ?? 'Menu not set') ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Full Week Menu -->
<form method="POST" id="menuForm">
<div style="overflow-x:auto">
<table style="min-width:800px;border-collapse:collapse;width:100%">
    <thead>
        <tr style="background:var(--dark);color:white">
            <th style="padding:12px 16px;text-align:left;width:120px">Day</th>
            <?php foreach ($meals as $meal): ?>
            <th style="padding:12px 16px;text-align:left">
                <i class="fas <?= $mealIcons[$meal] ?>"></i> <?= ucfirst($meal) ?>
            </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($days as $day): ?>
        <tr style="<?= $day===$today ? 'background:rgba(79,70,229,0.06);' : '' ?>border-bottom:1px solid var(--border)">
            <td style="padding:12px 16px;font-weight:600;<?= $day===$today?'color:var(--primary)':'' ?>">
                <?= $day ?>
                <?php if ($day===$today): ?><br><span style="font-size:11px;background:var(--primary);color:white;padding:1px 6px;border-radius:10px">Today</span><?php endif; ?>
            </td>
            <?php foreach ($meals as $meal): ?>
            <td style="padding:10px 16px;vertical-align:top">
                <!-- View Mode -->
                <div class="menu-view-<?= strtolower($day) ?>-<?= $meal ?>" style="font-size:13.5px;line-height:1.6">
                    <?= htmlspecialchars($menuData[$day][$meal] ?? '—') ?>
                </div>
                <!-- Edit Mode (hidden by default) -->
                <?php if (isWarden()): ?>
                <textarea name="menu[<?= $day ?>][<?= $meal ?>]"
                    class="form-control menu-edit" style="display:none;min-height:70px;font-size:13px"
                    placeholder="Enter <?= $meal ?> items..."><?= htmlspecialchars($menuData[$day][$meal] ?? '') ?></textarea>
                <?php endif; ?>
            </td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if (isWarden()): ?>
<div id="saveRow" style="display:none;margin-top:16px;text-align:right">
    <button type="button" class="btn btn-light" onclick="toggleEditMode()">Cancel</button>
    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Menu</button>
</div>
<?php endif; ?>
</form>

<script>
let editMode = false;
function toggleEditMode() {
    editMode = !editMode;
    document.querySelectorAll('[class^="menu-view-"]').forEach(el => el.style.display = editMode ? 'none' : 'block');
    document.querySelectorAll('.menu-edit').forEach(el => el.style.display = editMode ? 'block' : 'none');
    document.getElementById('saveRow').style.display = editMode ? 'block' : 'none';
    const btn = document.getElementById('editMenuBtn');
    btn.innerHTML = editMode ? '<i class="fas fa-times"></i> Cancel Edit' : '<i class="fas fa-edit"></i> Edit Menu';
    btn.className = editMode ? 'btn btn-warning' : 'btn btn-primary';
}
</script>

<?php require_once '../includes/footer.php'; ?>
