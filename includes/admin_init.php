<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_module.php';
require_once __DIR__ . '/admin_helpers.php';
require_once __DIR__ . '/admin_repository.php';

function adminInitPage(string $redirectScript, array $redirectKeys = ['q', 'status', 'page', 'user_type', 'bo_de_id', 'trang_thai']): array
{
    adminRequireRole();

    $pdo = getPdo();
    $adminId = (int) $_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        try {
            adminHandleAction($pdo, $adminId, $action, $_POST);
            adminFlashSet('success', 'Thao tác thành công.');
        } catch (Throwable $e) {
            adminFlashSet('danger', $e->getMessage());
        }

        $params = [];
        foreach ($redirectKeys as $key) {
            $val = $_POST['redirect_' . $key] ?? '';
            if ($val !== '') {
                $params[$key] = $val;
            }
        }
        $qs = http_build_query($params);
        adminRedirect($redirectScript . ($qs ? '?' . $qs : ''));
    }

    return [$pdo, $adminId];
}

function adminCollectFilters(array $keys): array
{
    $filters = [];
    foreach ($keys as $key) {
        $filters[$key] = trim($_GET[$key] ?? '');
    }
    return $filters;
}

function adminHiddenRedirects(array $filters, int $page = 1): string
{
    $html = '';
    foreach ($filters as $key => $val) {
        $html .= '<input type="hidden" name="redirect_' . htmlspecialchars($key) . '" value="' . htmlspecialchars($val) . '" />';
    }
    $html .= '<input type="hidden" name="redirect_page" value="' . $page . '" />';
    return $html;
}
