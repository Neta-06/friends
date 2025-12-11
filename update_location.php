<?php
session_start();
require_once "../config/database.php";

header('Content-Type: application/json');

if (!isset($_SESSION['kullanici_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum gerekli']);
    exit;
}

if ($_POST && isset($_POST['enlem']) && isset($_POST['boylam'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    $kullanici_id = $_SESSION['kullanici_id'];
    $enlem = $_POST['enlem'];
    $boylam = $_POST['boylam'];
    $hiz = isset($_POST['hiz']) ? $_POST['hiz'] : 0;
    $yon = isset($_POST['yon']) ? $_POST['yon'] : 0;
    
    try {
        // Transaction başlat
        $db->beginTransaction();
        
        // 1. konumlar tablosuna yeni kayıt ekle
        $query1 = "INSERT INTO konumlar (kullanici_id, enlem, boylam, hiz, yon, zaman) 
                  VALUES (:kullanici_id, :enlem, :boylam, :hiz, :yon, NOW())";
        
        $stmt1 = $db->prepare($query1);
        $stmt1->bindParam(":kullanici_id", $kullanici_id);
        $stmt1->bindParam(":enlem", $enlem);
        $stmt1->bindParam(":boylam", $boylam);
        $stmt1->bindParam(":hiz", $hiz);
        $stmt1->bindParam(":yon", $yon);
        
        $insertResult = $stmt1->execute();
        
        if (!$insertResult) {
            throw new Exception('Konumlar tablosuna kayıt eklenemedi');
        }
        
        // 2. kullanıcılar tablosundaki enlem ve boylamı güncelle
        $query2 = "UPDATE kullanicilar 
                  SET enlem = :enlem, boylam = :boylam, son_gorulme = NOW() 
                  WHERE id = :id";
        
        $stmt2 = $db->prepare($query2);
        $stmt2->bindParam(":enlem", $enlem);
        $stmt2->bindParam(":boylam", $boylam);
        $stmt2->bindParam(":id", $kullanici_id);
        
        $updateResult = $stmt2->execute();
        
        if (!$updateResult) {
            throw new Exception('Kullanıcılar tablosu güncellenemedi');
        }
        
        // Transaction'ı commit et
        $db->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Konum hem konumlar tablosuna eklendi hem de kullanıcılar tablosunda güncellendi'
        ]);
        
    } catch (Exception $e) {
        // Hata durumunda transaction'ı rollback et
        $db->rollBack();
        echo json_encode([
            'success' => false, 
            'message' => 'İşlem sırasında hata: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Eksik parametreler']);
}
?>