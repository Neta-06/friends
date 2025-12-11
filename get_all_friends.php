<?php
session_start();
require_once "../config/database.php";

header('Content-Type: application/json');

// Kullanıcı ID'sini oturumdan al
if (!isset($_SESSION['kullanici_id'])) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim.']);
    exit;
}
$kullanici_id = $_SESSION['kullanici_id'];

$database = new Database();
$db = $database->getConnection();

// DEBUG
error_log("=== EN GÜNCEL ARKADAŞ KONUMLARI ===");
error_log("Kullanıcı ID: " . $kullanici_id);

// EN GÜNCEL KONUMLARI GETİREN SORGU
$query = "
    SELECT 
        k.id,
        k.ad,
        k.soyad, 
        k.cep_telefonu,
        k.profil_resmi,
        k.cinsiyet,
        son_konum.enlem,
        son_konum.boylam,
        son_konum.zaman as son_konum_zaman,
        son_konum.hiz,
        son_konum.yon
    FROM arkadasliklar a
    JOIN kullanicilar k ON (
        (a.gonderen_id = k.id AND a.alici_id = ?) OR 
        (a.alici_id = k.id AND a.gonderen_id = ?)
    )
    JOIN (
        -- Her kullanıcının EN SON konumunu al
        SELECT 
            k1.kullanici_id,
            k1.enlem,
            k1.boylam,
            k1.zaman,
            k1.hiz,
            k1.yon
        FROM konumlar k1
        WHERE k1.zaman = (
            SELECT MAX(k2.zaman)
            FROM konumlar k2 
            WHERE k2.kullanici_id = k1.kullanici_id
        )
    ) son_konum ON k.id = son_konum.kullanici_id
    WHERE a.durum = 'kabul'
    AND k.id != ?
    ORDER BY son_konum.zaman DESC, k.id ASC
";

error_log("SQL: " . $query);

$stmt = $db->prepare($query);
$stmt->execute([$kullanici_id, $kullanici_id, $kullanici_id]);
$friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

error_log("Bulunan arkadaş sayısı: " . count($friends));

// DEBUG: Her arkadaşın en güncel konumunu logla
foreach ($friends as $friend) {
    error_log("EN GÜNCEL - " . $friend['id'] . " - " . $friend['ad'] . " " . $friend['soyad'] . 
              " | Konum: " . $friend['enlem'] . "," . $friend['boylam'] . 
              " | Zaman: " . $friend['son_konum_zaman'] . 
              " | Hız: " . $friend['hiz'] . " km/h");
}

echo json_encode([
    'success' => true,
    'user_id' => $kullanici_id,
    'friends' => $friends,
    'total_friends' => count($friends),
    'friend_ids' => array_map(function($f) { return $f['id']; }, $friends),
    'latest_update' => date('Y-m-d H:i:s')
]);
?>
