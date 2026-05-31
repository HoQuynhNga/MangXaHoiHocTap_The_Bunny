<?php
require_once __DIR__ . '/../config/db_module.php';
require_once __DIR__ . '/bunny_helpers.php';

function buddiesUserTypeLabel(string $type): string
{
    return match ($type) {
        'hoc_sinh'       => 'Học sinh',
        'sinh_vien'      => 'Sinh viên',
        'giao_vien'      => 'Giáo viên',
        'quan_tri_vien'  => 'Quản trị viên',
        default          => 'Thành viên',
    };
}

function buddiesSchoolLabel(?string $truongHoc, ?string $truongDaiHoc): string
{
    $school = trim((string) ($truongDaiHoc ?: $truongHoc));
    return $school !== '' ? $school : 'The Bunny';
}

function buddiesMapUserRow(array $row): array
{
    $userId = (int) $row['id'];
    $xp = (int) ($row['xp_points'] ?? 0);

    return [
        'id'         => $userId,
        'username'   => (string) $row['username'],
        'name'       => bunnyDisplayName($row['username'] ?? '', $row['thong_tin_dinh_danh'] ?? ''),
        'avatar'     => bunnyAvatar($userId),
        'user_type'  => buddiesUserTypeLabel((string) ($row['user_type'] ?? '')),
        'school'     => buddiesSchoolLabel($row['truong_hoc'] ?? null, $row['truong_dai_hoc'] ?? null),
        'is_online'  => (int) ($row['is_online'] ?? 0) === 1,
        'xp'         => $xp,
        'xp_rank'    => bunnyXpRank($xp),
        'profile_url'=> 'trang-ca-nhan.php?user_id=' . $userId,
    ];
}

function buddiesLoadCurrentUser(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT u.id, u.username, h.thong_tin_dinh_danh,
                COALESCE((
                    SELECT SUM(p.diem_so) FROM phien_luyen_tap p WHERE p.user_id = u.id
                ), 0) AS xp_points
         FROM users u
         LEFT JOIN ho_so_ca_nhan h ON h.user_id = u.id
         WHERE u.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $user = buddiesMapUserRow($row);
    $user['profile_url'] = 'trang-ca-nhan.php';

    return $user;
}

function buddiesBaseUserSelect(): string
{
    return 'u.id, u.username, u.user_type, u.truong_hoc, u.truong_dai_hoc, u.is_online,
            h.thong_tin_dinh_danh,
            COALESCE((
                SELECT SUM(p.diem_so) FROM phien_luyen_tap p WHERE p.user_id = u.id
            ), 0) AS xp_points';
}

/**
 * Subquery SQL: một relation Accepted duy nhất cho mỗi cặp user (gom cả A→B lẫn B→A).
 * Dùng trong JOIN, bind :me cho user hiện tại.
 */
function buddiesSqlUniqueAcceptedRelationIds(): string
{
    return "
        SELECT MIN(f2.id) AS id
        FROM ban_cung_tien f2
        WHERE f2.status = 'Accepted'
          AND (f2.user_id = :me OR f2.friend_user_id = :me)
        GROUP BY LEAST(f2.user_id, f2.friend_user_id), GREATEST(f2.user_id, f2.friend_user_id)
    ";
}

function buddiesLoadAccepted(PDO $pdo, int $currentUserId): array
{
    $stmt = $pdo->prepare(
        'SELECT f.id AS relation_id, ' . buddiesBaseUserSelect() . '
         FROM ban_cung_tien f
         INNER JOIN (' . buddiesSqlUniqueAcceptedRelationIds() . ') uniq ON uniq.id = f.id
         INNER JOIN users u ON u.id = CASE
             WHEN f.user_id = :me2 THEN f.friend_user_id
             ELSE f.user_id
         END
         LEFT JOIN ho_so_ca_nhan h ON h.user_id = u.id
         WHERE u.id <> :me3
         ORDER BY u.is_online DESC, u.username ASC'
    );
    $stmt->execute([
        'me'  => $currentUserId,
        'me2' => $currentUserId,
        'me3' => $currentUserId,
    ]);

    $list = [];
    foreach ($stmt->fetchAll() as $row) {
        $item = buddiesMapUserRow($row);
        $item['relation_id'] = (int) $row['relation_id'];
        $list[] = $item;
    }

    return $list;
}

function buddiesLoadIncoming(PDO $pdo, int $currentUserId): array
{
    $stmt = $pdo->prepare(
        'SELECT f.id AS relation_id, ' . buddiesBaseUserSelect() . '
         FROM ban_cung_tien f
         INNER JOIN users u ON u.id = f.user_id
         LEFT JOIN ho_so_ca_nhan h ON h.user_id = u.id
         WHERE f.status = \'Pending\'
           AND f.friend_user_id = :me
         ORDER BY f.created_at DESC'
    );
    $stmt->execute(['me' => $currentUserId]);

    $list = [];
    foreach ($stmt->fetchAll() as $row) {
        $item = buddiesMapUserRow($row);
        $item['relation_id'] = (int) $row['relation_id'];
        $list[] = $item;
    }

    return $list;
}

function buddiesLoadOutgoing(PDO $pdo, int $currentUserId): array
{
    $stmt = $pdo->prepare(
        'SELECT f.id AS relation_id, ' . buddiesBaseUserSelect() . '
         FROM ban_cung_tien f
         INNER JOIN users u ON u.id = f.friend_user_id
         LEFT JOIN ho_so_ca_nhan h ON h.user_id = u.id
         WHERE f.status = \'Pending\'
           AND f.user_id = :me
         ORDER BY f.created_at DESC'
    );
    $stmt->execute(['me' => $currentUserId]);

    $list = [];
    foreach ($stmt->fetchAll() as $row) {
        $item = buddiesMapUserRow($row);
        $item['relation_id'] = (int) $row['relation_id'];
        $list[] = $item;
    }

    return $list;
}

function buddiesSearchUsers(PDO $pdo, int $currentUserId, string $query, int $limit = 12): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $like = '%' . $query . '%';
    $stmt = $pdo->prepare(
        'SELECT ' . buddiesBaseUserSelect() . ',
                f.id AS relation_id,
                f.status AS relation_status,
                f.user_id AS relation_user_id,
                f.friend_user_id AS relation_friend_id
         FROM users u
         LEFT JOIN ho_so_ca_nhan h ON h.user_id = u.id
         LEFT JOIN ban_cung_tien f ON (
             (f.user_id = :me AND f.friend_user_id = u.id)
             OR (f.user_id = u.id AND f.friend_user_id = :me2)
         )
         WHERE u.id <> :me3
           AND u.status = \'Active\'
           AND (u.username LIKE :q OR u.email LIKE :q2
                OR h.thong_tin_dinh_danh LIKE :q3)
         ORDER BY u.username ASC
         LIMIT ' . (int) $limit
    );
    $stmt->execute([
        'me'  => $currentUserId,
        'me2' => $currentUserId,
        'me3' => $currentUserId,
        'q'   => $like,
        'q2'  => $like,
        'q3'  => $like,
    ]);

    $list = [];
    foreach ($stmt->fetchAll() as $row) {
        $item = buddiesMapUserRow($row);
        $item['relation_id'] = $row['relation_id'] !== null ? (int) $row['relation_id'] : null;
        $item['relation_status'] = $row['relation_status'] ?? null;
        $item['can_request'] = $item['relation_id'] === null;
        $item['is_incoming'] = $item['relation_status'] === 'Pending'
            && (int) ($row['relation_friend_id'] ?? 0) === $currentUserId;
        $item['is_outgoing'] = $item['relation_status'] === 'Pending'
            && (int) ($row['relation_user_id'] ?? 0) === $currentUserId;
        $item['is_friend'] = $item['relation_status'] === 'Accepted';
        $list[] = $item;
    }

    return $list;
}

function buddiesSuggestUsers(PDO $pdo, int $currentUserId, int $limit = 6): array
{
    $stmt = $pdo->prepare(
        'SELECT ' . buddiesBaseUserSelect() . '
         FROM users u
         LEFT JOIN ho_so_ca_nhan h ON h.user_id = u.id
         WHERE u.id <> :me
           AND u.status = \'Active\'
           AND NOT EXISTS (
               SELECT 1 FROM ban_cung_tien f
               WHERE (f.user_id = :me2 AND f.friend_user_id = u.id)
                  OR (f.user_id = u.id AND f.friend_user_id = :me3)
           )
         ORDER BY u.is_online DESC, u.username ASC
         LIMIT ' . (int) $limit
    );
    $stmt->execute([
        'me'  => $currentUserId,
        'me2' => $currentUserId,
        'me3' => $currentUserId,
    ]);

    $list = [];
    foreach ($stmt->fetchAll() as $row) {
        $item = buddiesMapUserRow($row);
        $item['can_request'] = true;
        $list[] = $item;
    }

    return $list;
}

function buddiesGetRelation(PDO $pdo, int $relationId, int $currentUserId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, user_id, friend_user_id, status
         FROM ban_cung_tien
         WHERE id = :id
           AND (user_id = :me OR friend_user_id = :me2)
         LIMIT 1'
    );
    $stmt->execute(['id' => $relationId, 'me' => $currentUserId, 'me2' => $currentUserId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function buddiesSendRequest(PDO $pdo, int $currentUserId, int $targetUserId): array
{
    if ($targetUserId <= 0 || $targetUserId === $currentUserId) {
        throw new InvalidArgumentException('Người dùng không hợp lệ.');
    }

    $check = $pdo->prepare('SELECT id FROM users WHERE id = :id AND status = \'Active\' LIMIT 1');
    $check->execute(['id' => $targetUserId]);
    if (!$check->fetch()) {
        throw new RuntimeException('Không tìm thấy người dùng.');
    }

    $existing = $pdo->prepare(
        'SELECT id, user_id, friend_user_id, status
         FROM ban_cung_tien
         WHERE (user_id = :me AND friend_user_id = :target)
            OR (user_id = :target2 AND friend_user_id = :me2)
         LIMIT 1'
    );
    $existing->execute([
        'me'       => $currentUserId,
        'target'   => $targetUserId,
        'target2'  => $targetUserId,
        'me2'      => $currentUserId,
    ]);
    $row = $existing->fetch();

    if ($row) {
        if ($row['status'] === 'Accepted') {
            throw new RuntimeException('Hai bạn đã là bạn cùng tiến.');
        }
        if ($row['status'] === 'Pending' && (int) $row['user_id'] === $currentUserId) {
            throw new RuntimeException('Bạn đã gửi lời mời trước đó.');
        }
        if ($row['status'] === 'Pending' && (int) $row['friend_user_id'] === $currentUserId) {
            buddiesAcceptRequest($pdo, $currentUserId, (int) $row['id']);
            return ['message' => 'Đã chấp nhận lời mời — hai bạn giờ là bạn cùng tiến!'];
        }
    }

    $ins = $pdo->prepare(
        'INSERT INTO ban_cung_tien (user_id, friend_user_id, status)
         VALUES (:uid, :fid, \'Pending\')'
    );
    $ins->execute(['uid' => $currentUserId, 'fid' => $targetUserId]);

    return ['message' => 'Đã gửi lời mời kết bạn.'];
}

function buddiesAcceptRequest(PDO $pdo, int $currentUserId, int $relationId): array
{
    $relation = buddiesGetRelation($pdo, $relationId, $currentUserId);
    if (!$relation || $relation['status'] !== 'Pending') {
        throw new RuntimeException('Lời mời không hợp lệ.');
    }
    if ((int) $relation['friend_user_id'] !== $currentUserId) {
        throw new RuntimeException('Bạn không thể chấp nhận lời mời này.');
    }

    $upd = $pdo->prepare('UPDATE ban_cung_tien SET status = \'Accepted\' WHERE id = :id');
    $upd->execute(['id' => $relationId]);

    return ['message' => 'Đã chấp nhận lời mời kết bạn.'];
}

function buddiesDeclineRequest(PDO $pdo, int $currentUserId, int $relationId): array
{
    $relation = buddiesGetRelation($pdo, $relationId, $currentUserId);
    if (!$relation || $relation['status'] !== 'Pending') {
        throw new RuntimeException('Lời mời không hợp lệ.');
    }
    if ((int) $relation['friend_user_id'] !== $currentUserId) {
        throw new RuntimeException('Bạn không thể từ chối lời mời này.');
    }

    $del = $pdo->prepare('DELETE FROM ban_cung_tien WHERE id = :id');
    $del->execute(['id' => $relationId]);

    return ['message' => 'Đã từ chối lời mời.'];
}

function buddiesCancelRequest(PDO $pdo, int $currentUserId, int $relationId): array
{
    $relation = buddiesGetRelation($pdo, $relationId, $currentUserId);
    if (!$relation || $relation['status'] !== 'Pending') {
        throw new RuntimeException('Lời mời không hợp lệ.');
    }
    if ((int) $relation['user_id'] !== $currentUserId) {
        throw new RuntimeException('Bạn không thể hủy lời mời này.');
    }

    $del = $pdo->prepare('DELETE FROM ban_cung_tien WHERE id = :id');
    $del->execute(['id' => $relationId]);

    return ['message' => 'Đã hủy lời mời đã gửi.'];
}

function buddiesRemoveFriend(PDO $pdo, int $currentUserId, int $relationId): array
{
    $relation = buddiesGetRelation($pdo, $relationId, $currentUserId);
    if (!$relation || $relation['status'] !== 'Accepted') {
        throw new RuntimeException('Liên kết bạn cùng tiến không hợp lệ.');
    }

    $del = $pdo->prepare('DELETE FROM ban_cung_tien WHERE id = :id');
    $del->execute(['id' => $relationId]);

    return ['message' => 'Đã xóa khỏi danh sách bạn cùng tiến.'];
}

function buddiesLoadPage(PDO $pdo, int $currentUserId, string $searchQuery = ''): array
{
    $currentUser = buddiesLoadCurrentUser($pdo, $currentUserId);
    if ($currentUser === null) {
        throw new RuntimeException('Không tìm thấy user id=' . $currentUserId);
    }

    $accepted = buddiesLoadAccepted($pdo, $currentUserId);
    $incoming = buddiesLoadIncoming($pdo, $currentUserId);
    $outgoing = buddiesLoadOutgoing($pdo, $currentUserId);
    $searchResults = $searchQuery !== ''
        ? buddiesSearchUsers($pdo, $currentUserId, $searchQuery)
        : [];

    return [
        'currentUser'     => $currentUser,
        'acceptedFriends' => $accepted,
        'incomingRequests'=> $incoming,
        'outgoingRequests'=> $outgoing,
        'suggestions'     => buddiesSuggestUsers($pdo, $currentUserId),
        'searchResults'   => $searchResults,
        'searchQuery'     => $searchQuery,
        'counts'          => [
            'accepted' => count($accepted),
            'incoming' => count($incoming),
            'outgoing' => count($outgoing),
        ],
    ];
}

function buddiesHandleAction(PDO $pdo, int $currentUserId, string $action, array $input): array
{
    return match ($action) {
        'send_request'    => buddiesSendRequest($pdo, $currentUserId, (int) ($input['user_id'] ?? 0)),
        'accept_request'  => buddiesAcceptRequest($pdo, $currentUserId, (int) ($input['relation_id'] ?? 0)),
        'decline_request' => buddiesDeclineRequest($pdo, $currentUserId, (int) ($input['relation_id'] ?? 0)),
        'cancel_request'  => buddiesCancelRequest($pdo, $currentUserId, (int) ($input['relation_id'] ?? 0)),
        'remove_friend'   => buddiesRemoveFriend($pdo, $currentUserId, (int) ($input['relation_id'] ?? 0)),
        'search'          => [
            'results' => buddiesSearchUsers($pdo, $currentUserId, (string) ($input['q'] ?? '')),
        ],
        default           => throw new InvalidArgumentException('Hành động không hợp lệ.'),
    };
}
