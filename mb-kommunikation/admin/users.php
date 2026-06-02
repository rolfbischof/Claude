<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
$auth->requireRole('viewer'); // minimum role to access admin

$auth->requireRole('admin'); // users page requires admin+

$db = Database::getInstance();

// ── Helpers ───────────────────────────────────────────────────────────────
$roleHierarchy = ['viewer' => 0, 'editor' => 1, 'admin' => 2, 'superadmin' => 3];
$currentUserRole = $auth->getUserRole();
$currentUserId   = $auth->getUserId();
$csrfToken = $auth->getCsrfToken();

function roleLabel(string $role): string {
    return match($role) {
        'superadmin' => 'Superadmin', 'admin' => 'Admin', 'editor' => 'Editor', default => 'Viewer'
    };
}
function roleBadge(string $role): string {
    $cls = match($role) {
        'superadmin' => 'bg-red-100 text-red-700', 'admin' => 'bg-orange-100 text-orange-700',
        'editor' => 'bg-blue-100 text-blue-700', default => 'bg-gray-100 text-gray-600'
    };
    return '<span class="badge '.$cls.'">'.roleLabel($role).'</span>';
}

// ── Handle POST actions ────────────────────────────────────────────────────
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $username   = trim($_POST['username'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $firstName  = trim($_POST['first_name'] ?? '');
        $lastName   = trim($_POST['last_name'] ?? '');
        $role       = $_POST['role'] ?? 'viewer';
        $password   = $_POST['password'] ?? '';
        $active     = isset($_POST['active']) ? 1 : 0;

        if (strlen($username) < 3)      $errors[] = 'Benutzername muss mindestens 3 Zeichen lang sein.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Ungültige E-Mail-Adresse.';
        if (strlen($password) < 8)      $errors[] = 'Passwort muss mindestens 8 Zeichen lang sein.';
        if (!array_key_exists($role, $roleHierarchy)) $errors[] = 'Ungültige Rolle.';
        // Admin cannot create superadmin unless they are superadmin
        if ($role === 'superadmin' && !$auth->hasRole('superadmin')) $errors[] = 'Keine Berechtigung für Superadmin.';

        if (empty($errors)) {
            if ($db->exists('users', ['username' => $username])) $errors[] = 'Benutzername bereits vergeben.';
            if ($db->exists('users', ['email' => $email]))       $errors[] = 'E-Mail-Adresse bereits vergeben.';
        }

        if (empty($errors)) {
            $db->insert('users', [
                'username'      => $username,
                'email'         => $email,
                'first_name'    => $firstName,
                'last_name'     => $lastName,
                'role'          => $role,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]),
                'active'        => $active,
            ]);
            $auth->flash('Benutzer «'.$username.'» wurde erstellt.', 'success');
            header('Location: users.php');
            exit;
        }

    } elseif ($action === 'edit_user') {
        $uid       = (int) ($_POST['user_id'] ?? 0);
        $email     = trim($_POST['email'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $role      = $_POST['role'] ?? 'viewer';
        $active    = isset($_POST['active']) ? 1 : 0;
        $newPass   = $_POST['new_password'] ?? '';
        $target    = $db->find('users', $uid);

        if (!$target) { $errors[] = 'Benutzer nicht gefunden.'; }
        if ($target && $target['role'] === 'superadmin' && !$auth->hasRole('superadmin')) {
            $errors[] = 'Superadmin kann nicht bearbeitet werden.';
        }
        if ($role === 'superadmin' && !$auth->hasRole('superadmin')) $errors[] = 'Keine Berechtigung für Superadmin.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Ungültige E-Mail-Adresse.';
        if (!array_key_exists($role, $roleHierarchy)) $errors[] = 'Ungültige Rolle.';
        if ($newPass !== '' && strlen($newPass) < 8) $errors[] = 'Neues Passwort muss mindestens 8 Zeichen lang sein.';

        if (empty($errors)) {
            $dupeEmail = $db->fetchOne('SELECT id FROM users WHERE email = ? AND id != ?', [$email, $uid]);
            if ($dupeEmail) $errors[] = 'E-Mail-Adresse bereits vergeben.';
        }

        if (empty($errors) && $target) {
            $updateData = [
                'email'      => $email,
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'role'       => $role,
                'active'     => $active,
            ];
            if ($newPass !== '') {
                $updateData['password_hash'] = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
            }
            $db->update('users', $updateData, ['id' => $uid]);
            $auth->flash('Benutzer wurde aktualisiert.', 'success');
            header('Location: users.php');
            exit;
        }

    } elseif ($action === 'delete_user') {
        $uid    = (int) ($_POST['user_id'] ?? 0);
        $target = $db->find('users', $uid);
        if (!$target || $target['role'] === 'superadmin') {
            $auth->flash('Dieser Benutzer kann nicht gelöscht werden.', 'error');
        } elseif ($uid === $currentUserId) {
            $auth->flash('Sie können sich nicht selbst löschen.', 'error');
        } else {
            $db->delete('users', ['id' => $uid]);
            $auth->flash('Benutzer wurde gelöscht.', 'success');
        }
        header('Location: users.php');
        exit;

    } elseif ($action === 'toggle_active') {
        $uid    = (int) ($_POST['user_id'] ?? 0);
        $target = $db->find('users', $uid);
        if ($target && $target['role'] !== 'superadmin') {
            $db->update('users', ['active' => $target['active'] ? 0 : 1], ['id' => $uid]);
            $auth->flash('Status geändert.', 'success');
        } else {
            $auth->flash('Superadmin kann nicht deaktiviert werden.', 'error');
        }
        header('Location: users.php');
        exit;
    }
}

// ── Fetch/filter users ────────────────────────────────────────────────────
$filterRole   = $_GET['role'] ?? '';
$searchQuery  = trim($_GET['q'] ?? '');
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 20;

$whereSQL   = '1=1';
$params     = [];
if ($filterRole && array_key_exists($filterRole, $roleHierarchy)) {
    $whereSQL .= ' AND role = ?';
    $params[]  = $filterRole;
}
if ($searchQuery !== '') {
    $whereSQL .= ' AND (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)';
    $like      = '%'.$searchQuery.'%';
    $params    = array_merge($params, [$like, $like, $like, $like]);
}

$totalCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM users WHERE $whereSQL", $params);
$totalPages = max(1, (int) ceil($totalCount / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$users = $db->fetchAll(
    "SELECT * FROM users WHERE $whereSQL ORDER BY role DESC, username ASC LIMIT $perPage OFFSET $offset",
    $params
);

// Edit prefill
$editUser = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editUser = $db->find('users', (int) $_GET['id']);
}

$pageTitle   = 'Benutzerverwaltung';
$currentPage = 'users';
require_once 'layout.php';
?>

<div class="flex items-center justify-between mb-6 flex-wrap gap-3" x-data="{ showModal: <?= ($editUser || !empty($errors)) ? 'true' : 'false' ?>, modalMode: '<?= $editUser ? 'edit' : 'create' ?>' }">
    <div>
        <p class="text-sm text-gray-500"><?= $totalCount ?> Benutzer gefunden</p>
    </div>
    <button @click="showModal=true; modalMode='create'"
            class="btn-primary">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Neuer Benutzer
    </button>
</div>

<!-- Filters -->
<div class="card p-4 mb-5">
    <form method="get" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="form-label">Suche</label>
            <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>"
                   placeholder="Name oder E-Mail…" class="form-input">
        </div>
        <div class="min-w-[140px]">
            <label class="form-label">Rolle</label>
            <select name="role" class="form-input">
                <option value="">Alle Rollen</option>
                <?php foreach (['viewer','editor','admin','superadmin'] as $r): ?>
                <option value="<?= $r ?>" <?= $filterRole === $r ? 'selected' : '' ?>><?= roleLabel($r) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Filtern</button>
            <a href="users.php" class="btn-gray">Reset</a>
        </div>
    </form>
</div>

<!-- Users table -->
<div class="card overflow-hidden" x-data="{
    showModal: <?= ($editUser || !empty($errors)) ? 'true' : 'false' ?>,
    modalMode: '<?= $editUser ? 'edit' : 'create' ?>',
    editId: <?= $editUser ? $editUser['id'] : 'null' ?>,
    editData: <?= $editUser ? json_encode($editUser) : 'null' ?>,
    deleteId: null,
    showDelete: false,
    openEdit(user) {
        this.editData = user;
        this.editId   = user.id;
        this.modalMode = 'edit';
        this.showModal = true;
    },
    openCreate() {
        this.editData = null;
        this.editId   = null;
        this.modalMode = 'create';
        this.showModal = true;
    },
    confirmDelete(id) {
        this.deleteId  = id;
        this.showDelete = true;
    }
}">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="table-th">Benutzer</th>
                    <th class="table-th">E-Mail</th>
                    <th class="table-th">Rolle</th>
                    <th class="table-th">Status</th>
                    <th class="table-th">Letzter Login</th>
                    <th class="table-th text-right">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr><td colspan="6" class="table-td text-center py-8 text-gray-400">Keine Benutzer gefunden.</td></tr>
                <?php endif; ?>
                <?php foreach ($users as $u): ?>
                <?php
                $isSelf   = $u['id'] === $currentUserId;
                $isSuperA = $u['role'] === 'superadmin';
                $canEdit  = !$isSuperA || $auth->hasRole('superadmin');
                $canDelete = !$isSuperA && !$isSelf;
                $displayName = trim(($u['first_name'] ?? '').' '.($u['last_name'] ?? '')) ?: $u['username'];
                ?>
                <tr class="table-tr" x-data="{}">
                    <td class="table-td">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                <span class="text-primary text-xs font-semibold"><?= htmlspecialchars(mb_substr($displayName, 0, 1)) ?></span>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800"><?= htmlspecialchars($displayName) ?></p>
                                <p class="text-xs text-gray-400">@<?= htmlspecialchars($u['username']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="table-td text-gray-500"><?= htmlspecialchars($u['email']) ?></td>
                    <td class="table-td"><?= roleBadge($u['role']) ?></td>
                    <td class="table-td">
                        <?php if ($u['active']): ?>
                        <span class="badge bg-green-100 text-green-700">Aktiv</span>
                        <?php else: ?>
                        <span class="badge bg-red-100 text-red-600">Inaktiv</span>
                        <?php endif; ?>
                    </td>
                    <td class="table-td text-gray-500">
                        <?= $u['last_login'] ? format_date($u['last_login'], 'd.m.Y H:i') : '–' ?>
                    </td>
                    <td class="table-td">
                        <div class="flex items-center justify-end gap-1">
                            <?php if ($canEdit): ?>
                            <button @click="openEdit(<?= htmlspecialchars(json_encode([
                                'id'         => (int) $u['id'],
                                'username'   => $u['username'],
                                'email'      => $u['email'],
                                'first_name' => $u['first_name'] ?? '',
                                'last_name'  => $u['last_name'] ?? '',
                                'role'       => $u['role'],
                                'active'     => (bool) $u['active'],
                            ]), ENT_QUOTES) ?>)"
                                    class="p-1.5 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Bearbeiten">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <?php endif; ?>
                            <?php if (!$isSuperA): ?>
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action"  value="toggle_active">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-<?= $u['active'] ? 'orange' : 'green' ?>-600 hover:bg-<?= $u['active'] ? 'orange' : 'green' ?>-50 rounded-lg transition-colors"
                                        title="<?= $u['active'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                                    <?php if ($u['active']): ?>
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                    <?php else: ?>
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <?php endif; ?>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                            <button @click="confirmDelete(<?= $u['id'] ?>)"
                                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Löschen">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-sm text-gray-500">Seite <?= $page ?> von <?= $totalPages ?></p>
        <div class="flex gap-1">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?page=<?= $p ?>&role=<?= urlencode($filterRole) ?>&q=<?= urlencode($searchQuery) ?>"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-sm <?= $p === $page ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Create/Edit Modal ──────────────────────────────────────────────── -->
    <div x-show="showModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="showModal=false">
        <div class="absolute inset-0 bg-gray-900/60" @click="showModal=false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-y-auto max-h-[90vh]"
             @click.stop>

            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800" x-text="modalMode === 'edit' ? 'Benutzer bearbeiten' : 'Neuer Benutzer'"></h2>
                <button @click="showModal=false" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Error messages -->
            <?php if (!empty($errors)): ?>
            <div class="mx-6 mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                <?php foreach ($errors as $err): ?>
                <p class="text-sm text-red-700"><?= htmlspecialchars($err) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Create form -->
            <div x-show="modalMode === 'create'">
                <form method="post" class="p-6 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_user">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Vorname</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Nachname</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" class="form-input">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Benutzername <span class="text-red-500">*</span></label>
                        <input type="text" name="username" required minlength="3"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">E-Mail-Adresse <span class="text-red-500">*</span></label>
                        <input type="email" name="email" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Passwort <span class="text-red-500">*</span></label>
                        <input type="password" name="password" required minlength="8" class="form-input"
                               placeholder="Mindestens 8 Zeichen">
                    </div>
                    <div>
                        <label class="form-label">Rolle <span class="text-red-500">*</span></label>
                        <select name="role" class="form-input">
                            <?php foreach (['viewer','editor','admin'] as $r): ?>
                            <option value="<?= $r ?>" <?= ($_POST['role'] ?? 'viewer') === $r ? 'selected' : '' ?>><?= roleLabel($r) ?></option>
                            <?php endforeach; ?>
                            <?php if ($auth->hasRole('superadmin')): ?>
                            <option value="superadmin" <?= ($_POST['role'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="active" id="new_active" value="1" checked class="w-4 h-4 accent-primary">
                        <label for="new_active" class="text-sm text-gray-700">Benutzer aktiv</label>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showModal=false" class="btn-gray">Abbrechen</button>
                        <button type="submit" class="btn-primary">Benutzer erstellen</button>
                    </div>
                </form>
            </div>

            <!-- Edit form -->
            <div x-show="modalMode === 'edit'" x-cloak>
                <form method="post" class="p-6 space-y-4" id="editUserForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action"  value="edit_user">
                    <input type="hidden" name="user_id" :value="editId">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Vorname</label>
                            <input type="text" name="first_name" :value="editData?.first_name" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Nachname</label>
                            <input type="text" name="last_name" :value="editData?.last_name" class="form-input">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Benutzername</label>
                        <input type="text" :value="editData?.username" class="form-input bg-gray-50 cursor-not-allowed" readonly>
                        <p class="text-xs text-gray-400 mt-1">Benutzername kann nicht geändert werden.</p>
                    </div>
                    <div>
                        <label class="form-label">E-Mail-Adresse <span class="text-red-500">*</span></label>
                        <input type="email" name="email" :value="editData?.email" required class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Neues Passwort</label>
                        <input type="password" name="new_password" class="form-input"
                               placeholder="Leer lassen = keine Änderung" minlength="8">
                    </div>
                    <div>
                        <label class="form-label">Rolle</label>
                        <select name="role" x-model="editData.role" class="form-input"
                                :disabled="editData?.role === 'superadmin' && !<?= $auth->hasRole('superadmin') ? 'false' : 'true' ?>">
                            <?php foreach (['viewer','editor','admin'] as $r): ?>
                            <option value="<?= $r ?>"><?= roleLabel($r) ?></option>
                            <?php endforeach; ?>
                            <?php if ($auth->hasRole('superadmin')): ?>
                            <option value="superadmin">Superadmin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="active" id="edit_active" value="1"
                               :checked="editData?.active" class="w-4 h-4 accent-primary"
                               x-effect="$el.checked = editData?.active">
                        <label for="edit_active" class="text-sm text-gray-700">Benutzer aktiv</label>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showModal=false" class="btn-gray">Abbrechen</button>
                        <button type="submit" class="btn-primary">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete confirmation modal -->
    <div x-show="showDelete" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-gray-900/60" @click="showDelete=false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6" @click.stop>
            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-red-100 mx-auto mb-4">
                <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.924-.833-2.694 0L3.34 16.5c-.77.833.193 2.5 1.732 2.5z"/></svg>
            </div>
            <h3 class="text-center font-semibold text-gray-800 mb-2">Benutzer löschen?</h3>
            <p class="text-center text-sm text-gray-500 mb-6">Diese Aktion kann nicht rückgängig gemacht werden.</p>
            <form method="post" class="flex gap-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action"  value="delete_user">
                <input type="hidden" name="user_id" :value="deleteId">
                <button type="button" @click="showDelete=false" class="btn-gray flex-1">Abbrechen</button>
                <button type="submit" class="btn-danger flex-1">Löschen</button>
            </form>
        </div>
    </div>

</div><!-- /card x-data -->

<?php require_once 'layout_end.php'; ?>
